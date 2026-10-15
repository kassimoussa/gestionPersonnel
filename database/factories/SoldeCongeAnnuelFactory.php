<?php

namespace Database\Factories;

use App\Models\Employe;
use App\Models\SoldeCongeAnnuel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SoldeCongeAnnuel>
 */
class SoldeCongeAnnuelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employe_id' => Employe::factory(),
            'annee' => now()->year,
            'jours_acquis' => 0,
            'dernier_mois_accumule' => null,
        ];
    }
}
