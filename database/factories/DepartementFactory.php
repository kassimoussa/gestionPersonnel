<?php

namespace Database\Factories;

use App\Enums\TypeDepartement;
use App\Models\Departement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Departement>
 */
class DepartementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->company(),
            // Une direction est la seule unité valide sans parent.
            'type' => TypeDepartement::Direction,
            'responsable_id' => null,
            'parent_departement_id' => null,
        ];
    }

    public function division(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TypeDepartement::Division]);
    }

    public function departement(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TypeDepartement::Departement]);
    }

    public function service(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TypeDepartement::Service]);
    }
}
