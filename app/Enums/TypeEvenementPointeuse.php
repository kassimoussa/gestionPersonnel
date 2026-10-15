<?php

namespace App\Enums;

/**
 * Type d'un événement brut exporté par la pointeuse (colonne "type" du CSV).
 */
enum TypeEvenementPointeuse: string
{
    case Entree = 'entree';
    case Sortie = 'sortie';

    /**
     * Interprète la valeur textuelle du fichier, avec quelques alias tolérés
     * (voir docs/format-import-pointages.md). Retourne null si inconnue.
     */
    public static function depuisTexte(string $valeur): ?self
    {
        $normalisee = mb_strtolower(trim($valeur));
        $normalisee = str_replace(['é', 'è', 'ê'], 'e', $normalisee);

        return match ($normalisee) {
            'entree', 'e', 'in', '0' => self::Entree,
            'sortie', 's', 'out', '1' => self::Sortie,
            default => null,
        };
    }
}
