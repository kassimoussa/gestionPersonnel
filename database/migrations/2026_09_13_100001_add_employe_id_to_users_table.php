<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Lien compte Breeze ↔ fiche RH (un compte par employé au maximum)
            $table->foreignId('employe_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('employes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employe_id');
        });
    }
};
