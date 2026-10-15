<?php

namespace App\Services\Pointages;

/**
 * Résultat de la lecture d'un fichier : lignes exploitables et lignes rejetées.
 */
final class ResultatLecture
{
    /**
     * @param  LignePointeuse[]  $lignes
     * @param  array<int, array{ligne: int, message: string, contenu: string}>  $erreurs
     */
    public function __construct(
        public array $lignes = [],
        public array $erreurs = [],
    ) {}

    public function ajouterErreur(int $ligne, string $message, string $contenu = ''): void
    {
        $this->erreurs[] = ['ligne' => $ligne, 'message' => $message, 'contenu' => $contenu];
    }

    /**
     * Nombre de lignes de données lues (valides ou non), hors en-tête et lignes vides.
     */
    public function nombreLignes(): int
    {
        return count($this->lignes) + count($this->erreurs);
    }
}
