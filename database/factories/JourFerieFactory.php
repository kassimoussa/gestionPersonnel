<?php

namespace Database\Factories;

use App\Models\JourFerie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JourFerie>
 */
class JourFerieFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'libelle' => 'Jour férié',
            'recurrent' => false,
        ];
    }

    public function recurrent(): static
    {
        return $this->state(fn (array $attributes) => ['recurrent' => true]);
    }
}
