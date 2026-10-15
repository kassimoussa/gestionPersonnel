<?php

namespace Tests\Feature;

use App\Enums\TypeDocumentEmploye;
use App\Livewire\Employe\Fiche;
use App\Livewire\Employe\Index as EmployeIndex;
use App\Models\Contrat;
use App\Models\Departement;
use App\Models\DocumentEmploye;
use App\Models\Employe;
use App\Models\SoldeCongeAnnuel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fiche détaillée d'un employé et pièces de son dossier.
 */
class FicheEmployeTest extends TestCase
{
    use RefreshDatabase;

    private Employe $employe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-20 09:00:00');

        $this->employe = Employe::factory()->create([
            'prenom' => 'Ibado', 'nom' => 'Bouh Guessod', 'poste' => 'Coordinatrice',
            'matricule' => '1001', 'date_naissance' => '1990-04-12', 'lieu_naissance' => 'Djibouti',
            'numero_cnss' => 'CNSS-123', 'date_embauche' => '2020-01-01',
        ]);
    }

    public function test_la_fiche_rassemble_le_dossier_de_l_employe(): void
    {
        $departement = Departement::factory()->create(['nom' => 'Direction Emploi']);
        $this->employe->update(['departement_id' => $departement->id]);
        SoldeCongeAnnuel::factory()->for($this->employe)->create(['annee' => 2026, 'jours_acquis' => 22.5]);
        Contrat::factory()->for($this->employe)->create(['type' => 'CDI', 'salaire_base' => 150000]);

        $this->actingAs($this->creerCompteRh())->get(route('employes.fiche', $this->employe))
            ->assertOk()
            ->assertSeeLivewire(Fiche::class);

        Livewire::actingAs($this->creerCompteRh())
            ->test(Fiche::class, ['employe' => $this->employe])
            ->assertSee('Ibado Bouh Guessod')
            ->assertSee('Coordinatrice')
            ->assertSee('Direction Emploi')
            ->assertSee('12/04/1990')
            ->assertSee('22,5 j')
            ->assertSee('CNSS-123')
            ->assertSee('CDI');
    }

    public function test_un_agent_sans_droit_n_ouvre_aucune_fiche_pas_meme_la_sienne(): void
    {
        $compte = User::factory()->create(['employe_id' => $this->employe->id]);

        $this->actingAs($compte)->get(route('employes.fiche', $this->employe))->assertForbidden();
        $this->actingAs($compte)->get(route('employes.fiche', Employe::factory()->create()))->assertForbidden();
    }

    public function test_un_manager_ouvre_la_fiche_de_son_equipe_sans_les_donnees_sensibles(): void
    {
        $manager = Employe::factory()->create();
        $departement = Departement::factory()->create(['responsable_id' => $manager->id]);
        $this->employe->update(['departement_id' => $departement->id]);
        $compteManager = User::factory()->create(['employe_id' => $manager->id]);

        Livewire::actingAs($compteManager)
            ->test(Fiche::class, ['employe' => $this->employe])
            ->assertSee('Ibado Bouh Guessod')
            ->assertDontSee('CNSS-123')
            ->assertDontSee('Administratif et paie')
            ->assertDontSee('Documents du dossier');
    }

    public function test_le_service_du_personnel_ajoute_un_document_au_dossier(): void
    {
        Storage::fake(DocumentEmploye::DISQUE);

        Livewire::actingAs($this->creerCompteRh())
            ->test(Fiche::class, ['employe' => $this->employe])
            ->assertSee('Documents du dossier')
            ->call('ajouterUnDocument')
            ->assertSeeHtml('role="dialog"')
            ->set('type', TypeDocumentEmploye::Contrat->value)
            ->set('libelle', 'Contrat CDI signé')
            ->set('date_document', '2026-01-05')
            ->set('fichier', UploadedFile::fake()->create('contrat.pdf', 300, 'application/pdf'))
            ->call('enregistrerDocument')
            ->assertHasNoErrors()
            ->assertSee('Document ajouté au dossier');

        $document = DocumentEmploye::sole();
        $this->assertSame($this->employe->id, $document->employe_id);
        $this->assertSame(TypeDocumentEmploye::Contrat, $document->type);
        $this->assertSame('contrat.pdf', $document->fichier_nom);
        $this->assertSame('300 Ko', $document->taille());
        Storage::disk(DocumentEmploye::DISQUE)->assertExists($document->fichier_path);
    }

    public function test_le_document_est_obligatoire_et_limite_aux_formats_attendus(): void
    {
        $composant = Livewire::actingAs($this->creerCompteRh())
            ->test(Fiche::class, ['employe' => $this->employe])
            ->call('ajouterUnDocument')
            ->set('libelle', 'Pièce douteuse');

        $composant->call('enregistrerDocument')->assertHasErrors(['fichier']);

        $composant->set('fichier', UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'))
            ->call('enregistrerDocument')
            ->assertHasErrors(['fichier']);

        $this->assertSame(0, DocumentEmploye::count());
    }

    public function test_le_fichier_disparait_avec_le_document(): void
    {
        Storage::fake(DocumentEmploye::DISQUE);

        $chemin = UploadedFile::fake()->create('diplome.pdf', 20, 'application/pdf')
            ->store(DocumentEmploye::DOSSIER, DocumentEmploye::DISQUE);
        $document = DocumentEmploye::factory()->for($this->employe)->create([
            'fichier_path' => $chemin, 'fichier_nom' => 'diplome.pdf', 'libelle' => 'Diplôme',
        ]);

        Livewire::actingAs($this->creerCompteRh())
            ->test(Fiche::class, ['employe' => $this->employe])
            ->call('supprimerDocument', $document->id)
            ->assertSee('Document retiré du dossier');

        $this->assertModelMissing($document);
        Storage::disk(DocumentEmploye::DISQUE)->assertMissing($chemin);
    }

    public function test_le_telechargement_est_reserve_a_l_employe_et_aux_habilites(): void
    {
        Storage::fake(DocumentEmploye::DISQUE);

        $chemin = UploadedFile::fake()->create('contrat.pdf', 20, 'application/pdf')
            ->store(DocumentEmploye::DOSSIER, DocumentEmploye::DISQUE);
        $document = DocumentEmploye::factory()->for($this->employe)->create([
            'fichier_path' => $chemin, 'fichier_nom' => 'contrat.pdf',
        ]);

        $this->actingAs($this->creerCompteRh())->get(route('employes.document', $document))
            ->assertOk()
            ->assertDownload('contrat.pdf');

        // L'agent n'accède pas aux pièces de son dossier : elles lui sont remises.
        $sien = User::factory()->create(['employe_id' => $this->employe->id]);
        $this->actingAs($sien)->get(route('employes.document', $document))->assertForbidden();

        // Un manager consulte la fiche de son équipe mais pas ses pièces.
        $manager = Employe::factory()->create();
        $departement = Departement::factory()->create(['responsable_id' => $manager->id]);
        $this->employe->update(['departement_id' => $departement->id]);
        $compteManager = User::factory()->create(['employe_id' => $manager->id]);

        $this->actingAs($compteManager)->get(route('employes.document', $document))->assertForbidden();
    }

    public function test_le_bouton_modifier_ouvre_le_formulaire_depuis_la_liste(): void
    {
        $this->actingAs($this->creerCompteRh())
            ->get(route('employes.index', ['editer' => $this->employe->id]))
            ->assertOk()
            ->assertSeeHtml('role="dialog"')
            ->assertSee('Ibado');

        Livewire::actingAs($this->creerCompteRh())
            ->test(EmployeIndex::class)
            ->assertSeeHtml(route('employes.fiche', $this->employe));
    }
}
