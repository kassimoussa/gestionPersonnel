<?php

namespace Database\Factories;

use App\Enums\StatutFichePaie;
use App\Models\Employe;
use App\Models\FichePaie;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FichePaie>
 */
class FichePaieFactory extends Factory
{
    /**
     * Montants de l'exemple de référence : brut 100 000 FD en CDI.
     */
    public function definition(): array
    {
        return [
            'employe_id' => Employe::factory(),
            'annee' => 2026,
            'mois' => 9,
            'salaire_base' => 100000,
            'primes' => 0,
            'indemnites' => 0,
            'brut' => 100000,
            'cnss_salarie' => 6000,
            'cnss_employeur' => 15700,
            'base_imposable' => 94000,
            'irpp' => 11520,
            'net_a_payer' => 82480,
            'statut' => StatutFichePaie::Brouillon,
            'validee_par_id' => null,
            'validee_le' => null,
        ];
    }

    public function validee(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => StatutFichePaie::Validee,
            'validee_par_id' => User::factory(),
            'validee_le' => now(),
        ]);
    }
}
