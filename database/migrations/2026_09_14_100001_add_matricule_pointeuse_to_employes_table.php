<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employes', function (Blueprint $table) {
            // Identifiant de l'employé dans la machine à empreinte (pas encore renseigné)
            $table->string('matricule_pointeuse', 50)->nullable()->unique()->after('telephone');
        });
    }

    public function down(): void
    {
        Schema::table('employes', function (Blueprint $table) {
            $table->dropUnique(['matricule_pointeuse']);
            $table->dropColumn('matricule_pointeuse');
        });
    }
};
