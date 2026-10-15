<?php

namespace App\Services\Organigramme;

use App\Enums\TypeDepartement;
use App\Models\Departement;
use App\Models\Employe;
use Illuminate\Support\Collection;

/**
 * Une unité organisationnelle avec son type, son responsable (null = poste vacant),
 * ses sous-unités et ses employés actifs directs (hors responsable).
 */
final readonly class NoeudDepartement
{
    /**
     * @param  NoeudDepartement[]  $enfants
     * @param  Collection<int, Employe>  $employes
     */
    public function __construct(
        public Departement $departement,
        public TypeDepartement $type,
        public ?Employe $responsable,
        public array $enfants,
        public Collection $employes,
        public bool $estDirectionGenerale = false,
    ) {}

    public function posteVacant(): bool
    {
        return $this->responsable === null;
    }

    /**
     * Responsable + employés directs + effectif de toutes les sous-unités.
     */
    public function effectifTotal(): int
    {
        $total = $this->employes->count() + ($this->responsable === null ? 0 : 1);

        foreach ($this->enfants as $enfant) {
            $total += $enfant->effectifTotal();
        }

        return $total;
    }
}
