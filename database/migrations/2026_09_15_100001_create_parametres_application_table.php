<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametres_application', function (Blueprint $table) {
            // Une seule ligne, d'identifiant fixe 1 (voir ParametresApplication::courants()).
            $table->id();
            $table->string('nom_application');
            $table->string('logo_path')->nullable();
            $table->string('couleur_accent', 7)->default('#245B63');
            $table->string('nom_legal_entreprise')->nullable();
            $table->text('adresse_entreprise')->nullable();
            $table->string('telephone_entreprise', 50)->nullable();
            $table->string('email_entreprise')->nullable();
            $table->timestamps();
        });

        // Valeur de départ, à renommer depuis l'écran Paramètres.
        DB::table('parametres_application')->insert([
            'id' => 1,
            'nom_application' => 'Gestion RH',
            'couleur_accent' => '#245B63',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres_application');
    }
};
