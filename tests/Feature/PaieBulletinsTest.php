<?php

namespace Tests\Feature;

use App\Models\Employe;
use App\Models\FichePaie;
use App\Models\User;
use App\Services\Paie\PaieCalculateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaieBulletinsTest extends TestCase
{
    use RefreshDatabase;

    private User $dg;

    private User $compteA;

    private User $compteB;

    private FichePaie $valideeA;

    private FichePaie $brouillonA;

    private FichePaie $valideeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-14 10:00:00');

        $this->dg = $this->creerCompteDg();

        $employeA = Employe::factory()->create(['prenom' => 'Awa', 'nom' => 'Responsable']);
        $employeB = Employe::factory()->create(['prenom' => 'Hodan', 'nom' => 'Chef']);
        $this->compteA = User::factory()->create(['employe_id' => $employeA->id]);
        $this->compteB = User::factory()->create(['employe_id' => $employeB->id]);

        $this->valideeA = FichePaie::factory()->for($employeA)->validee()->create(['mois' => 9]);
        $this->brouillonA = FichePaie::factory()->for($employeA)->create(['mois' => 8, 'net_a_payer' => 70000]);
        $this->valideeB = FichePaie::factory()->for($employeB)->validee()->create(['mois' => 9, 'net_a_payer' => 60000]);
    }

    public function test_le_bulletin_est_reserve_aux_comptes_qui_preparent_la_paie(): void
    {
        $this->get(route('paie.bulletin', $this->valideeA))->assertRedirect(route('login'));

        // Un agent, même titulaire de la fiche, ne télécharge pas son bulletin :
        // il lui est remis par le service du personnel.
        $this->actingAs($this->compteA)->get(route('paie.bulletin', $this->valideeA))->assertForbidden();
        $this->actingAs($this->compteB)->get(route('paie.bulletin', $this->valideeA))->assertForbidden();

        // La direction et le service accèdent à toutes les fiches, brouillons compris.
        $reponse = $this->actingAs($this->dg)->get(route('paie.bulletin', $this->valideeA));
        $reponse->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('bulletin-2026-09-awa-responsable.pdf');
        $this->assertStringStartsWith('%PDF', $reponse->getContent());

        $this->actingAs($this->dg)->get(route('paie.bulletin', $this->brouillonA))->assertOk();
        $this->actingAs($this->creerCompteRh())->get(route('paie.bulletin', $this->valideeB))->assertOk();
    }

    public function test_le_bulletin_detaille_les_branches_cnss_et_les_tranches_irpp(): void
    {
        $calculateur = app(PaieCalculateur::class);

        $html = view('paie.bulletin', [
            'fiche' => $this->valideeA->load(['employe.departement', 'valideePar']),
            'detailCnss' => $calculateur->detailCnss((float) $this->valideeA->brut),
            'detailIrpp' => $calculateur->detailIrppPourFiche($this->valideeA),
            'genereLe' => now(),
        ])->render();

        foreach (['Awa Responsable', 'Septembre 2026', 'Prestations familiales', 'Soins de santé', 'Accident de travail', 'Retraite'] as $texte) {
            $this->assertStringContainsString($texte, $html);
        }

        // Branches : 4 000 (retraite salarié), 5 500 (prestations familiales) ; tranches : 600, 3 000, 7 920 ; totaux.
        foreach (['4 000 FD', '5 500 FD', '600 FD', '3 000 FD', '7 920 FD', '11 520 FD', '82 480 FD', '115 700 FD'] as $montant) {
            $this->assertStringContainsString($montant, $html);
        }
    }

    public function test_le_detail_irpp_est_indisponible_si_le_montant_fige_ne_correspond_plus(): void
    {
        $fiche = FichePaie::factory()->create(['irpp' => 999]);

        $this->assertSame('indisponible', app(PaieCalculateur::class)->detailIrppPourFiche($fiche)['mode']);
    }

    public function test_la_navigation_paie_n_apparait_que_pour_les_comptes_habilites(): void
    {
        $this->actingAs($this->compteA)->get('/dashboard')
            ->assertDontSee('Génération de la paie')
            ->assertDontSee('Historique de la paie');

        $this->actingAs($this->dg)->get('/dashboard')
            ->assertSee('Génération de la paie')
            ->assertSee('Historique de la paie');
    }
}
