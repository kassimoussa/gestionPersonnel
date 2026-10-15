<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une fiche de paie est un document à valeur comptable : la base refuse désormais
     * la suppression d'un employé qui en possède (brouillon ou validée), au lieu de les
     * supprimer en cascade. La migration d'origine (batch 7) n'est pas modifiée.
     */
    public function up(): void
    {
        Schema::table('fiches_paie', function (Blueprint $table) {
            $table->dropForeign(['employe_id']);
        });

        Schema::table('fiches_paie', function (Blueprint $table) {
            $table->foreign('employe_id')->references('id')->on('employes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fiches_paie', function (Blueprint $table) {
            $table->dropForeign(['employe_id']);
        });

        Schema::table('fiches_paie', function (Blueprint $table) {
            $table->foreign('employe_id')->references('id')->on('employes')->cascadeOnDelete();
        });
    }
};
