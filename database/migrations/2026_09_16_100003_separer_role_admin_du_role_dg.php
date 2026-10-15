<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Le rôle "dg" disparaît : le DG est désormais le responsable de l'unité Direction générale.
     * Les comptes qui l'avaient deviennent administrateurs de l'application (rôle "admin").
     */
    public function up(): void
    {
        $roles = config('permission.table_names.roles', 'roles');
        $attributions = config('permission.table_names.model_has_roles', 'model_has_roles');

        $adminId = DB::table($roles)->where('name', 'admin')->where('guard_name', 'web')->value('id')
            ?? DB::table($roles)->insertGetId(['name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);

        $dgId = DB::table($roles)->where('name', 'dg')->where('guard_name', 'web')->value('id');

        if ($dgId !== null) {
            foreach (DB::table($attributions)->where('role_id', $dgId)->get() as $attribution) {
                DB::table($attributions)->updateOrInsert([
                    'role_id' => $adminId,
                    'model_type' => $attribution->model_type,
                    'model_id' => $attribution->model_id,
                ]);
            }

            DB::table($attributions)->where('role_id', $dgId)->delete();
            DB::table($roles)->where('id', $dgId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roles = config('permission.table_names.roles', 'roles');
        $attributions = config('permission.table_names.model_has_roles', 'model_has_roles');

        $adminId = DB::table($roles)->where('name', 'admin')->where('guard_name', 'web')->value('id');

        if ($adminId !== null) {
            $dgId = DB::table($roles)->insertGetId(['name' => 'dg', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            DB::table($attributions)->where('role_id', $adminId)->update(['role_id' => $dgId]);
            DB::table($roles)->where('id', $adminId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
