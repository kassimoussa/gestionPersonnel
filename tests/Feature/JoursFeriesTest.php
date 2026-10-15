<?php

namespace Tests\Feature;

use App\Enums\StatutPresence;
use App\Livewire\Parametres\JoursFeries;
use App\Models\Employe;
use App\Models\JourFerie;
use App\Models\User;
use App\Services\CalculJoursOuvres;
use App\Services\Pointages\RapportPresence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Jours fériés : ni décomptés des congés, ni comptés comme des absences.
 */
class JoursFeriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-20 09:00:00');
    }

    public function test_les_fetes_a_date_fixe_valent_pour_toutes_les_annees(): void
    {
        // La migration installe le 1er janvier, le 1er mai et le 27 juin.
        $calcul = app(CalculJoursOuvres::class);

        $this->assertTrue($calcul->estFerie('2026-06-27'));
        $this->assertTrue($calcul->estFerie('2030-06-27'));
        $this->assertSame("Fête de l'Indépendance", $calcul->libelleFerie('2027-06-27'));
    }

    public function test_une_fete_mobile_ne_vaut_que_pour_son_annee(): void
    {
        JourFerie::factory()->create(['date' => '2026-03-20', 'libelle' => 'Aïd el-Fitr']);

        $calcul = app(CalculJoursOuvres::class);

        $this->assertTrue($calcul->estFerie('2026-03-20'));
        $this->assertFalse($calcul->estFerie('2027-03-20'));
    }

    public function test_un_jour_ferie_n_est_pas_une_absence_dans_le_rapport(): void
    {
        $employe = Employe::factory()->create(['date_embauche' => '2020-01-01']);
        JourFerie::factory()->create(['date' => '2026-09-16', 'libelle' => 'Fête mobile']);

        $lignes = app(RapportPresence::class)->generer(
            collect([$employe]),
            CarbonImmutable::parse('2026-09-16'),
            CarbonImmutable::parse('2026-09-17'),
        );

        $this->assertSame(StatutPresence::Ferie, $lignes[0]['jours']['2026-09-16']['statut']);
        $this->assertSame(StatutPresence::Absence, $lignes[0]['jours']['2026-09-17']['statut']);
        $this->assertSame(1, $lignes[0]['totaux']['absence']);
    }

    public function test_l_administrateur_gere_le_calendrier(): void
    {
        $admin = $this->creerCompteAdmin();

        $this->actingAs($admin)->get('/parametres/jours-feries')
            ->assertOk()
            ->assertSeeLivewire(JoursFeries::class);

        Livewire::actingAs($admin)
            ->test(JoursFeries::class)
            ->assertSee("Fête de l'Indépendance")
            ->call('nouveau')
            ->set('date', '2026-03-20')
            ->set('libelle', 'Aïd el-Fitr')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSee('Aïd el-Fitr');

        $this->assertTrue(JourFerie::where('libelle', 'Aïd el-Fitr')->exists());
    }

    public function test_un_meme_jour_ne_peut_pas_etre_ferie_deux_fois(): void
    {
        JourFerie::factory()->create(['date' => '2026-03-20', 'libelle' => 'Aïd el-Fitr']);

        Livewire::actingAs($this->creerCompteAdmin())
            ->test(JoursFeries::class)
            ->call('nouveau')
            ->set('date', '2026-03-20')
            ->set('libelle', 'Doublon')
            ->call('enregistrer')
            ->assertHasErrors(['date']);

        // Une fête à date fixe déjà posée ne se redéclare pas sous une autre année.
        Livewire::actingAs($this->creerCompteAdmin())
            ->test(JoursFeries::class)
            ->call('nouveau')
            ->set('date', '2027-06-27')
            ->set('libelle', "Fête de l'Indépendance")
            ->set('recurrent', true)
            ->call('enregistrer')
            ->assertHasErrors(['date']);
    }

    public function test_le_calendrier_est_reserve_aux_parametres(): void
    {
        $this->get('/parametres/jours-feries')->assertRedirect(route('login'));

        $compte = User::factory()->create(['employe_id' => Employe::factory()->create()->id]);
        $this->actingAs($compte)->get('/parametres/jours-feries')->assertForbidden();
    }

    public function test_retirer_un_jour_ferie_le_rend_travaille(): void
    {
        $ferie = JourFerie::factory()->create(['date' => '2026-09-16', 'libelle' => 'Fête mobile']);

        $this->assertTrue(app(CalculJoursOuvres::class)->estFerie('2026-09-16'));

        Livewire::actingAs($this->creerCompteAdmin())
            ->test(JoursFeries::class)
            ->call('supprimer', $ferie->id)
            ->assertSee('retiré du calendrier');

        $this->assertModelMissing($ferie);
        $this->assertTrue((new CalculJoursOuvres([5, 6], JourFerie::pourLeCalcul()))->estOuvre('2026-09-16'));
    }
}
