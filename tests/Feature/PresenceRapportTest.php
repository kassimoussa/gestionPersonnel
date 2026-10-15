<?php

namespace Tests\Feature;

use App\Enums\StatutPresence;
use App\Livewire\Presence\Rapport;
use App\Models\Conge;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\Pointage;
use App\Models\User;
use App\Services\Pointages\RapportPresence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PresenceRapportTest extends TestCase
{
    use RefreshDatabase;

    private User $compteManager;

    private Departement $departement;

    private Employe $employe;

    private Employe $autreEmploye;

    protected function setUp(): void
    {
        parent::setUp();

        // Mercredi 16 septembre 2026 ; weekend configuré : vendredi-samedi.
        $this->travelTo('2026-09-16 12:00:00');

        $manager = Employe::factory()->create();
        $this->departement = Departement::factory()->create(['nom' => 'Comptabilité', 'responsable_id' => $manager->id]);
        $this->employe = Employe::factory()->create([
            'prenom' => 'Moussa', 'nom' => 'Demandeur', 'departement_id' => $this->departement->id, 'date_embauche' => '2026-01-05',
        ]);
        $this->autreEmploye = Employe::factory()->create([
            'prenom' => 'Hodan', 'nom' => 'Ailleurs', 'departement_id' => Departement::factory()->create(['nom' => 'Logistique'])->id,
        ]);
        $this->compteManager = User::factory()->create(['employe_id' => $manager->id]);
    }

    public function test_le_statut_de_chaque_jour_croise_pointages_conges_et_calendrier(): void
    {
        // Dimanche : arrivée dans la tolérance (08:00 + 15 min)
        Pointage::factory()->for($this->employe)->create(['date' => '2026-09-13', 'heure_arrivee' => '08:15:00']);
        // Lundi : arrivée à 08:30, en retard
        Pointage::factory()->for($this->employe)->enRetard()->create(['date' => '2026-09-14']);
        // Mardi : congé approuvé
        Conge::factory()->for($this->employe)->acte()->create(['date_debut' => '2026-09-15', 'date_fin' => '2026-09-15', 'nombre_jours' => 1]);

        $rapport = app(RapportPresence::class)->generer(
            collect([$this->employe]),
            CarbonImmutable::parse('2026-09-13'),
            CarbonImmutable::parse('2026-09-19'),
        );

        $jours = $rapport[0]['jours'];
        $this->assertSame(StatutPresence::Present, $jours['2026-09-13']['statut']);
        $this->assertSame(StatutPresence::Retard, $jours['2026-09-14']['statut']);
        $this->assertSame(StatutPresence::Conge, $jours['2026-09-15']['statut']);
        $this->assertSame(StatutPresence::EnCours, $jours['2026-09-16']['statut']);
        $this->assertSame(StatutPresence::NonApplicable, $jours['2026-09-17']['statut']);
        $this->assertSame(StatutPresence::Weekend, $jours['2026-09-18']['statut']);
        $this->assertSame(StatutPresence::Weekend, $jours['2026-09-19']['statut']);
        $this->assertSame(
            ['present' => 1, 'retard' => 1, 'conge' => 1, 'mise_a_pied' => 0, 'absence' => 0, 'absence_justifiee' => 0],
            $rapport[0]['totaux'],
        );
    }

    public function test_le_jour_courant_sans_pointage_est_en_cours_et_les_jours_passes_restent_des_absences(): void
    {
        $rapport = app(RapportPresence::class);
        $periode = [CarbonImmutable::parse('2026-09-14'), CarbonImmutable::parse('2026-09-16')];

        $jours = $rapport->generer(collect([$this->employe]), ...$periode)[0]['jours'];
        $this->assertSame(StatutPresence::Absence, $jours['2026-09-14']['statut']);
        $this->assertSame(StatutPresence::Absence, $jours['2026-09-15']['statut']);
        $this->assertSame(StatutPresence::EnCours, $jours['2026-09-16']['statut']);

        $totaux = $rapport->generer(collect([$this->employe]), ...$periode)[0]['totaux'];
        $this->assertSame(2, $totaux['absence']);
        $this->assertArrayNotHasKey('en_cours', $totaux);

        // Un pointage enregistré plus tard dans la journée donne le statut habituel.
        $pointage = Pointage::factory()->for($this->employe)->create(['date' => '2026-09-16', 'heure_arrivee' => '10:30:00', 'heure_depart' => null]);
        $this->assertSame(StatutPresence::Retard, $rapport->generer(collect([$this->employe]), ...$periode)[0]['jours']['2026-09-16']['statut']);

        $pointage->update(['heure_arrivee' => '08:10:00']);
        $this->assertSame(StatutPresence::Present, $rapport->generer(collect([$this->employe]), ...$periode)[0]['jours']['2026-09-16']['statut']);
    }

    public function test_le_jour_courant_reste_weekend_ou_conge_quand_c_est_le_cas(): void
    {
        $rapport = app(RapportPresence::class);

        Conge::factory()->for($this->employe)->acte()->create(['date_debut' => '2026-09-16', 'date_fin' => '2026-09-16', 'nombre_jours' => 1]);
        $jour = CarbonImmutable::parse('2026-09-16');
        $this->assertSame(StatutPresence::Conge, $rapport->generer(collect([$this->employe]), $jour, $jour)[0]['jours']['2026-09-16']['statut']);

        // Vendredi 18 septembre 2026 : jour de weekend configuré.
        $this->travelTo('2026-09-18 12:00:00');
        $jour = CarbonImmutable::parse('2026-09-18');
        $this->assertSame(StatutPresence::Weekend, $rapport->generer(collect([$this->employe]), $jour, $jour)[0]['jours']['2026-09-18']['statut']);
    }

    public function test_la_legende_du_rapport_affiche_le_statut_en_cours(): void
    {
        Livewire::actingAs($this->compteManager)
            ->test(Rapport::class)
            ->assertSee('En cours (jour courant, pas encore de pointage)');
    }

    public function test_un_jour_avant_l_embauche_n_est_pas_une_absence(): void
    {
        $rapport = app(RapportPresence::class)->generer(
            collect([$this->employe]),
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-05'),
        );

        $jours = $rapport[0]['jours'];
        // Le 1er janvier est férié : comme le weekend, il prime sur « hors période ».
        $this->assertSame(StatutPresence::Ferie, $jours['2026-01-01']['statut']);
        $this->assertSame(StatutPresence::Weekend, $jours['2026-01-02']['statut']);
        $this->assertSame(StatutPresence::NonApplicable, $jours['2026-01-04']['statut']);
        $this->assertSame(StatutPresence::Absence, $jours['2026-01-05']['statut']);
    }

    public function test_le_manager_est_limite_a_son_departement(): void
    {
        $this->actingAs($this->compteManager)->get('/presence/rapport')->assertOk()->assertSeeLivewire(Rapport::class);

        Livewire::actingAs($this->compteManager)
            ->test(Rapport::class)
            ->assertSet('departement_id', $this->departement->id)
            ->assertSee('Moussa Demandeur')
            ->assertSee('Comptabilité')
            ->assertDontSee('Hodan Ailleurs')
            ->assertDontSee('Logistique')
            ->set('departement_id', $this->autreEmploye->departement_id)
            ->assertSet('departement_id', null)
            ->assertDontSee('Hodan Ailleurs');
    }

    public function test_la_dg_voit_tout_le_monde_et_filtre_par_departement(): void
    {
        $composant = Livewire::actingAs($this->compteDg())
            ->test(Rapport::class)
            ->assertSet('departement_id', null)
            ->assertSee('Moussa Demandeur')
            ->assertSee('Hodan Ailleurs');

        $composant->set('departement_id', $this->departement->id)
            ->assertSee('Moussa Demandeur')
            ->assertDontSee('Hodan Ailleurs');
    }

    public function test_une_periode_invalide_affiche_un_message(): void
    {
        Livewire::actingAs($this->compteManager)
            ->test(Rapport::class)
            ->set('date_debut', '2026-09-10')
            ->set('date_fin', '2026-09-01')
            ->assertSee('La date de fin doit être postérieure')
            ->set('date_fin', '2026-12-31')
            ->assertSee('ne peut pas dépasser 92 jours');
    }

    public function test_l_acces_est_refuse_aux_comptes_sans_equipe_ni_role(): void
    {
        $this->actingAs(User::factory()->create())->get('/presence/rapport')->assertForbidden();
    }

    private function compteDg(): User
    {
        $dg = $this->creerCompteDg();

        return $dg;
    }
}
