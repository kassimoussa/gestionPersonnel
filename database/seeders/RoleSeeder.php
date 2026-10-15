<?php

namespace Database\Seeders;

use App\Enums\Droit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Catalogue des droits et rôle administrateur, qui les possède tous.
     * L'administrateur s'attribue depuis l'écran Comptes utilisateurs, ou avec :
     * php artisan roles:assigner-admin admin@rh.test
     *
     * Le directeur général et les managers ne s'attribuent pas : ils découlent
     * de l'organigramme (responsable de la Direction générale, responsable d'une unité).
     */
    public function run(): void
    {
        foreach (Droit::cases() as $droit) {
            Permission::findOrCreate($droit->value, 'web');
        }

        Role::findOrCreate(User::ROLE_ADMIN, 'web')->syncPermissions(Droit::valeurs());
    }
}
