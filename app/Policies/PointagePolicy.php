<?php

namespace App\Policies;

use App\Models\Employe;
use App\Models\Pointage;
use App\Models\User;

class PointagePolicy
{
    /**
     * L'import du fichier de la pointeuse.
     */
    public function importer(User $user): bool
    {
        return $user->peutImporterPointages();
    }

    /**
     * Saisir ou corriger un pointage pour un employé : les comptes habilités sur tout
     * l'effectif, un manager sur les employés de ses unités.
     */
    public function saisir(User $user, Employe $employe): bool
    {
        return ($user->peutSaisirPresence() && $user->employesSupervises()->whereKey($employe->id)->exists())
            || ($user->employe !== null && $user->employe->estManagerDe($employe));
    }

    public function modifier(User $user, Pointage $pointage): bool
    {
        return $pointage->employe !== null && $this->saisir($user, $pointage->employe);
    }

    public function supprimer(User $user, Pointage $pointage): bool
    {
        return $this->modifier($user, $pointage);
    }
}
