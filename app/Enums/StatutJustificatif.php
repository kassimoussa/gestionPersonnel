<?php

namespace App\Enums;

enum StatutJustificatif: string
{
    case EnAttente = 'en_attente';
    case Accepte = 'accepte';
    case Refuse = 'refuse';

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente de décision',
            self::Accepte => 'Acceptée',
            self::Refuse => 'Refusée',
        };
    }

    /**
     * Variante du composant <x-badge> (système de badges commun à toute l'application).
     */
    public function varianteBadge(): string
    {
        return match ($this) {
            self::EnAttente => 'attention',
            self::Accepte => 'succes',
            self::Refuse => 'danger',
        };
    }

    /** @return string[] */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
