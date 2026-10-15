<?php

namespace App\Policies;

use App\Models\FichePaie;
use App\Models\User;

class FichePaiePolicy
{
    /**
     * Préparer la paie : génération des brouillons, ajustement des primes et indemnités,
     * suppression d'un brouillon, consultation de l'historique (RH et DG).
     */
    public function preparer(User $user): bool
    {
        return $user->peutPreparerPaie();
    }

    /**
     * Valider une fiche : le directeur général uniquement, pour séparer
     * la préparation de l'engagement des montants.
     */
    public function valider(User $user): bool
    {
        return $user->peutValiderPaie();
    }

    /**
     * Consulter ou télécharger un bulletin : les comptes qui préparent la paie.
     * Le bulletin est remis à l'agent par le service du personnel.
     */
    public function voir(User $user, FichePaie $fiche): bool
    {
        return $user->peutPreparerPaie();
    }
}
