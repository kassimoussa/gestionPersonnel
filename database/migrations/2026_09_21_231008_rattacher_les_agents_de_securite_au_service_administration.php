<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const UNITE_SUPPRIMEE = 'Securite et Femme de Menage';

    private const UNITE_DESTINATION = 'Service Administration';

    /**
     * L'unité « Securite et Femme de Menage » ne figure pas sur l'organigramme
     * officiel de l'ANEFIP : le gardiennage et l'entretien relèvent du Service
     * Administration, où ses agents sont transférés avant sa suppression.
     */
    public function up(): void
    {
        // Les bases de test partent vides : il n'y a aucune unité à reprendre.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $supprimee = DB::table('departements')->where('nom', self::UNITE_SUPPRIMEE)->first();
        $destination = DB::table('departements')->where('nom', self::UNITE_DESTINATION)->first();

        if ($supprimee === null || $destination === null) {
            return;
        }

        DB::table('employes')
            ->where('departement_id', $supprimee->id)
            ->update(['departement_id' => $destination->id, 'updated_at' => now()]);

        DB::table('departements')->where('parent_departement_id', $supprimee->id)->update([
            'parent_departement_id' => $destination->id,
            'updated_at' => now(),
        ]);

        DB::table('departements')->where('id', $supprimee->id)->delete();
    }

    /**
     * Irréversible : l'unité d'origine de chaque agent n'est plus enregistrée
     * nulle part une fois le transfert fait.
     */
    public function down(): void
    {
        //
    }
};
