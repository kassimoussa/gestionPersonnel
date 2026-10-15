<?php

namespace App\Policies;

use App\Models\User;

class ContratPolicy
{
    /**
     * Les contrats portent les salaires : réservés aux gestionnaires du personnel
     * (RH, DG, administrateur). Les managers n'y ont pas accès.
     */
    public function gerer(User $user): bool
    {
        return $user->peutGererContrats();
    }
}
