<?php

namespace Tests\Unit;

use App\Services\CalculJoursOuvres;
use App\Services\Conges\CalculPeriodeConge;
use PHPUnit\Framework\TestCase;

class CalculPeriodeCongeTest extends TestCase
{
    private CalculPeriodeConge $calcul;

    protected function setUp(): void
    {
        parent::setUp();

        // Weekend de Djibouti : vendredi (5) et samedi (6). 2026-09-13 est un dimanche.
        // Le mercredi 16 septembre 2026 est déclaré férié pour les besoins du test.
        $this->calcul = new CalculPeriodeConge(new CalculJoursOuvres([5, 6], ['2026-09-16' => 'Fête mobile']));
    }

    public function test_le_conge_se_compte_en_jours_travailles(): void
    {
        // Du dimanche au jeudi : 5 jours calendaires, mais le mercredi est férié.
        $this->assertSame(4, $this->calcul->nombreJours('2026-09-13', '2026-09-17'));
        $this->assertSame(1, $this->calcul->nombreJours('2026-09-13', '2026-09-13'));
    }

    public function test_ni_le_weekend_ni_les_jours_feries_ne_sont_decomptes(): void
    {
        // Du dimanche au samedi suivant : 7 jours calendaires, dont vendredi, samedi
        // et un jour férié, soit 3 journées chômées.
        $this->assertSame(4, $this->calcul->nombreJours('2026-09-13', '2026-09-19'));
        $this->assertSame(7, $this->calcul->nombreJoursCalendaires('2026-09-13', '2026-09-19'));
    }

    public function test_un_jour_ferie_isole_ne_decompte_rien(): void
    {
        $this->assertSame(0, $this->calcul->nombreJours('2026-09-16', '2026-09-16'));
        $this->assertFalse($this->calcul->contientUnJourTravaille('2026-09-16', '2026-09-16'));
    }

    public function test_une_fin_anterieure_au_debut_ne_compte_aucun_jour(): void
    {
        $this->assertSame(0, $this->calcul->nombreJours('2026-09-17', '2026-09-13'));
        $this->assertSame(0, $this->calcul->nombreJoursCalendaires('2026-09-17', '2026-09-13'));
    }

    public function test_la_reprise_saute_le_weekend_et_les_jours_feries(): void
    {
        // Congé terminé le jeudi : reprise le dimanche, vendredi et samedi étant chômés.
        $this->assertSame('2026-09-20', $this->calcul->jourReprise('2026-09-17')->toDateString());

        // Congé terminé le dimanche : reprise dès le lundi.
        $this->assertSame('2026-09-21', $this->calcul->jourReprise('2026-09-20')->toDateString());

        // Congé terminé la veille d'un jour férié : la reprise l'enjambe.
        $this->assertSame('2026-09-17', $this->calcul->jourReprise('2026-09-15')->toDateString());
    }

    public function test_une_periode_entierement_chomee_ne_libere_aucun_jour_travaille(): void
    {
        $this->assertFalse($this->calcul->contientUnJourTravaille('2026-09-18', '2026-09-19'));
        $this->assertTrue($this->calcul->contientUnJourTravaille('2026-09-18', '2026-09-20'));
    }
}
