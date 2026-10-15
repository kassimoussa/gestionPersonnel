<?php

namespace App\Policies;

use App\Models\Employe;
use App\Models\User;

class EmployePolicy
{
    /**
     * Ouvrir l'écran des dossiers du personnel : les gestionnaires (RH, DG, administrateur)
     * et les managers, qui y voient leur équipe en lecture seule.
     */
    public function consulter(User $user): bool
    {
        return $user->peutConsulterPersonnel();
    }

    /**
     * Créer, modifier, activer ou supprimer une fiche.
     */
    public function gerer(User $user): bool
    {
        return $user->peutGererPersonnel();
    }

    /**
     * Voir le détail d'une fiche : les gestionnaires pour tout le monde,
     * un manager pour les employés de ses unités.
     */
    public function voir(User $user, Employe $employe): bool
    {
        return $user->peutGererPersonnel()
            || ($user->employe !== null && $user->employe->estManagerDe($employe));
    }

    /**
     * Ouvrir la fiche détaillée d'un employé : les comptes du service, pour les
     * agents de leur périmètre.
     */
    public function voirLaFiche(User $user, Employe $employe): bool
    {
        return $user->peutConsulterPersonnel()
            && $user->employesSupervises()->whereKey($employe->id)->exists();
    }
}
