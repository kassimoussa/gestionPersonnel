<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pointages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->date('date');
            $table->time('heure_arrivee')->nullable();
            $table->time('heure_depart')->nullable();
            $table->enum('source', ['import', 'manuel']);
            $table->foreignId('import_id')->nullable()->constrained('imports_pointages')->nullOnDelete();
            $table->foreignId('saisi_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('commentaire')->nullable();
            $table->timestamps();

            // Un seul enregistrement par employé et par jour (entrée/sortie regroupées)
            $table->unique(['employe_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pointages');
    }
};
