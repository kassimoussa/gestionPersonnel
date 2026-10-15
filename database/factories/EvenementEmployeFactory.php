<?php

namespace Database\Factories;

use App\Enums\TypeEvenementEmploye;
use App\Models\Employe;
use App\Models\EvenementEmploye;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvenementEmploye>
 */
class EvenementEmployeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employe_id' => Employe::factory(),
            'type' => TypeEvenementEmploye::Avertissement,
            'date_debut' => now()->subMonth()->toDateString(),
            'date_fin' => null,
            'motif' => 'Retards répétés',
            'reference' => null,
        ];
    }

    public function miseAPied(string $debut, string $fin, bool $avecSolde = false): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TypeEvenementEmploye::MiseAPied,
            'date_debut' => $debut,
            'date_fin' => $fin,
            'avec_solde' => $avecSolde,
        ]);
    }

    public function type(TypeEvenementEmploye $type): static
    {
        return $this->state(fn (array $attributes) => ['type' => $type]);
    }
}
