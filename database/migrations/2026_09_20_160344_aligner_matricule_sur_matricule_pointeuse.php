<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * À l'ANEFIP, le matricule de l'employé et son identifiant dans la pointeuse sont
     * un seul et même numéro. Les fiches créées depuis l'annuaire de la machine
     * n'avaient que l'identifiant : on recopie la valeur dans le matricule, sans
     * écraser un matricule déjà saisi ni créer de doublon.
     */
    public function up(): void
    {
        $pris = DB::table('employes')->whereNotNull('matricule')->pluck('matricule')->all();

        DB::table('employes')
            ->whereNull('matricule')
            ->whereNotNull('matricule_pointeuse')
            ->when($pris !== [], fn ($query) => $query->whereNotIn('matricule_pointeuse', $pris))
            ->update(['matricule' => DB::raw('matricule_pointeuse')]);
    }

    public function down(): void
    {
        DB::table('employes')
            ->whereNotNull('matricule_pointeuse')
            ->whereColumn('matricule', 'matricule_pointeuse')
            ->update(['matricule' => null]);
    }
};
