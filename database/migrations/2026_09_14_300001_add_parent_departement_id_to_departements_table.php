<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departements', function (Blueprint $table) {
            // Sous-départements : un département racine a parent_departement_id = null.
            // nullOnDelete : si le parent est supprimé, le sous-département redevient racine.
            $table->foreignId('parent_departement_id')
                ->nullable()
                ->after('responsable_id')
                ->constrained('departements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_departement_id');
        });
    }
};
