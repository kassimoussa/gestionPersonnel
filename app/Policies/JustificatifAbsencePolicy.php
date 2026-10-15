<?php

namespace App\Policies;

use App\Models\Employe;
use App\Models\JustificatifAbsence;
use App\Models\User;

/**
 * Les justifications d'absence sont saisies et tranchées par les comptes qui
 * suivent la présence : l'agent apporte sa pièce au service du personnel.
 */
class JustificatifAbsencePolicy
{
    /**
     * Enregistrer la justification d'un agent de son périmètre.
     */
    public function creerPour(User $user, Employe $employe): bool
    {
        return $this->couvre($user, $employe);
    }

    /**
     * Accepter ou refuser : celui qui suit la présence de l'employé. Personne ne
     * tranche sa propre justification, fût-il responsable.
     */
    public function decider(User $user, JustificatifAbsence $justificatif): bool
    {
        if ($justificatif->employe === null) {
            return false;
        }

        if ($user->employe_id !== null && (int) $user->employe_id === (int) $justificatif->employe_id) {
            return false;
        }

        return $this->couvre($user, $justificatif->employe);
    }

    /**
     * Consulter la pièce jointe : ceux qui suivent la présence de l'agent.
     */
    public function voirLaPiece(User $user, JustificatifAbsence $justificatif): bool
    {
        return $justificatif->employe !== null && $this->couvre($user, $justificatif->employe);
    }

    /**
     * Retirer une justification tant qu'aucune décision n'a été prise.
     */
    public function retirer(User $user, JustificatifAbsence $justificatif): bool
    {
        if (! $justificatif->estEnAttente()) {
            return false;
        }

        return $justificatif->employe !== null && $this->couvre($user, $justificatif->employe);
    }

    /**
     * L'employé fait-il partie du périmètre de présence de l'utilisateur ?
     */
    private function couvre(User $user, Employe $employe): bool
    {
        return $user->peutSaisirPresence()
            && $user->employesSupervises()->whereKey($employe->id)->exists();
    }
}
