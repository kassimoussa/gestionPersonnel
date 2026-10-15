<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En-tête officiel repris en haut des documents produits par l'application
     * (attestation de congé), une ligne par retour à la ligne.
     */
    private const ENTETE_ANEFIP = "République de Djibouti\nUnité - Égalité - Paix\nMinistère du Travail, chargé de la Formalisation et de la Protection Sociale\nAgence Nationale de l'Emploi, de la Formation et de l'Insertion Professionnelle";

    public function up(): void
    {
        Schema::table('parametres_application', function (Blueprint $table) {
            $table->text('entete_documents')->nullable()->after('pied_page_documents');
        });

        DB::table('parametres_application')
            ->where('id', 1)
            ->whereNull('entete_documents')
            ->update(['entete_documents' => self::ENTETE_ANEFIP]);
    }

    public function down(): void
    {
        Schema::table('parametres_application', function (Blueprint $table) {
            $table->dropColumn('entete_documents');
        });
    }
};
