<?php

namespace Tests\Feature;

use App\Models\Employe;
use App\Models\SoldeCongeAnnuel;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccumulerSoldesCongesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-13 09:00:00');
    }

    public function test_credite_les_employes_actifs_pour_le_mois_en_cours(): void
    {
        $actifs = Employe::factory()->count(2)->create();
        $inactif = Employe::factory()->inactif()->create();

        $this->artisan('conges:accumuler-soldes')->assertSuccessful();

        foreach ($actifs as $employe) {
            $solde = SoldeCongeAnnuel::where('employe_id', $employe->id)->where('annee', 2026)->sole();
            $this->assertEquals(2.5, $solde->jours_acquis);
            $this->assertSame('2026-09', $solde->dernier_mois_accumule);
            $this->assertEquals(2.5, $employe->soldeCongeRestant());
        }

        $this->assertDatabaseMissing('soldes_conges_annuels', ['employe_id' => $inactif->id]);
    }

    public function test_ne_credite_pas_deux_fois_le_meme_mois_sauf_si_force(): void
    {
        $employe = Employe::factory()->create();

        $this->artisan('conges:accumuler-soldes')->assertSuccessful();
        $this->artisan('conges:accumuler-soldes')
            ->expectsOutputToContain('1 ignoré(s)')
            ->assertSuccessful();
        $this->assertEquals(2.5, $employe->soldeCongeRestant());

        $this->artisan('conges:accumuler-soldes', ['--forcer' => true])->assertSuccessful();
        $this->assertEquals(5, $employe->soldeCongeRestant());
    }

    public function test_cumule_les_mois_successifs_et_ignore_un_mois_deja_depasse(): void
    {
        $employe = Employe::factory()->create();

        $this->artisan('conges:accumuler-soldes', ['--mois' => '2026-07'])->assertSuccessful();
        $this->artisan('conges:accumuler-soldes', ['--mois' => '2026-08'])->assertSuccessful();
        $this->assertEquals(5, $employe->soldeCongeRestant());

        $this->artisan('conges:accumuler-soldes', ['--mois' => '2026-07'])->assertSuccessful();
        $this->assertEquals(5, $employe->soldeCongeRestant());
    }

    public function test_refuse_un_mois_mal_forme(): void
    {
        $this->artisan('conges:accumuler-soldes', ['--mois' => '13-2026'])->assertFailed();
    }

    public function test_la_commande_est_planifiee_le_premier_de_chaque_mois(): void
    {
        // Exécuter une commande Artisan charge routes/console.php, donc le planificateur.
        $this->artisan('schedule:list')
            ->expectsOutputToContain('conges:accumuler-soldes')
            ->assertSuccessful();

        $evenements = collect(app(Schedule::class)->events())
            ->filter(fn (Event $evenement) => str_contains((string) $evenement->command, 'conges:accumuler-soldes'));

        $this->assertCount(1, $evenements);
        $this->assertSame('0 0 1 * *', $evenements->first()->expression);
    }
}
