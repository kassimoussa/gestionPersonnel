<?php

namespace Tests\Unit;

use App\Services\CalculJoursOuvres;
use PHPUnit\Framework\TestCase;

class CalculJoursOuvresTest extends TestCase
{
    private CalculJoursOuvres $calcul;

    protected function setUp(): void
    {
        parent::setUp();

        // Weekend de Djibouti : vendredi (5) et samedi (6). 2026-09-13 est un dimanche.
        $this->calcul = new CalculJoursOuvres([5, 6], ['2026-09-16' => 'Fete mobile', '01-01' => "Jour de l'An"]);
    }

    public function test_compte_les_jours_du_dimanche_au_jeudi(): void
    {
        // Le mercredi 16 est ferie dans ce calendrier de test.
        $this->assertSame(4, $this->calcul->entre('2026-09-13', '2026-09-17'));
    }

    public function test_exclut_vendredi_et_samedi_sur_une_semaine_complete(): void
    {
        $this->assertSame(4, $this->calcul->entre('2026-09-13', '2026-09-19'));
    }

    public function test_un_jour_ferie_n_est_pas_ouvre(): void
    {
        $this->assertFalse($this->calcul->estOuvre('2026-09-16'));
        $this->assertTrue($this->calcul->estFerie('2026-09-16'));
        $this->assertSame('Fete mobile', $this->calcul->libelleFerie('2026-09-16'));

        // Une fete a date fixe vaut pour toutes les annees.
        $this->assertTrue($this->calcul->estFerie('2027-01-01'));
        $this->assertTrue($this->calcul->estFerie('2030-01-01'));
        $this->assertNull($this->calcul->libelleFerie('2026-09-15'));
    }

    public function test_une_periode_uniquement_en_weekend_vaut_zero(): void
    {
        $this->assertSame(0, $this->calcul->entre('2026-09-18', '2026-09-19'));
    }

    public function test_les_bornes_sont_incluses(): void
    {
        $this->assertSame(1, $this->calcul->entre('2026-09-14', '2026-09-14'));
    }

    public function test_une_date_de_fin_anterieure_vaut_zero(): void
    {
        $this->assertSame(0, $this->calcul->entre('2026-09-17', '2026-09-13'));
    }

    public function test_respecte_une_configuration_de_weekend_differente(): void
    {
        $calcul = new CalculJoursOuvres([6, 7], []);

        // Dimanche 13 et samedi 19 exclus, lundi à vendredi comptés.
        $this->assertSame(5, $calcul->entre('2026-09-13', '2026-09-19'));
    }
}
