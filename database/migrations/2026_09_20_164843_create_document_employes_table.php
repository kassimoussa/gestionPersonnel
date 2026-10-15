<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pièces du dossier d'un employé : contrat signé, diplôme, pièce d'identité,
     * CV... Les fichiers sont rangés sur le disque privé et servis par une route
     * contrôlée, jamais exposés publiquement.
     */
    public function up(): void
    {
        Schema::create('documents_employe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->enum('type', [
                'contrat', 'piece_identite', 'diplome', 'cv', 'certificat_medical',
                'attestation', 'correspondance', 'autre',
            ])->default('autre');
            $table->string('libelle');
            $table->date('date_document')->nullable();
            $table->string('fichier_path');
            $table->string('fichier_nom');
            $table->unsignedInteger('taille_octets')->nullable();
            $table->foreignId('ajoute_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employe_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_employe');
    }
};
