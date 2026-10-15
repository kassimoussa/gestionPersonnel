<?php

namespace App\Services\Pointages;

use App\Enums\TypeEvenementPointeuse;

/**
 * Une ligne valide du fichier de la pointeuse, déjà normalisée.
 */
final readonly class LignePointeuse
{
    public function __construct(
        public int $numeroLigne,
        public string $matricule,
        /** Date au format AAAA-MM-JJ */
        public string $date,
        /** Heure au format HH:MM:SS */
        public string $heure,
        public TypeEvenementPointeuse $type,
        /** Ligne brute telle que lue dans le fichier, pour le journal d'erreurs */
        public string $brut,
    ) {}
}
