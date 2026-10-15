<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conges', function (Blueprint $table) {
            // Demande saisie par le service RH, la direction ou un manager pour un
            // employé qui n'a pas de compte utilisateur.
            $table->foreignId('saisi_par_id')->nullable()->after('motif')->constrained('users')->nullOnDelete();

            // Attestation de congé remise à l'employé : numérotation par année civile,
            // comme le registre papier (N° 11/ANEFIP).
            $table->unsignedSmallInteger('annee_attestation')->nullable();
            $table->unsignedInteger('numero_attestation')->nullable();
            $table->timestamp('date_attestation')->nullable();
            $table->foreignId('attestation_par_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unique(['annee_attestation', 'numero_attestation']);
        });
    }

    public function down(): void
    {
        Schema::table('conges', function (Blueprint $table) {
            $table->dropUnique(['annee_attestation', 'numero_attestation']);
            $table->dropConstrainedForeignId('saisi_par_id');
            $table->dropConstrainedForeignId('attestation_par_id');
            $table->dropColumn(['annee_attestation', 'numero_attestation', 'date_attestation']);
        });
    }
};
