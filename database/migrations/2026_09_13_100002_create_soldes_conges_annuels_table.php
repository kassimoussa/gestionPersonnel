<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soldes_conges_annuels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->decimal('jours_acquis', 6, 2)->default(0);
            // Dernier mois (AAAA-MM) pris en compte par conges:accumuler-soldes,
            // pour rendre la commande idempotente si elle est relancée dans le mois.
            $table->string('dernier_mois_accumule', 7)->nullable();
            $table->timestamps();

            // Une ligne par employé et par année
            $table->unique(['employe_id', 'annee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soldes_conges_annuels');
    }
};
