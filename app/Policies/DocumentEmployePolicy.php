<?php

namespace App\Policies;

use App\Models\DocumentEmploye;
use App\Models\Employe;
use App\Models\User;

/**
 * Les pièces d'un dossier (contrat signé, pièce d'identité, certificat...) sont
 * de nature sensible : elles suivent les mêmes droits que les données sensibles
 * de la fiche.
 */
class DocumentEmployePolicy
{
    /**
     * Voir la liste des pièces d'un employé et les télécharger.
     */
    public function consulterCeuxDe(User $user, Employe $employe): bool
    {
        return $user->peutVoirDonneesSensibles();
    }

    public function telecharger(User $user, DocumentEmploye $document): bool
    {
        return $document->employe !== null && $this->consulterCeuxDe($user, $document->employe);
    }

    /**
     * Ajouter une pièce au dossier : le service du personnel et la direction.
     * Un employé ne dépose pas lui-même de pièce dans son dossier.
     */
    public function ajouterPour(User $user, Employe $employe): bool
    {
        return $user->peutGererPersonnel() && $user->peutVoirDonneesSensibles();
    }

    public function supprimer(User $user, DocumentEmploye $document): bool
    {
        return $document->employe !== null && $this->ajouterPour($user, $document->employe);
    }
}
