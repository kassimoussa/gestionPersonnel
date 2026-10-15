<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports_pointages', function (Blueprint $table) {
            $table->id();
            $table->string('nom_fichier');
            $table->foreignId('importe_par_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('nombre_lignes_traitees')->default(0);
            $table->unsignedInteger('nombre_erreurs')->default(0);
            // Liste de {ligne, message, contenu} pour chaque ligne rejetée
            $table->json('erreurs_detail')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports_pointages');
    }
};
