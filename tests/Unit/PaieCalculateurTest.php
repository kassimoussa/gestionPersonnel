<?php

namespace Tests\Unit;

use App\Services\Paie\PaieCalculateur;
use PHPUnit\Framework\TestCase;

class PaieCalculateurTest extends TestCase
{
    private PaieCalculateur $calculateur;

    protected function setUp(): void
    {
        parent::setUp();

        // Les taux réels de config/paie.php, chargés sans passer par le framework ni la base.
        $this->calculateur = new PaieCalculateur(require __DIR__.'/../../config/paie.php');
    }

    public function test_reproduit_l_exemple_de_reference_de_100_000_fd_en_cdi(): void
    {
        $montants = $this->calculateur->calculerDepuisMontants(100000, 0, 0);

        $this->assertEqualsWithDelta(100000, $montants->brut, 0.001);
        $this->assertEqualsWithDelta(6000, $montants->cnssSalarie, 0.001);
        $this->assertEqualsWithDelta(15700, $montants->cnssEmployeur, 0.001);
        $this->assertEqualsWithDelta(94000, $montants->baseImposable, 0.001);
        $this->assertEqualsWithDelta(11520, $montants->irpp, 0.001);
        $this->assertEqualsWithDelta(82480, $montants->netAPayer, 0.001);
        $this->assertEqualsWithDelta(115700, $montants->coutEmployeur(), 0.001);
        $this->assertFalse($montants->contratCourt);

        // 30 000 × 2 % + 20 000 × 15 % + 44 000 × 18 %
        $this->assertSame([600.0, 3000.0, 7920.0], array_column($montants->tranchesIrpp, 'montant'));
        $this->assertSame([30000.0, 20000.0, 44000.0], array_column($montants->tranchesIrpp, 'base'));
    }

    public function test_les_primes_et_indemnites_entrent_dans_le_brut_et_dans_l_assiette(): void
    {
        $montants = $this->calculateur->calculerDepuisMontants(100000, 10000, 5000);

        $this->assertEqualsWithDelta(115000, $montants->brut, 0.001);
        $this->assertEqualsWithDelta(6900, $montants->cnssSalarie, 0.001);
        $this->assertEqualsWithDelta(108100, $montants->baseImposable, 0.001);
        // 600 + 3 000 + 58 100 × 18 %
        $this->assertEqualsWithDelta(14058, $montants->irpp, 0.001);
        $this->assertEqualsWithDelta(94042, $montants->netAPayer, 0.001);
    }

    public function test_le_plancher_cnss_s_applique_sous_20_000_fd(): void
    {
        $montants = $this->calculateur->calculerDepuisMontants(15000, 0, 0);

        $this->assertEqualsWithDelta(20000, $montants->assietteCnss, 0.001);
        $this->assertEqualsWithDelta(1200, $montants->cnssSalarie, 0.001);
        $this->assertEqualsWithDelta(3140, $montants->cnssEmployeur, 0.001);
        $this->assertEqualsWithDelta(13800, $montants->baseImposable, 0.001);
        $this->assertEqualsWithDelta(276, $montants->irpp, 0.001);
        $this->assertEqualsWithDelta(13524, $montants->netAPayer, 0.001);
    }

    public function test_le_taux_forfaitaire_de_15_pour_cent_s_applique_a_un_contrat_court(): void
    {
        $montants = $this->calculateur->calculerDepuisMontants(100000, 0, 0, contratCourt: true);

        $this->assertTrue($montants->contratCourt);
        $this->assertEqualsWithDelta(6000, $montants->cnssSalarie, 0.001);
        $this->assertEqualsWithDelta(15000, $montants->irpp, 0.001);
        $this->assertEqualsWithDelta(79000, $montants->netAPayer, 0.001);
        $this->assertSame([], $montants->tranchesIrpp);
    }

    public function test_la_derniere_tranche_a_30_pour_cent_est_sans_limite(): void
    {
        $montants = $this->calculateur->calculerDepuisMontants(1000000, 0, 0);

        $this->assertEqualsWithDelta(940000, $montants->baseImposable, 0.001);
        // 600 + 3 000 + 18 000 + 90 000 + 340 000 × 30 %
        $this->assertEqualsWithDelta(213600, $montants->irpp, 0.001);
        $this->assertEqualsWithDelta(726400, $montants->netAPayer, 0.001);
        $this->assertCount(5, $montants->tranchesIrpp);
        $this->assertNull($montants->tranchesIrpp[4]['a']);
    }

    public function test_la_base_imposable_n_est_jamais_negative(): void
    {
        $montants = $this->calculateur->calculerDepuisMontants(500, 0, 0);

        $this->assertEqualsWithDelta(1200, $montants->cnssSalarie, 0.001);
        $this->assertEqualsWithDelta(0, $montants->baseImposable, 0.001);
        $this->assertEqualsWithDelta(0, $montants->irpp, 0.001);
        $this->assertEqualsWithDelta(0, $montants->netAPayer, 0.001);
    }

    public function test_detecte_un_contrat_de_moins_d_un_mois_civil(): void
    {
        $this->assertTrue($this->calculateur->estContratCourt('2026-09-01', '2026-09-20'));
        $this->assertTrue($this->calculateur->estContratCourt('2026-09-01', '2026-09-29'));
        $this->assertFalse($this->calculateur->estContratCourt('2026-09-01', '2026-09-30'));
        $this->assertTrue($this->calculateur->estContratCourt('2026-09-15', '2026-10-13'));
        $this->assertFalse($this->calculateur->estContratCourt('2026-09-15', '2026-10-14'));
        $this->assertFalse($this->calculateur->estContratCourt('2026-01-31', '2026-02-28'));
        $this->assertFalse($this->calculateur->estContratCourt('2025-01-01', null));
    }

    public function test_le_detail_cnss_par_branche_somme_aux_totaux(): void
    {
        $detail = $this->calculateur->detailCnss(100000);

        $this->assertEqualsWithDelta(100000, $detail['assiette'], 0.001);
        $this->assertEqualsWithDelta(4000, $detail['salarie']['retraite']['montant'], 0.001);
        $this->assertEqualsWithDelta(2000, $detail['salarie']['soins']['montant'], 0.001);
        $this->assertEqualsWithDelta(5500, $detail['employeur']['prestations_familiales']['montant'], 0.001);
        $this->assertEqualsWithDelta(5000, $detail['employeur']['soins']['montant'], 0.001);
        $this->assertEqualsWithDelta(1200, $detail['employeur']['accident_travail']['montant'], 0.001);
        $this->assertEqualsWithDelta(4000, $detail['employeur']['retraite']['montant'], 0.001);
        $this->assertSame('Prestations familiales', $detail['employeur']['prestations_familiales']['libelle']);

        $this->assertEqualsWithDelta(6000, array_sum(array_column($detail['salarie'], 'montant')), 0.001);
        $this->assertEqualsWithDelta(15700, array_sum(array_column($detail['employeur'], 'montant')), 0.001);
    }
}
