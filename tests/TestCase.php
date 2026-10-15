<?php

namespace Tests;

use App\Enums\Droit;
use App\Enums\ProfilDroits;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\ParametresApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    /**
     * Compte administrateur de l'application : il a tous les droits et il est
     * le seul à pouvoir en attribuer.
     */
    protected function creerCompteAdmin(): User
    {
        Role::findOrCreate(User::ROLE_ADMIN, 'web');

        $admin = User::factory()->create();
        $admin->assignRole(User::ROLE_ADMIN);

        return $admin;
    }

    /**
     * Compte du service du personnel : dossiers, contrats, présence, congés
     * et préparation de la paie (profil « Service du personnel »).
     */
    protected function creerCompteRh(): User
    {
        return $this->creerCompteAvecDroits(...ProfilDroits::Rh->droits());
    }

    /**
     * Compte portant exactement les droits demandés.
     */
    protected function creerCompteAvecDroits(Droit ...$droits): User
    {
        $compte = User::factory()->create();

        foreach ($droits as $droit) {
            Permission::findOrCreate($droit->value, 'web');
        }

        $compte->syncPermissions(array_map(fn (Droit $droit) => $droit->value, $droits));

        return $compte;
    }

    /**
     * Compte du directeur général : responsable de l'unité Direction générale désignée
     * dans les paramètres (créée si besoin), et rattaché à cette unité.
     *
     * @param  array<string, mixed>  $attributsEmploye
     */
    protected function creerCompteDg(array $attributsEmploye = []): User
    {
        $parametres = ParametresApplication::courants();

        $direction = $parametres->directionGenerale
            ?? Departement::factory()->create(['nom' => 'Direction générale']);

        $employe = Employe::factory()->create([
            'prenom' => 'Directeur',
            'nom' => 'Général',
            'poste' => 'Directeur général',
            'departement_id' => $direction->id,
            ...$attributsEmploye,
        ]);

        $direction->update(['responsable_id' => $employe->id]);
        $parametres->update(['direction_generale_id' => $direction->id]);

        return User::factory()->create(['employe_id' => $employe->id]);
    }
}
