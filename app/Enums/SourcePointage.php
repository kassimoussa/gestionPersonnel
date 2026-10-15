<?php

namespace App\Enums;

enum SourcePointage: string
{
    case Import = 'import';
    case Manuel = 'manuel';

    public function libelle(): string
    {
        return match ($this) {
            self::Import => 'Import pointeuse',
            self::Manuel => 'Saisie manuelle',
        };
    }
}
