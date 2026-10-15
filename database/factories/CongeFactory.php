<?php

namespace Database\Factories;

use App\Enums\StatutConge;
use App\Enums\TypeConge;
use App\Models\Conge;
use App\Models\Employe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conge>
 */
class CongeFactory extends Factory
{
    public function definition(): array
    {
        $debut = now()->startOfYear()->addDays(fake()->numberBetween(0, 300));

        return [
            'employe_id' => Employe::factory(),
            'type' => TypeConge::CongeAnnuel,
            'date_debut' => $debut->toDateString(),
            'date_fin' => $debut->copy()->addDays(2)->toDateString(),
            'nombre_jours' => 3,
            'motif' => null,
            'statut' => StatutConge::FormulaireRemis,
        ];
    }

    public function type(TypeConge $type): static
    {
        return $this->state(fn (array $attributes) => ['type' => $type]);
    }

    /**
     * Formulaire visé par le chef, en attente de l'attestation.
     */
    public function vise(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => StatutConge::ViseParLeChef,
            'date_decision_manager' => now(),
            'formulaire_signe_path' => 'conges/formulaire-vise.pdf',
            'formulaire_signe_nom' => 'formulaire-vise.pdf',
        ]);
    }

    /**
     * Attestation établie, en attente de la signature du directeur général.
     */
    public function attestationASigner(): static
    {
        return $this->vise()->state(fn (array $attributes) => [
            'statut' => StatutConge::AttestationASigner,
            'annee_attestation' => now()->year,
            'numero_attestation' => fake()->unique()->numberBetween(1, 9999),
            'date_attestation' => now(),
        ]);
    }

    /**
     * Congé acté : l'attestation signée est revenue.
     */
    public function acte(): static
    {
        return $this->attestationASigner()->state(fn (array $attributes) => [
            'statut' => StatutConge::Acte,
            'date_decision_dg' => now(),
            'attestation_signee_path' => 'conges/attestation-signee.pdf',
            'attestation_signee_nom' => 'attestation-signee.pdf',
        ]);
    }

    public function refuse(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => StatutConge::Refuse,
            'date_decision_dg' => now(),
            'commentaire_refus' => 'Effectif insuffisant sur la période',
        ]);
    }
}
