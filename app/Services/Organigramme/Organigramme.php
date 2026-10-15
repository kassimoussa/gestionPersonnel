<?php

namespace App\Services\Organigramme;

use App\Models\Employe;
use Illuminate\Support\Collection;

/**
 * Arbre complet, réutilisé par l'affichage interactif et par l'export PDF.
 */
final readonly class Organigramme
{
    /**
     * @param  NoeudDepartement[]  $racines  Unités racines, la Direction générale en premier
     * @param  Collection<int, Employe>  $employesSansDepartement
     */
    public function __construct(
        public array $racines,
        public Collection $employesSansDepartement,
    ) {}

    public function estVide(): bool
    {
        return $this->racines === [] && $this->employesSansDepartement->isEmpty();
    }

    public function effectifTotal(): int
    {
        $total = $this->employesSansDepartement->count();

        foreach ($this->racines as $racine) {
            $total += $racine->effectifTotal();
        }

        return $total;
    }
}
