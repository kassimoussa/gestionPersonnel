<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dossier du personnel : identité, situation, coordonnées, données administratives et bancaires.
     * Tous les champs sont facultatifs pour ne pas bloquer les fiches existantes.
     */
    public function up(): void
    {
        Schema::table('employes', function (Blueprint $table) {
            $table->string('matricule', 50)->nullable()->unique()->after('id');
            $table->string('photo_path')->nullable()->after('prenom');
            $table->enum('sexe', ['F', 'M'])->nullable()->after('photo_path');
            $table->date('date_naissance')->nullable()->after('sexe');
            $table->string('lieu_naissance')->nullable()->after('date_naissance');
            $table->string('nationalite', 100)->nullable()->after('lieu_naissance');
            $table->enum('situation_familiale', ['celibataire', 'marie', 'divorce', 'veuf'])->nullable()->after('nationalite');
            $table->unsignedTinyInteger('nombre_enfants')->nullable()->after('situation_familiale');
            $table->text('adresse')->nullable()->after('telephone');
            $table->string('contact_urgence_nom')->nullable()->after('adresse');
            $table->string('contact_urgence_telephone', 50)->nullable()->after('contact_urgence_nom');
            $table->string('categorie', 100)->nullable()->after('poste');
            $table->string('numero_piece_identite', 100)->nullable()->after('actif');
            $table->string('numero_cnss', 50)->nullable()->after('numero_piece_identite');
            $table->string('banque', 150)->nullable()->after('numero_cnss');
            $table->string('numero_compte_bancaire', 100)->nullable()->after('banque');
        });
    }

    public function down(): void
    {
        Schema::table('employes', function (Blueprint $table) {
            $table->dropUnique(['matricule']);
            $table->dropColumn([
                'matricule', 'photo_path', 'sexe', 'date_naissance', 'lieu_naissance', 'nationalite',
                'situation_familiale', 'nombre_enfants', 'adresse', 'contact_urgence_nom',
                'contact_urgence_telephone', 'categorie', 'numero_piece_identite', 'numero_cnss',
                'banque', 'numero_compte_bancaire',
            ]);
        });
    }
};
