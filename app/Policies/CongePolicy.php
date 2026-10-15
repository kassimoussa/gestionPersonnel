<?php

namespace App\Policies;

use App\Models\Conge;
use App\Models\Employe;
use App\Models\User;

/**
 * Le circuit du congé est tenu par le service du personnel : il ouvre la demande,
 * classe le formulaire visé par le chef, établit l'attestation et enregistre la
 * signature du directeur général. L'employé consulte son dossier et ses pièces.
 */
class CongePolicy
{
    /**
     * Ouvrir une demande pour un employé de son périmètre.
     */
    public function creerPour(User $user, Employe $employe): bool
    {
        return $user->peutSaisirCongePourAutrui()
            && $user->employesSupervises()->whereKey($employe->id)->exists();
    }

    /**
     * Conduire le circuit : visa, attestation, signature, refus.
     */
    public function conduire(User $user, Conge $conge): bool
    {
        return $conge->employe !== null
            && $user->peutEtablirAttestationConge()
            && $user->employesSupervises()->whereKey($conge->employe_id)->exists();
    }

    /**
     * Enregistrer un congé déjà accordé hors application, sans repasser par le circuit.
     */
    public function enregistrerCommeAccorde(User $user): bool
    {
        return $user->peutEtablirAttestationConge();
    }

    /**
     * Retirer une demande ouverte par erreur, tant qu'elle n'est pas actée.
     */
    public function annuler(User $user, Conge $conge): bool
    {
        if (! $conge->estEnCours()) {
            return false;
        }

        return $conge->employe !== null && $this->creerPour($user, $conge->employe);
    }

    /**
     * Éditer les documents du circuit : formulaire, attestation, récapitulatif.
     * Ils sont remis à l'employé sur papier, par le service du personnel.
     */
    public function telechargerLesDocuments(User $user, Conge $conge): bool
    {
        return $this->conduire($user, $conge);
    }
}
