<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AssignerRoleAdmin extends Command
{
    /**
     * Exemple : docker compose exec app php artisan roles:assigner-admin admin@rh.test
     * Le directeur général ne se désigne pas ici : c'est le responsable de la Direction générale.
     */
    protected $signature = 'roles:assigner-admin {email : Adresse e-mail du compte qui devient administrateur}';

    protected $description = 'Crée le rôle "admin" si nécessaire et l\'attribue au compte utilisateur indiqué';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("Aucun compte utilisateur avec l'adresse {$email}.");

            return self::FAILURE;
        }

        $role = Role::findOrCreate(User::ROLE_ADMIN, 'web');

        if ($user->hasRole($role)) {
            $this->info("{$email} est déjà administrateur.");

            return self::SUCCESS;
        }

        $user->assignRole($role);

        $this->info("Rôle administrateur attribué à {$email}.");

        return self::SUCCESS;
    }
}
