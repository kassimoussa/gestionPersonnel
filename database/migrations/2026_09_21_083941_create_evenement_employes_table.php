<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Événements du dossier d'un agent : sanctions disciplinaires (avertissement,
     * blâme, mise à pied, licenciement) et faits de carrière (promotion, mutation,
     * formation, distinction). La mise à pied porte une période : elle suspend le
     * travail et apparaît comme telle dans le rapport de présence.
     */
    public function up(): void
    {
        Schema::create('evenements_employe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->enum('type', [
                'avertissement', 'blame', 'mise_a_pied', 'licenciement',
                'promotion', 'mutation', 'formation', 'distinction', 'autre',
            ]);
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->boolean('avec_solde')->nullable();
            $table->text('motif');
            $table->string('reference')->nullable();
            $table->string('fichier_path')->nullable();
            $table->string('fichier_nom')->nullable();
            $table->foreignId('enregistre_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employe_id', 'type']);
            $table->index('date_debut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenements_employe');
    }
};
