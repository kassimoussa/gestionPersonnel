<?php

namespace App\Enums;

/**
 * Événements qui jalonnent le dossier d'un agent : sanctions disciplinaires
 * d'un côté, faits de carrière de l'autre. Seules les sanctions se comptent
 * et, pour la mise à pied, se déduisent de la présence.
 */
enum TypeEvenementEmploye: string
{
    case Avertissement = 'avertissement';
    case Blame = 'blame';
    case MiseAPied = 'mise_a_pied';
    case Licenciement = 'licenciement';

    case Promotion = 'promotion';
    case Mutation = 'mutation';
    case Formation = 'formation';
    case Distinction = 'distinction';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::Avertissement => 'Avertissement',
            self::Blame => 'Blâme',
            self::MiseAPied => 'Mise à pied',
            self::Licenciement => 'Licenciement',
            self::Promotion => 'Promotion',
            self::Mutation => 'Mutation ou affectation',
            self::Formation => 'Formation',
            self::Distinction => 'Distinction ou félicitations',
            self::Autre => 'Autre événement',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Avertissement => 'Rappel à l\'ordre écrit, sans suspension.',
            self::Blame => 'Sanction écrite plus grave que l\'avertissement.',
            self::MiseAPied => 'Suspension temporaire du travail, sur une période datée.',
            self::Licenciement => 'Rupture du contrat à l\'initiative de l\'employeur.',
            self::Promotion => 'Changement de poste, de grade ou de catégorie.',
            self::Mutation => 'Changement d\'unité ou de lieu d\'affectation.',
            self::Formation => 'Stage ou formation suivie par l\'agent.',
            self::Distinction => 'Félicitations, prime exceptionnelle, reconnaissance.',
            self::Autre => 'Tout autre fait à consigner au dossier.',
        };
    }

    /**
     * Une sanction se compte dans le dossier disciplinaire de l'agent.
     */
    public function estSanction(): bool
    {
        return in_array($this, [self::Avertissement, self::Blame, self::MiseAPied, self::Licenciement], true);
    }

    /**
     * Seule la mise à pied s'étale sur une période et suspend le travail.
     */
    public function suspendLeTravail(): bool
    {
        return $this === self::MiseAPied;
    }

    /**
     * Variante du composant <x-badge> (système de badges commun à toute l'application).
     */
    public function varianteBadge(): string
    {
        return match ($this) {
            self::MiseAPied, self::Licenciement => 'danger',
            self::Avertissement, self::Blame => 'attention',
            self::Promotion, self::Distinction => 'succes',
            self::Mutation, self::Formation => 'info',
            self::Autre => 'neutre',
        };
    }

    /** @return self[] */
    public static function sanctions(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $type->estSanction()));
    }

    /** @return self[] */
    public static function faitsDeCarriere(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => ! $type->estSanction()));
    }

    /** @return string[] */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
