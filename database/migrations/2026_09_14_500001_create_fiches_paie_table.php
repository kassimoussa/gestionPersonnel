<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiches_paie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('mois');
            // Copié du contrat au moment de la génération : historique stable même si le contrat change.
            $table->decimal('salaire_base', 12, 2);
            $table->decimal('primes', 12, 2)->default(0);
            $table->decimal('indemnites', 12, 2)->default(0);
            $table->decimal('brut', 12, 2);
            $table->decimal('cnss_salarie', 12, 2);
            $table->decimal('cnss_employeur', 12, 2);
            $table->decimal('base_imposable', 12, 2);
            $table->decimal('irpp', 12, 2);
            $table->decimal('net_a_payer', 12, 2);
            $table->enum('statut', ['brouillon', 'validee'])->default('brouillon');
            $table->foreignId('validee_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validee_le')->nullable();
            $table->timestamps();

            // Une seule fiche par employé et par mois.
            $table->unique(['employe_id', 'annee', 'mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiches_paie');
    }
};
