<?php

namespace Database\Factories;

use App\Enums\SourcePointage;
use App\Models\Employe;
use App\Models\Pointage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pointage>
 */
class PointageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employe_id' => Employe::factory(),
            'date' => now()->toDateString(),
            'heure_arrivee' => '08:00:00',
            'heure_depart' => '17:00:00',
            'source' => SourcePointage::Import,
            'import_id' => null,
            'saisi_par_id' => null,
            'commentaire' => null,
        ];
    }

    public function manuel(): static
    {
        return $this->state(fn (array $attributes) => ['source' => SourcePointage::Manuel]);
    }

    public function enRetard(): static
    {
        return $this->state(fn (array $attributes) => ['heure_arrivee' => '08:30:00']);
    }
}
