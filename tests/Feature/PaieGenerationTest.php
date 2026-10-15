<?php

namespace Tests\Feature;

use App\Enums\StatutFichePaie;
use App\Livewire\Paie\Generation;
use App\Models\Contrat;
use App\Models\Employe;
use App\Models\FichePaie;
use App\Models\User;
use App\Services\Paie\AucunContratActif;
use App\Services\Paie\PaieCalculateur;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaieGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $dg;

    private Employe $awa;

    private Employe $ancien;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-14 10:00:00');

        $this->dg = $this->creerCompteDg();

        // Awa : CDI à 100 000 FD depuis 2025 (éligible)
        $this->awa = Employe::factory()->create(['prenom' => 'Awa', 'nom' => 'Cdi']);
        Contrat::factory()->for($this->awa)->create();

        // Bilan : contrat terminé fin 2025 (plus de contrat en vigueur en septembre 2026)
        $this->ancien = Employe::factory()->create(['prenom' => 'Bilan', 'nom' => 'Termine']);
        Contrat::factory()->for($this->ancien)->cdd('2025-01-01', '2025-12-31')->create();

        // Inactif sous CDI et actif sans contrat : ignorés
        Contrat::factory()->for(Employe::factory()->inactif()->create(['prenom' => 'Ina', 'nom' => 'Ctif']))->create();
        Employe::factory()->create(['prenom' => 'Sans', 'nom' => 'Contrat']);
    }

    public function test_la_generation_est_reservee_a_la_dg(): void
    {
        $this->actingAs(User::factory()->create())->get('/paie/generation')->assertForbidden();
        $this->actingAs($this->dg)->get('/paie/generation')->assertOk()->assertSeeLivewire(Generation::class);
    }

    public function test_la_generation_cree_un_brouillon_par_employe_actif_sous_contrat_sans_doublon(): void
    {
        $composant = Livewire::actingAs($this->dg)
            ->test(Generation::class)
            ->set('annee', 2026)
            ->set('mois', 9)
            ->call('generer')
            // Awa générée ; Bilan (contrat terminé), Sans Contrat et le DG (sans contrat) ignorés ; l'inactif n'est pas considéré.
            ->assertSee('1 brouillon(s) généré(s) pour Septembre 2026, 3 employé(s) actif(s) ignoré(s)');

        $fiche = FichePaie::sole();
        $this->assertSame($this->awa->id, $fiche->employe_id);
        $this->assertSame(StatutFichePaie::Brouillon, $fiche->statut);
        $this->assertEquals(100000, $fiche->salaire_base);
        $this->assertEquals(100000, $fiche->brut);
        $this->assertEquals(6000, $fiche->cnss_salarie);
        $this->assertEquals(15700, $fiche->cnss_employeur);
        $this->assertEquals(94000, $fiche->base_imposable);
        $this->assertEquals(11520, $fiche->irpp);
        $this->assertEquals(82480, $fiche->net_a_payer);

        $composant->call('generer')->assertSee('0 brouillon(s) généré(s)');

        $this->assertSame(1, FichePaie::count());
    }

    public function test_les_primes_et_indemnites_sont_ajustables_avant_validation(): void
    {
        $fiche = FichePaie::factory()->for($this->awa)->create();

        $composant = Livewire::actingAs($this->dg)->test(Generation::class)->set('annee', 2026)->set('mois', 9);

        $composant->set("primes.{$fiche->id}", '10000')
            ->set("indemnites.{$fiche->id}", '5000')
            ->call('mettreAJour', $fiche->id)
            ->assertHasNoErrors();

        $fiche->refresh();
        $this->assertEquals(100000, $fiche->salaire_base);
        $this->assertEquals(115000, $fiche->brut);
        $this->assertEquals(6900, $fiche->cnss_salarie);
        $this->assertEquals(108100, $fiche->base_imposable);
        $this->assertEquals(14058, $fiche->irpp);
        $this->assertEquals(94042, $fiche->net_a_payer);

        $composant->set("primes.{$fiche->id}", '-5')
            ->call('mettreAJour', $fiche->id)
            ->assertHasErrors(["primes.{$fiche->id}"]);

        $this->assertEquals(115000, $fiche->fresh()->brut);
    }

    public function test_la_validation_fige_la_fiche_individuellement_ou_en_masse(): void
    {
        $premiere = FichePaie::factory()->for($this->awa)->create();
        $seconde = FichePaie::factory()->for($this->ancien)->create();

        $composant = Livewire::actingAs($this->dg)->test(Generation::class)->set('annee', 2026)->set('mois', 9);

        $composant->call('valider', $premiere->id);

        $premiere->refresh();
        $this->assertSame(StatutFichePaie::Validee, $premiere->statut);
        $this->assertSame($this->dg->id, $premiere->validee_par_id);
        $this->assertSame('2026-09-14 10:00:00', $premiere->validee_le?->format('Y-m-d H:i:s'));
        $this->assertSame(StatutFichePaie::Brouillon, $seconde->fresh()->statut);

        Livewire::actingAs($this->dg)->test(Generation::class)->set('annee', 2026)->set('mois', 9)
            ->set("primes.{$premiere->id}", '1000')->call('mettreAJour', $premiere->id)->assertForbidden();
        Livewire::actingAs($this->dg)->test(Generation::class)->set('annee', 2026)->set('mois', 9)
            ->call('supprimer', $premiere->id)->assertForbidden();

        $this->assertEquals(0, $premiere->fresh()->primes);

        $composant->call('validerTout');

        $seconde->refresh();
        $this->assertSame(StatutFichePaie::Validee, $seconde->statut);
        $this->assertSame($this->dg->id, $seconde->validee_par_id);
        $this->assertNotNull($seconde->validee_le);
    }

    public function test_un_brouillon_peut_etre_supprime(): void
    {
        $fiche = FichePaie::factory()->for($this->awa)->create();

        Livewire::actingAs($this->dg)->test(Generation::class)->set('annee', 2026)->set('mois', 9)->call('supprimer', $fiche->id);

        $this->assertModelMissing($fiche);
    }

    public function test_l_unicite_employe_annee_mois_est_garantie_en_base(): void
    {
        FichePaie::factory()->for($this->awa)->create(['annee' => 2026, 'mois' => 9]);

        $this->expectException(UniqueConstraintViolationException::class);

        FichePaie::factory()->for($this->awa)->create(['annee' => 2026, 'mois' => 9]);
    }

    public function test_le_calcul_utilise_le_contrat_en_vigueur_sur_la_periode_et_le_forfait_pour_un_contrat_court(): void
    {
        $calculateur = app(PaieCalculateur::class);

        $court = Employe::factory()->create();
        Contrat::factory()->for($court)->cdd('2026-09-01', '2026-09-20')->create();
        $montantsCourt = $calculateur->calculer($court, 2026, 9, 0, 0);
        $this->assertTrue($montantsCourt->contratCourt);
        $this->assertEqualsWithDelta(15000, $montantsCourt->irpp, 0.001);
        $this->assertEqualsWithDelta(79000, $montantsCourt->netAPayer, 0.001);

        $moisEntier = Employe::factory()->create();
        Contrat::factory()->for($moisEntier)->cdd('2026-09-01', '2026-09-30')->create();
        $this->assertEqualsWithDelta(11520, $calculateur->calculer($moisEntier, 2026, 9, 0, 0)->irpp, 0.001);

        $this->assertEqualsWithDelta(82480, $calculateur->calculer($this->awa, 2026, 9, 0, 0)->netAPayer, 0.001);

        // Le contrat terminé fin 2025 reste utilisable pour une paie de 2025.
        $this->assertEqualsWithDelta(82480, $calculateur->calculer($this->ancien, 2025, 6, 0, 0)->netAPayer, 0.001);

        $this->expectException(AucunContratActif::class);
        $calculateur->calculer($this->ancien, 2026, 9, 0, 0);
    }
}
