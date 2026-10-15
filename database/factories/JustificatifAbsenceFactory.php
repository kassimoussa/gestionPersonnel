<?php

namespace Database\Factories;

use App\Enums\StatutJustificatif;
use App\Models\Employe;
use App\Models\JustificatifAbsence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JustificatifAbsence>
 */
class JustificatifAbsenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employe_id' => Employe::factory(),
            'date' => now()->subDay()->toDateString(),
            'motif' => 'Rendez-vous médical',
            'fichier_path' => null,
            'fichier_nom' => null,
            'statut' => StatutJustificatif::EnAttente,
        ];
    }

    public function accepte(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => StatutJustificatif::Accepte,
            'date_decision' => now(),
        ]);
    }

    public function refuse(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => StatutJustificatif::Refuse,
            'date_decision' => now(),
            'commentaire_decision' => 'Pièce illisible',
        ]);
    }
}
