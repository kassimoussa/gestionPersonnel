<?php

namespace Tests\Feature;

use App\Enums\StatutPresence;
use App\Enums\TypeEvenementEmploye;
use App\Livewire\Employe\Fiche;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\EvenementEmploye;
use App\Models\User;
use App\Services\Pointages\RapportPresence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sanctions disciplinaires et faits de carrière au dossier d'un agent.
 */
class EvenementsEmployeTest extends TestCase
{
    use RefreshDatabase;

    private Employe $employe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-21 09:00:00');

        $this->employe = Employe::factory()->create([
            'prenom' => 'Moussa', 'nom' => 'Sanctionne', 'date_embauche' => '2020-01-01',
        ]);
    }

    public function test_une_mise_a_pied_est_consignee_avec_sa_periode(): void
    {
        Storage::fake(EvenementEmploye::DISQUE);

        Livewire::actingAs($this->creerCompteRh())
            ->test(Fiche::class, ['employe' => $this->employe])
            ->assertSee('Discipline et parcours')
            ->call('ajouterUnEvenement')
            ->assertSeeHtml('role="dialog"')
            ->set('evenementType', TypeEvenementEmploye::MiseAPied->value)
            ->set('evenementDebut', '2026-09-14')
            ->set('evenementFin', '2026-09-16')
            ->set('evenementAvecSolde', 'sans')
            ->set('evenementMotif', 'Absences répétées non justifiées')
            ->set('evenementReference', 'Décision n° 12/ANEFIP')
            ->set('evenementPiece', UploadedFile::fake()->create('decision.pdf', 50, 'application/pdf'))
            ->call('enregistrerEvenement')
            ->assertHasNoErrors()
            ->assertSee('Mise à pied consigné(e) au dossier');

        $evenement = EvenementEmploye::sole();
        $this->assertSame(TypeEvenementEmploye::MiseAPied, $evenement->type);
        $this->assertSame('2026-09-16', $evenement->date_fin->toDateString());
        $this->assertFalse($evenement->avec_solde);
        $this->assertSame(3, $evenement->nombreJours());
        $this->assertSame('du 14/09/2026 au 16/09/2026', $evenement->periode());
        Storage::disk(EvenementEmploye::DISQUE)->assertExists($evenement->fichier_path);
    }

    public function test_la_fiche_compte_les_mises_a_pied_et_les_sanctions(): void
    {
        EvenementEmploye::factory()->for($this->employe)->miseAPied('2026-03-02', '2026-03-04')->create();
        EvenementEmploye::factory()->for($this->employe)->miseAPied('2026-06-15', '2026-06-15')->create();
        EvenementEmploye::factory()->for($this->employe)->create(['type' => TypeEvenementEmploye::Avertissement]);
        EvenementEmploye::factory()->for($this->employe)->create(['type' => TypeEvenementEmploye::Promotion]);

        $bilan = $this->employe->bilanMisesAPied();
        $this->assertSame(2, $bilan['nombre']);
        // Trois jours en mars, un jour en juin.
        $this->assertSame(4, $bilan['jours']);

        // La promotion n'est pas une sanction.
        $this->assertSame(3, $this->employe->nombreDeSanctions());

        Livewire::actingAs($this->creerCompteRh())
            ->test(Fiche::class, ['employe' => $this->employe])
            ->assertSee('Mises à pied')
            ->assertSee('Jours de suspension');
    }

    public function test_une_mise_a_pied_n_est_pas_une_absence_dans_le_rapport(): void
    {
        EvenementEmploye::factory()->for($this->employe)->miseAPied('2026-09-14', '2026-09-16')->create();

        $lignes = app(RapportPresence::class)->generer(
            collect([$this->employe]),
            CarbonImmutable::parse('2026-09-14'),
            CarbonImmutable::parse('2026-09-17'),
        );

        $jours = $lignes[0]['jours'];
        $this->assertSame(StatutPresence::MiseAPied, $jours['2026-09-14']['statut']);
        $this->assertSame(StatutPresence::MiseAPied, $jours['2026-09-16']['statut']);
        $this->assertSame(StatutPresence::Absence, $jours['2026-09-17']['statut']);
        $this->assertSame(3, $lignes[0]['totaux']['mise_a_pied']);
        $this->assertSame(1, $lignes[0]['totaux']['absence']);
    }

    public function test_un_fait_de_carriere_ne_porte_ni_periode_ni_solde(): void
    {
        Livewire::actingAs($this->creerCompteRh())
            ->test(Fiche::class, ['employe' => $this->employe])
            ->call('ajouterUnEvenement')
            ->set('evenementType', TypeEvenementEmploye::Promotion->value)
            ->set('evenementDebut', '2026-04-01')
            ->set('evenementFin', '2026-04-30')
            ->set('evenementAvecSolde', 'avec')
            ->set('evenementMotif', 'Nomination au poste de chef de service')
            ->call('enregistrerEvenement')
            ->assertHasNoErrors();

        $evenement = EvenementEmploye::sole();
        $this->assertNull($evenement->date_fin);
        $this->assertNull($evenement->avec_solde);
        $this->assertFalse($evenement->estSanction());
        $this->assertSame('01/04/2026', $evenement->periode());
    }

    public function test_le_motif_est_obligatoire_et_la_fin_ne_precede_pas_le_debut(): void
    {
        $composant = Livewire::actingAs($this->creerCompteRh())
            ->test(Fiche::class, ['employe' => $this->employe])
            ->call('ajouterUnEvenement')
            ->set('evenementType', TypeEvenementEmploye::MiseAPied->value)
            ->set('evenementDebut', '2026-09-14');

        $composant->set('evenementMotif', '')->call('enregistrerEvenement')->assertHasErrors(['evenementMotif']);

        $composant->set('evenementMotif', 'Motif valable')
            ->set('evenementFin', '2026-09-10')
            ->call('enregistrerEvenement')
            ->assertHasErrors(['evenementFin']);

        $this->assertSame(0, EvenementEmploye::count());
    }

    public function test_le_dossier_disciplinaire_suit_les_droits_des_donnees_sensibles(): void
    {
        $evenement = EvenementEmploye::factory()->for($this->employe)->miseAPied('2026-09-14', '2026-09-16')->create();

        // Le manager consulte la fiche de son équipe, mais pas son dossier disciplinaire.
        $manager = Employe::factory()->create();
        $departement = Departement::factory()->create(['responsable_id' => $manager->id]);
        $this->employe->update(['departement_id' => $departement->id]);
        $compteManager = User::factory()->create(['employe_id' => $manager->id]);

        Livewire::actingAs($compteManager)
            ->test(Fiche::class, ['employe' => $this->employe])
            ->assertSee('Moussa Sanctionne')
            ->assertDontSee('Discipline et parcours');

        Livewire::actingAs($compteManager)
            ->test(Fiche::class, ['employe' => $this->employe])
            ->call('supprimerEvenement', $evenement->id)
            ->assertForbidden();

        // L'agent n'ouvre même plus sa propre fiche : tout passe par le service.
        $sien = User::factory()->create(['employe_id' => $this->employe->id]);

        $this->actingAs($sien)->get(route('employes.fiche', $this->employe))->assertForbidden();
    }

    public function test_la_piece_de_la_decision_est_reservee_aux_habilites(): void
    {
        Storage::fake(EvenementEmploye::DISQUE);

        $chemin = UploadedFile::fake()->create('decision.pdf', 20, 'application/pdf')
            ->store(EvenementEmploye::DOSSIER, EvenementEmploye::DISQUE);
        $evenement = EvenementEmploye::factory()->for($this->employe)->create([
            'fichier_path' => $chemin, 'fichier_nom' => 'decision.pdf',
        ]);

        $this->actingAs($this->creerCompteRh())->get(route('employes.evenement.piece', $evenement))
            ->assertOk()
            ->assertDownload('decision.pdf');

        $intrus = User::factory()->create(['employe_id' => Employe::factory()->create()->id]);
        $this->actingAs($intrus)->get(route('employes.evenement.piece', $evenement))->assertForbidden();
    }

    public function test_retirer_un_evenement_supprime_sa_piece(): void
    {
        Storage::fake(EvenementEmploye::DISQUE);

        $chemin = UploadedFile::fake()->create('decision.pdf', 20, 'application/pdf')
            ->store(EvenementEmploye::DOSSIER, EvenementEmploye::DISQUE);
        $evenement = EvenementEmploye::factory()->for($this->employe)->create([
            'fichier_path' => $chemin, 'fichier_nom' => 'decision.pdf',
        ]);

        Livewire::actingAs($this->creerCompteRh())
            ->test(Fiche::class, ['employe' => $this->employe])
            ->call('supprimerEvenement', $evenement->id)
            ->assertSee('Événement retiré du dossier');

        $this->assertModelMissing($evenement);
        Storage::disk(EvenementEmploye::DISQUE)->assertMissing($chemin);
    }
}
