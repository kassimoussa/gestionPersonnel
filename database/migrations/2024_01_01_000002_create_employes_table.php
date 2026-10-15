<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employes', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom');
            $table->string('email')->unique();
            $table->string('telephone')->nullable();
            $table->string('poste');
            $table->foreignId('departement_id')->nullable()->constrained('departements')->nullOnDelete();
            $table->date('date_embauche');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // Contrainte différée : le responsable d'un département est un employé
        Schema::table('departements', function (Blueprint $table) {
            $table->foreign('responsable_id')->references('id')->on('employes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departements', function (Blueprint $table) {
            $table->dropForeign(['responsable_id']);
        });
        Schema::dropIfExists('employes');
    }
};
