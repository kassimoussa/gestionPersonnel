<?php

namespace App\Enums;

/**
 * Type d'une unité organisationnelle (table `departements`, au sens générique).
 * Schémas d'imbrication autorisés, coexistants :
 *   Direction → Division → Service
 *   Direction → Département → Service
 *   Direction → Service
 *   Direction → Direction (le directeur général chapeaute les autres directeurs)
 */
enum TypeDepartement: string
{
    case Direction = 'direction';
    case Division = 'division';
    case Departement = 'departement';
    case Service = 'service';

    public function libelle(): string
    {
        return match ($this) {
            self::Direction => 'Direction',
            self::Division => 'Division',
            self::Departement => 'Département',
            self::Service => 'Service',
        };
    }

    /**
     * Types d'unité auxquels une unité de ce type peut être rattachée
     * (liste vide : l'unité est toujours une racine).
     *
     * @return self[]
     */
    public function parentsAutorises(): array
    {
        return match ($this) {
            self::Direction => [self::Direction],
            self::Division, self::Departement => [self::Direction],
            self::Service => [self::Direction, self::Division, self::Departement],
        };
    }

    /**
     * Seule une direction peut occuper le sommet de l'organigramme ; elle peut
     * aussi être rattachée à la direction générale, qui la chapeaute.
     */
    public function peutEtreRacine(): bool
    {
        return $this === self::Direction;
    }

    /**
     * Un service est toujours une feuille de l'organigramme.
     */
    public function peutAvoirDesEnfants(): bool
    {
        return $this !== self::Service;
    }

    public function libelleParentsAutorises(): string
    {
        return implode(' ou ', array_map(fn (self $type) => $type->libelle(), $this->parentsAutorises()));
    }

    /**
     * Variante du composant <x-badge> : une teinte distincte par type, dans le système commun.
     */
    public function varianteBadge(): string
    {
        return $this->value;
    }

    /**
     * Couleurs du badge dans le PDF, alignées sur les teintes du badge écran (app.css).
     *
     * @return array{bordure: string, fond: string, texte: string}
     */
    public function couleursPdf(): array
    {
        return match ($this) {
            self::Direction => ['bordure' => '#E9F0F1', 'fond' => '#E9F0F1', 'texte' => '#245B63'],
            self::Division => ['bordure' => '#F1EAF4', 'fond' => '#F1EAF4', 'texte' => '#6B3F7A'],
            self::Departement => ['bordure' => '#E9EDF1', 'fond' => '#E9EDF1', 'texte' => '#3E4C59'],
            self::Service => ['bordure' => '#EEF2E6', 'fond' => '#EEF2E6', 'texte' => '#556B2F'],
        };
    }

    /** @return string[] */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
