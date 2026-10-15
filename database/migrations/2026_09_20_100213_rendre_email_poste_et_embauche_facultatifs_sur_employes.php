<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'annuaire de la pointeuse ne fournit que le nom, l'identifiant et le service :
     * l'adresse e-mail, le poste et la date d'embauche se complètent ensuite, fiche par
     * fiche. Ils deviennent donc facultatifs plutôt que d'être remplis de valeurs fictives.
     */
    public function up(): void
    {
        Schema::table('employes', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('poste')->nullable()->change();
            $table->date('date_embauche')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('employes', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('poste')->nullable(false)->change();
            $table->date('date_embauche')->nullable(false)->change();
        });
    }
};
