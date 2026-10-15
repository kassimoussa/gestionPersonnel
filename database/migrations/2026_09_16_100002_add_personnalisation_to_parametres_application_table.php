<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres_application', function (Blueprint $table) {
            // Organisation : la Direction générale est une unité de type Direction ; son responsable est le DG.
            $table->foreignId('direction_generale_id')->nullable()->after('email_entreprise')->constrained('departements')->nullOnDelete();

            // Interface et écran de connexion
            $table->string('couleur_accent_secondaire', 7)->nullable()->after('couleur_accent');
            $table->string('titre_connexion')->nullable();
            $table->string('texte_connexion', 500)->nullable();
            $table->boolean('inscription_ouverte')->default(false);

            // Temps de travail et congés (null = valeur par défaut de config/personnel.php)
            $table->json('jours_weekend')->nullable();
            $table->string('heure_debut_travail', 5)->nullable();
            $table->unsignedSmallInteger('tolerance_retard_minutes')->nullable();
            $table->decimal('jours_conge_par_mois', 4, 2)->nullable();

            // Documents PDF
            $table->text('pied_page_documents')->nullable();
            $table->string('signataire_nom')->nullable();
            $table->string('signataire_fonction')->nullable();
        });

        // Installation existante : la Direction générale devient une unité de type Direction.
        if (DB::table('departements')->exists()) {
            $existante = DB::table('departements')
                ->where('type', 'direction')
                ->whereRaw('LOWER(nom) IN (?, ?)', ['direction générale', 'direction generale'])
                ->value('id');

            $id = $existante ?? DB::table('departements')->insertGetId([
                'nom' => 'Direction générale',
                'type' => 'direction',
                'responsable_id' => null,
                'parent_departement_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('parametres_application')->where('id', 1)->update(['direction_generale_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('parametres_application', function (Blueprint $table) {
            $table->dropConstrainedForeignId('direction_generale_id');
            $table->dropColumn([
                'couleur_accent_secondaire', 'titre_connexion', 'texte_connexion', 'inscription_ouverte',
                'jours_weekend', 'heure_debut_travail', 'tolerance_retard_minutes', 'jours_conge_par_mois',
                'pied_page_documents', 'signataire_nom', 'signataire_fonction',
            ]);
        });
    }
};
