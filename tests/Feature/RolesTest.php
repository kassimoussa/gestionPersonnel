<?php

namespace Tests\Feature;

use App\Livewire\Contrat\Index as ContratIndex;
use App\Livewire\Employe\Index as EmployeIndex;
use App\Livewire\Paie\Generation as PaieGeneration;
use App\Models\Contrat;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\FichePaie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-16 10:00:00');
    }

    public function test_un_employe_sans_role_n_accede_ni_aux_dossiers_ni_aux_contrats(): void
    {
        $compte = User::factory()->create(['employe_id' => Employe::factory()->create()->id]);

        $this->actingAs($compte)->get('/employes')->assertForbidden();
        $this->actingAs($compte)->get('/contrats')->assertForbidden();
        $this->actingAs($compte)->get('/paie/generation')->assertForbidden();

        // Les liens correspondants disparaissent de la navigation.
        $this->actingAs($compte)->get('/dashboard')
            ->assertOk()
            ->assertDontSeeHtml(route('employes.index'))
            ->assertDontSeeHtml(route('contrats.index'));
    }

    public function test_un_manager_consulte_son_equipe_en_lecture_seule(): void
    {
        $manager = Employe::factory()->create();
        $departement = Departement::factory()->create(['responsable_id' => $manager->id]);
        $membre = Employe::factory()->create(['prenom' => 'Moussa', 'nom' => 'Equipe', 'departement_id' => $departement->id]);
        Employe::factory()->create(['prenom' => 'Hodan', 'nom' => 'Ailleurs']);
        $compte = User::factory()->create(['employe_id' => $manager->id]);

        Livewire::actingAs($compte)
            ->test(EmployeIndex::class)
            ->assertSee('Moussa Equipe')
            ->assertDontSee('Hodan Ailleurs')
            ->assertSee('Leur modification revient au service RH')
            ->assertDontSee('Ajouter un employé')
            ->assertDontSeeHtml("editer({$membre->id})")
            ->call('nouveau')
            ->assertForbidden();

        Livewire::actingAs($compte)->test(EmployeIndex::class)->call('basculerActivite', $membre->id)->assertForbidden();

        // Les contrats portent les salaires : pas d'accès pour un manager.
        $this->actingAs($compte)->get('/contrats')->assertForbidden();
    }

    public function test_le_rh_gere_les_dossiers_les_contrats_et_les_donnees_sensibles(): void
    {
        $rh = $this->creerCompteRh();
        $employe = Employe::factory()->create(['numero_cnss' => 'CNSS-123']);

        Livewire::actingAs($rh)
            ->test(EmployeIndex::class)
            ->assertSee('Ajouter un employé')
            ->call('editer', $employe->id)
            ->assertSee('Administratif et paie')
            ->assertSet('numero_cnss', 'CNSS-123')
            ->set('poste', 'Nouveau poste')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame('Nouveau poste', $employe->fresh()->poste);

        Livewire::actingAs($rh)
            ->test(ContratIndex::class)
            ->call('nouveau')
            ->set('employe_id', $employe->id)
            ->set('type', 'CDI')
            ->set('date_debut', '2026-01-01')
            ->set('salaire_base', '120000')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame(1, Contrat::count());
    }

    public function test_le_rh_prepare_la_paie_mais_seul_le_dg_valide(): void
    {
        $rh = $this->creerCompteRh();
        $employe = Employe::factory()->create();
        Contrat::factory()->for($employe)->create();

        Livewire::actingAs($rh)
            ->test(PaieGeneration::class)
            ->set('annee', 2026)
            ->set('mois', 9)
            ->call('generer')
            ->assertHasNoErrors()
            ->assertSee('Leur validation revient au directeur général')
            ->assertDontSee('Valider toutes les fiches brouillon');

        $fiche = FichePaie::sole();

        Livewire::actingAs($rh)->test(PaieGeneration::class)->call('valider', $fiche->id)->assertForbidden();
        Livewire::actingAs($rh)->test(PaieGeneration::class)->call('validerTout')->assertForbidden();
        $this->assertFalse($fiche->fresh()->estValidee());

        Livewire::actingAs($this->creerCompteDg())
            ->test(PaieGeneration::class)
            ->set('annee', 2026)
            ->set('mois', 9)
            ->call('valider', $fiche->id);

        $this->assertTrue($fiche->fresh()->estValidee());

        // L'administrateur a tous les droits, y compris sur la paie.
        $this->actingAs($this->creerCompteAdmin())->get('/paie/generation')->assertOk();
    }

    public function test_le_compte_d_un_employe_desactive_ne_peut_plus_se_connecter(): void
    {
        $employe = Employe::factory()->create();
        $compte = User::factory()->create(['employe_id' => $employe->id, 'email' => 'parti@anefip.dj']);

        // Session déjà ouverte : elle est coupée dès la désactivation.
        $this->actingAs($compte)->get('/dashboard')->assertOk();
        $employe->update(['actif' => false]);
        $this->actingAs($compte->fresh())->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();

        Volt::test('pages.auth.login')
            ->set('form.email', 'parti@anefip.dj')
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors('form.email');

        $this->assertGuest();

        // Réactivé, le compte fonctionne de nouveau.
        $employe->update(['actif' => true]);
        $this->actingAs($compte->fresh())->get('/dashboard')->assertOk();
    }
}
