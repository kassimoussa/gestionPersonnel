<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->enum('type', ['conge_annuel', 'maladie', 'sans_solde', 'evenement_familial']);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->decimal('nombre_jours', 5, 2);
            $table->text('motif')->nullable();
            $table->enum('statut', [
                'en_attente_manager',
                'en_attente_dg',
                'approuve',
                'refuse_manager',
                'refuse_dg',
            ])->default('en_attente_manager');

            // Étape 1 : décision du manager (responsable du département de l'employé)
            $table->foreignId('manager_validateur_id')->nullable()->constrained('employes')->nullOnDelete();
            $table->timestamp('date_decision_manager')->nullable();

            // Étape 2 : décision de la DG (compte utilisateur ayant le rôle "dg")
            $table->foreignId('dg_validateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('date_decision_dg')->nullable();

            $table->text('commentaire_refus')->nullable();
            $table->timestamps();

            $table->index(['employe_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conges');
    }
};
