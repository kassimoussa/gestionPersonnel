<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Identité par défaut : charte ANEFIP (nom et bleu d'accent). Ne touche pas à une
     * ligne déjà personnalisée depuis l'écran Paramètres.
     */
    public function up(): void
    {
        DB::table('parametres_application')
            ->where('id', 1)
            ->where('nom_application', 'Gestion RH')
            ->where('couleur_accent', '#245B63')
            ->update(['nom_application' => 'ANEFIP', 'couleur_accent' => '#1C3C8F', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('parametres_application')
            ->where('id', 1)
            ->where('nom_application', 'ANEFIP')
            ->where('couleur_accent', '#1C3C8F')
            ->update(['nom_application' => 'Gestion RH', 'couleur_accent' => '#245B63', 'updated_at' => now()]);
    }
};
