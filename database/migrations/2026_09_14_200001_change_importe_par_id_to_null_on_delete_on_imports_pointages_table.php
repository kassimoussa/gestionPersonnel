<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conserve l'historique des imports quand le compte qui les a réalisés est
     * supprimé : la clé étrangère passe de cascadeOnDelete à nullOnDelete.
     * La migration d'origine (déjà exécutée) n'est pas modifiée.
     */
    public function up(): void
    {
        Schema::table('imports_pointages', function (Blueprint $table) {
            $table->dropForeign(['importe_par_id']);
        });

        Schema::table('imports_pointages', function (Blueprint $table) {
            $table->unsignedBigInteger('importe_par_id')->nullable()->change();
        });

        Schema::table('imports_pointages', function (Blueprint $table) {
            $table->foreign('importe_par_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('imports_pointages', function (Blueprint $table) {
            $table->dropForeign(['importe_par_id']);
        });

        Schema::table('imports_pointages', function (Blueprint $table) {
            $table->unsignedBigInteger('importe_par_id')->nullable(false)->change();
        });

        Schema::table('imports_pointages', function (Blueprint $table) {
            $table->foreign('importe_par_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
