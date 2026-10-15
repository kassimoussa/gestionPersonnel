<?php

namespace App\Enums;

enum StatutFichePaie: string
{
    case Brouillon = 'brouillon';
    case Validee = 'validee';

    public function libelle(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Validee => 'Validée',
        };
    }

    /**
     * Variante du composant <x-badge>.
     */
    public function varianteBadge(): string
    {
        return match ($this) {
            self::Brouillon => 'neutre',
            self::Validee => 'succes',
        };
    }
}
