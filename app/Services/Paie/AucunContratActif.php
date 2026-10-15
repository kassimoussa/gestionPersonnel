<?php

namespace App\Services\Paie;

use App\Models\Employe;
use RuntimeException;

/**
 * L'employé n'a aucun contrat en vigueur sur le mois demandé : impossible de calculer sa paie.
 */
class AucunContratActif extends RuntimeException
{
    public function __construct(public readonly Employe $employe, public readonly int $annee, public readonly int $mois)
    {
        parent::__construct(sprintf(
            '%s n\'a aucun contrat en vigueur sur %02d/%d.',
            $employe->nomComplet(),
            $mois,
            $annee,
        ));
    }
}
