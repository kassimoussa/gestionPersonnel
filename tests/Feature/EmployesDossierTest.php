<?php

namespace Tests\Feature;

use App\Livewire\Employe\Index;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EmployesDossierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-16 10:00:00');
    }

    public function test_le_dossier_complet_et_la_photo_sont_enregistres(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->creerCompteAdmin())
            ->test(Index::class)
            ->call('nouveau')
            ->assertSee('Date de naissance')
            ->assertSee('Lieu de naissance')
            ->assertSee('Photo')
            ->set('prenom', 'Fatouma')
            ->set('nom', 'Ali')
            ->set('matricule', 'ANF-0042')
            ->set('sexe', 'F')
            ->set('date_naissance', '1990-04-12')
            ->set('lieu_naissance', 'Djibouti')
            ->set('nationalite', 'Djiboutienne')
            ->set('situation_familiale', 'marie')
            ->set('nombre_enfants', '2')
            ->set('email', 'fatouma@anefip.dj')
            ->set('telephone', '+253 77 00 00 00')
            ->set('adresse', 'Quartier 7')
            ->set('contact_urgence_nom', 'Ali Moussa (frère)')
            ->set('contact_urgence_telephone', '+253 77 11 11 11')
            ->set('poste', 'Chargée RH')
            ->set('categorie', 'Cadre')
            ->set('date_embauche', '2025-02-01')
            ->set('numero_cnss', 'CNSS-123456')
            ->set('photo', UploadedFile::fake()->image('fatouma.png', 300, 300))
            ->call('enregistrer')
            ->assertHasNoErrors();

        $employe = Employe::where('email', 'fatouma@anefip.dj')->sole();
        $this->assertSame('ANF-0042', $employe->matricule);
        $this->assertSame('F', $employe->sexe);
        $this->assertSame('1990-04-12', $employe->date_naissance->toDateString());
        $this->assertSame(36, $employe->age());
        $this->assertSame('Djibouti', $employe->lieu_naissance);
        $this->assertSame('marie', $employe->situation_familiale);
        $this->assertSame(2, $employe->nombre_enfants);
        $this->assertSame('Ali Moussa (frère)', $employe->contact_urgence_nom);
        $this->assertSame('Cadre', $employe->categorie);
        $this->assertSame('CNSS-123456', $employe->numero_cnss);
        Storage::disk('public')->assertExists($employe->photo_path);

        Livewire::actingAs($this->creerCompteRh())
            ->test(Index::class)
            ->assertSeeHtml('src="'.$employe->photoUrl().'"')
            ->assertSee('ANF-0042');
    }

    public function test_la_date_de_naissance_doit_etre_passee_et_la_photo_une_image(): void
    {
        $composant = Livewire::actingAs($this->creerCompteRh())
            ->test(Index::class)
            ->call('nouveau')
            ->set('prenom', 'Test')
            ->set('nom', 'Test')
            ->set('email', 'test@anefip.dj')
            ->set('poste', 'Agent')
            ->set('date_embauche', '2026-01-01');

        $composant->set('date_naissance', '2030-01-01')->call('enregistrer')->assertHasErrors(['date_naissance']);
        $composant->set('date_naissance', '')->set('photo', UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'))
            ->call('enregistrer')->assertHasErrors(['photo']);

        $this->assertSame(0, Employe::count());
    }

    public function test_les_donnees_sensibles_sont_reservees_aux_habilites(): void
    {
        $manager = Employe::factory()->create();
        $departement = Departement::factory()->create(['responsable_id' => $manager->id]);
        $employe = Employe::factory()->create([
            'departement_id' => $departement->id,
            'numero_piece_identite' => 'PI-001',
            'banque' => 'Banque Exemple',
            'numero_compte_bancaire' => 'DJ00-1111',
        ]);

        // Le manager consulte son équipe sans voir les données sensibles ni pouvoir les écrire.
        $compteManager = User::factory()->create(['employe_id' => $manager->id]);

        Livewire::actingAs($compteManager)
            ->test(Index::class)
            ->assertDontSee('Administratif et paie')
            ->assertDontSee('DJ00-1111')
            ->assertDontSee('PI-001')
            ->call('editer', $employe->id)
            ->assertForbidden();

        Livewire::actingAs($compteManager)
            ->test(Index::class)
            ->set('editId', $employe->id)
            ->set('numero_compte_bancaire', 'PIRATE')
            ->call('enregistrer')
            ->assertForbidden();

        $this->assertSame('DJ00-1111', $employe->fresh()->numero_compte_bancaire);

        // La DG voit et modifie ces données.
        Livewire::actingAs($this->creerCompteDg())
            ->test(Index::class)
            ->call('editer', $employe->id)
            ->assertSee('Administratif et paie')
            ->assertSet('numero_compte_bancaire', 'DJ00-1111')
            ->set('banque', 'Autre banque')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame('Autre banque', $employe->fresh()->banque);
        $this->assertSame('PI-001', $employe->fresh()->numero_piece_identite);
    }

    public function test_la_photo_peut_etre_retiree(): void
    {
        Storage::fake('public');
        $chemin = UploadedFile::fake()->image('photo.png')->store('photos-employes', 'public');
        $employe = Employe::factory()->create(['photo_path' => $chemin]);

        Livewire::actingAs($this->creerCompteRh())
            ->test(Index::class)
            ->call('editer', $employe->id)
            ->set('retirerPhoto', true)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertNull($employe->fresh()->photo_path);
        Storage::disk('public')->assertMissing($chemin);
    }
}
