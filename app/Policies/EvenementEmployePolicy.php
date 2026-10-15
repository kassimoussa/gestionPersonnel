<?php

namespace App\Policies;

use App\Models\Employe;
use App\Models\EvenementEmploye;
use App\Models\User;

/**
 * Le dossier disciplinaire est une donnée sensible : il suit les mêmes droits
 * que la pièce d'identité ou le compte bancaire.
 */
class EvenementEmployePolicy
{
    public function consulterCeuxDe(User $user, Employe $employe): bool
    {
        return $user->peutVoirDonneesSensibles();
    }

    /**
     * Consigner une sanction ou un fait de carrière : le service du personnel
     * et la direction, jamais l'agent lui-même.
     */
    public function enregistrerPour(User $user, Employe $employe): bool
    {
        return $user->peutGererPersonnel() && $user->peutVoirDonneesSensibles();
    }

    public function supprimer(User $user, EvenementEmploye $evenement): bool
    {
        return $evenement->employe !== null && $this->enregistrerPour($user, $evenement->employe);
    }

    public function telechargerLaPiece(User $user, EvenementEmploye $evenement): bool
    {
        return $evenement->employe !== null && $this->consulterCeuxDe($user, $evenement->employe);
    }
}
