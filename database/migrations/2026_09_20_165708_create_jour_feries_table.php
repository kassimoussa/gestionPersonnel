<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jours fériés : ils ne sont décomptés ni des congés ni de la présence.
     * Les fêtes à date fixe sont marquées « récurrentes » et valent pour toutes
     * les années ; les fêtes musulmanes, mobiles, se saisissent année par année.
     */
    public function up(): void
    {
        Schema::create('jours_feries', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('libelle');
            $table->boolean('recurrent')->default(false);
            $table->timestamps();

            $table->index('recurrent');
        });

        // Fêtes civiles de Djibouti, à date fixe.
        $fixes = [
            ['01-01', 'Jour de l\'An'],
            ['05-01', 'Fête du Travail'],
            ['06-27', 'Fête de l\'Indépendance'],
        ];

        $annee = now()->year;

        foreach ($fixes as [$jour, $libelle]) {
            DB::table('jours_feries')->insert([
                'date' => "{$annee}-{$jour}",
                'libelle' => $libelle,
                'recurrent' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jours_feries');
    }
};
