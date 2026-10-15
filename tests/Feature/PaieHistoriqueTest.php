<?php

namespace Tests\Feature;

use App\Livewire\Paie\Historique;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\FichePaie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaieHistoriqueTest extends TestCase
{
    use RefreshDatabase;

    private User $dg;

    private Departement $finance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-14 10:00:00');

        $this->dg = $this->creerCompteDg();

        $this->finance = Departement::factory()->create(['nom' => 'Finance']);
        $awa = Employe::factory()->create(['prenom' => 'Awa', 'nom' => 'Finance', 'departement_id' => $this->finance->id]);
        $hodan = Employe::factory()->create(['prenom' => 'Hodan', 'nom' => 'Sansdept']);

        // Septembre : Awa validée + Hodan brouillon ; août : Awa validée. Chaque fiche : brut 100 000, coût 115 700.
        FichePaie::factory()->for($awa)->validee()->create(['mois' => 9]);
        FichePaie::factory()->for($hodan)->create(['mois' => 9]);
        FichePaie::factory()->for($awa)->validee()->create(['mois' => 8]);
    }

    public function test_l_historique_est_reserve_a_la_dg(): void
    {
        $this->actingAs(User::factory()->create())->get('/paie/historique')->assertForbidden();
        $this->actingAs($this->dg)->get('/paie/historique')->assertOk()->assertSeeLivewire(Historique::class);
    }

    public function test_les_totaux_suivent_les_filtres_mois_departement_et_statut(): void
    {
        $composant = Livewire::actingAs($this->dg)
            ->test(Historique::class)
            ->set('annee', 2026)
            ->set('mois', 9)
            ->assertSee('Awa Finance')
            ->assertSee('Hodan Sansdept')
            ->assertViewHas('totaux', fn ($totaux) => $totaux['nombre'] === 2
                && abs($totaux['brut'] - 200000) < 0.01
                && abs($totaux['cout_employeur'] - 231400) < 0.01
                && abs($totaux['net'] - 164960) < 0.01)
            ->assertSee('200 000 FD')
            ->assertSee('231 400 FD');

        $composant->set('departement_id', $this->finance->id)
            ->assertDontSee('Hodan Sansdept')
            ->assertViewHas('totaux', fn ($totaux) => $totaux['nombre'] === 1 && abs($totaux['brut'] - 100000) < 0.01);

        $composant->set('departement_id', null)
            ->set('mois', null)
            ->assertSee('Année 2026')
            ->assertViewHas('totaux', fn ($totaux) => $totaux['nombre'] === 3 && abs($totaux['brut'] - 300000) < 0.01);

        $composant->set('mois', 9)
            ->set('statut', 'validee')
            ->assertViewHas('totaux', fn ($totaux) => $totaux['nombre'] === 1)
            ->assertDontSee('Hodan Sansdept');
    }
}
