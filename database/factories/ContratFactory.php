<?php

namespace Database\Factories;

use App\Models\Contrat;
use App\Models\Employe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contrat>
 */
class ContratFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employe_id' => Employe::factory(),
            'type' => 'CDI',
            'date_debut' => '2025-01-01',
            'date_fin' => null,
            'salaire_base' => 100000,
        ];
    }

    public function cdd(string $dateDebut, string $dateFin): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'CDD',
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
        ]);
    }
}
