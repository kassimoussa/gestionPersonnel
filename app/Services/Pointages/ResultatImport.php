<?php

namespace App\Services\Pointages;

use App\Models\ImportPointages;

/**
 * Bilan d'un import : l'enregistrement journalisé et le nombre de pointages touchés.
 */
final readonly class ResultatImport
{
    public function __construct(
        public ImportPointages $import,
        public int $pointagesCrees,
        public int $pointagesMisAJour,
        /** Journées ignorées car un pointage saisi manuellement existait déjà */
        public int $joursIgnores,
    ) {}
}
