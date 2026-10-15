<?php

use App\Enums\Droit;
use App\Enums\ProfilDroits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Les droits deviennent attribuables un par un depuis « Comptes utilisateurs ».
     * L'administrateur les reçoit tous, et le rôle « rh » disparaît : les comptes
     * qui l'avaient conservent exactement les mêmes accès, sous forme de droits
     * individuels (profil « Service du personnel »).
     */
    public function up(): void
    {
        $ids = $this->creerLeCatalogue();

        $adminId = DB::table($this->table('roles'))->where('name', 'admin')->where('guard_name', 'web')->value('id')
            ?? DB::table($this->table('roles'))->insertGetId([
                'name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
            ]);

        foreach ($ids as $permissionId) {
            DB::table($this->table('role_has_permissions'))->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $adminId,
            ]);
        }

        $this->convertirLeRoleRh($ids);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $cleModele = config('permission.column_names.model_morph_key', 'model_id');
        $ids = DB::table($this->table('permissions'))
            ->whereIn('name', Droit::valeurs())
            ->where('guard_name', 'web')
            ->pluck('id', 'name');

        if ($ids->isNotEmpty()) {
            $rhId = DB::table($this->table('roles'))->insertGetId([
                'name' => 'rh', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
            ]);

            // Les comptes ayant le profil complet du service du personnel retrouvent le rôle « rh ».
            $attendus = ProfilDroits::Rh->valeursDroits();
            $porteurs = DB::table($this->table('model_has_permissions'))
                ->whereIn('permission_id', $ids->only($attendus)->values())
                ->get()
                ->groupBy(fn ($ligne) => $ligne->model_type.'|'.$ligne->{$cleModele});

            foreach ($porteurs as $cle => $lignes) {
                if ($lignes->count() < count($attendus)) {
                    continue;
                }

                [$type, $modele] = explode('|', (string) $cle);
                DB::table($this->table('model_has_roles'))->updateOrInsert([
                    'role_id' => $rhId, 'model_type' => $type, $cleModele => $modele,
                ]);
            }

            DB::table($this->table('model_has_permissions'))->whereIn('permission_id', $ids->values())->delete();
            DB::table($this->table('role_has_permissions'))->whereIn('permission_id', $ids->values())->delete();
            DB::table($this->table('permissions'))->whereIn('id', $ids->values())->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<string, int> identifiant de chaque droit, indexé par sa valeur
     */
    private function creerLeCatalogue(): array
    {
        $ids = [];

        foreach (Droit::cases() as $droit) {
            $ids[$droit->value] = DB::table($this->table('permissions'))
                ->where('name', $droit->value)->where('guard_name', 'web')->value('id')
                ?? DB::table($this->table('permissions'))->insertGetId([
                    'name' => $droit->value, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
                ]);
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $ids
     */
    private function convertirLeRoleRh(array $ids): void
    {
        $rhId = DB::table($this->table('roles'))->where('name', 'rh')->where('guard_name', 'web')->value('id');

        if ($rhId === null) {
            return;
        }

        $cleModele = config('permission.column_names.model_morph_key', 'model_id');

        foreach (DB::table($this->table('model_has_roles'))->where('role_id', $rhId)->get() as $attribution) {
            foreach (ProfilDroits::Rh->valeursDroits() as $valeur) {
                DB::table($this->table('model_has_permissions'))->updateOrInsert([
                    'permission_id' => $ids[$valeur],
                    'model_type' => $attribution->model_type,
                    $cleModele => $attribution->{$cleModele},
                ]);
            }
        }

        DB::table($this->table('model_has_roles'))->where('role_id', $rhId)->delete();
        DB::table($this->table('roles'))->where('id', $rhId)->delete();
    }

    private function table(string $nom): string
    {
        return config("permission.table_names.{$nom}", $nom);
    }
};
