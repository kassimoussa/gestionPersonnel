<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Justification d'une journée d'absence : l'employé explique et joint sa pièce
     * (certificat médical, convocation...), son responsable accepte ou refuse.
     * Une seule justification par employé et par jour.
     */
    public function up(): void
    {
        Schema::create('justificatifs_absence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->date('date');
            $table->string('motif', 1000);
            $table->string('fichier_path')->nullable();
            $table->string('fichier_nom')->nullable();
            $table->enum('statut', ['en_attente', 'accepte', 'refuse'])->default('en_attente');

            // Qui a saisi la justification : l'employé lui-même, ou le service du personnel pour lui.
            $table->foreignId('saisi_par_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('decide_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('date_decision')->nullable();
            $table->text('commentaire_decision')->nullable();
            $table->timestamps();

            $table->unique(['employe_id', 'date']);
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justificatifs_absence');
    }
};
