<?php

namespace Tests\Feature;

use App\Livewire\Employe\Index as EmployeIndex;
use App\Models\Conge;
use App\Models\Employe;
use App\Models\SoldeCongeAnnuel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Rattrapage des crédits mensuels manqués, et correction à la main d'un compteur
 * depuis le dossier de l'employé.
 */
class RegulariserSoldesCongesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Septembre : neuf mois de droits acquis depuis janvier.
        $this->travelTo('2026-09-20 09:00:00');
    }

    public function test_un_solde_en_retard_est_complete_jusqu_au_mois_en_cours(): void
    {
        $employe = Employe::factory()->create(['prenom' => 'Kassim', 'nom' => 'Moussa', 'date_embauche' => '2025-01-15']);
        SoldeCongeAnnuel::factory()->for($employe)->create([
            'annee' => 2026, 'jours_acquis' => 2.5, 'dernier_mois_accumule' => '2026-09',
        ]);

        $this->artisan('conges:regulariser-soldes')
            ->expectsOutputToContain('1 solde(s) régularisé(s)')
            ->assertSuccessful();

        $this->assertEquals(22.5, $employe->fresh()->joursCongeAcquis(2026));
    }

    public function test_un_employe_embauche_en_cours_d_annee_ne_compte_qu_a_partir_de_son_arrivee(): void
    {
        $employe = Employe::factory()->create(['date_embauche' => '2026-03-10']);

        $this->artisan('conges:regulariser-soldes')->assertSuccessful();

        // Mars à septembre : sept mois.
        $this->assertEquals(17.5, $employe->fresh()->joursCongeAcquis(2026));
    }

    public function test_un_solde_deja_a_jour_ou_superieur_n_est_pas_touche(): void
    {
        $employe = Employe::factory()->create(['date_embauche' => '2020-01-01']);
        SoldeCongeAnnuel::factory()->for($employe)->create(['annee' => 2026, 'jours_acquis' => 30]);

        $this->artisan('conges:regulariser-soldes')
            ->expectsOutputToContain('0 solde(s) régularisé(s)')
            ->assertSuccessful();

        $this->assertEquals(30, $employe->fresh()->joursCongeAcquis(2026));
    }

    public function test_les_employes_inactifs_sont_ignores_et_la_simulation_n_ecrit_rien(): void
    {
        $inactif = Employe::factory()->inactif()->create(['date_embauche' => '2020-01-01']);
        $actif = Employe::factory()->create(['date_embauche' => '2020-01-01']);

        $this->artisan('conges:regulariser-soldes', ['--simulation' => true])
            ->expectsOutputToContain('1 solde(s) à régulariser (simulation)')
            ->assertSuccessful();

        $this->assertEquals(0, $actif->fresh()->joursCongeAcquis(2026));

        $this->artisan('conges:regulariser-soldes')->assertSuccessful();

        $this->assertEquals(22.5, $actif->fresh()->joursCongeAcquis(2026));
        $this->assertEquals(0, $inactif->fresh()->joursCongeAcquis(2026));
    }

    public function test_le_rattrapage_n_empeche_pas_le_credit_du_mois_suivant(): void
    {
        $employe = Employe::factory()->create(['date_embauche' => '2020-01-01']);

        $this->artisan('conges:regulariser-soldes')->assertSuccessful();
        $this->artisan('conges:accumuler-soldes')->assertSuccessful();

        // Le mois de septembre est déjà compté dans le rattrapage : rien ne s'ajoute.
        $this->assertEquals(22.5, $employe->fresh()->joursCongeAcquis(2026));

        $this->artisan('conges:accumuler-soldes', ['--mois' => '2026-10'])->assertSuccessful();

        $this->assertEquals(25, $employe->fresh()->joursCongeAcquis(2026));
    }

    public function test_le_solde_se_corrige_a_la_main_depuis_le_dossier_de_l_employe(): void
    {
        $employe = Employe::factory()->create(['prenom' => 'Fatouma', 'nom' => 'Ali', 'date_embauche' => '2020-01-01']);
        SoldeCongeAnnuel::factory()->for($employe)->create(['annee' => 2026, 'jours_acquis' => 2.5]);

        Livewire::actingAs($this->creerCompteRh())
            ->test(EmployeIndex::class)
            ->call('editer', $employe->id)
            ->assertSee('Jours acquis en 2026')
            ->assertSet('jours_conge_acquis', '2,5')
            ->set('jours_conge_acquis', '22,5')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertEquals(22.5, $employe->fresh()->joursCongeAcquis(2026));

        // Le mois en cours est marqué comme pris en compte : pas de double crédit.
        $this->artisan('conges:accumuler-soldes')->assertSuccessful();
        $this->assertEquals(22.5, $employe->fresh()->joursCongeAcquis(2026));
    }

    public function test_le_solde_saisi_a_la_main_est_un_nombre_positif(): void
    {
        $employe = Employe::factory()->create();

        Livewire::actingAs($this->creerCompteRh())
            ->test(EmployeIndex::class)
            ->call('editer', $employe->id)
            ->set('jours_conge_acquis', 'beaucoup')
            ->call('enregistrer')
            ->assertHasErrors(['jours_conge_acquis']);

        Livewire::actingAs($this->creerCompteRh())
            ->test(EmployeIndex::class)
            ->call('editer', $employe->id)
            ->set('jours_conge_acquis', '-3')
            ->call('enregistrer')
            ->assertHasErrors(['jours_conge_acquis']);

        $this->assertEquals(0, $employe->fresh()->joursCongeAcquis(2026));
    }

    public function test_le_solde_corrige_se_reflete_dans_le_disponible_de_l_employe(): void
    {
        $employe = Employe::factory()->create(['date_embauche' => '2020-01-01']);
        Conge::factory()->for($employe)->acte()->create([
            'date_debut' => '2026-08-02', 'date_fin' => '2026-08-11', 'nombre_jours' => 10,
        ]);

        $this->artisan('conges:regulariser-soldes')->assertSuccessful();

        $employe->refresh();
        $this->assertEquals(22.5, $employe->joursCongeAcquis(2026));
        $this->assertEquals(12.5, $employe->soldeCongeRestant(2026));
        $this->assertEquals(12.5, $employe->soldeCongeDisponible(2026));
    }
}
