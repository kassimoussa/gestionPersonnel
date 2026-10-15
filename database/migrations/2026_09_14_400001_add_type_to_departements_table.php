<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPES = ['direction', 'division', 'departement', 'service'];

    public function up(): void
    {
        Schema::table('departements', function (Blueprint $table) {
            $table->enum('type', self::TYPES)->nullable()->after('nom');
        });

        // Migration de données : les unités existantes deviennent des "departement".
        DB::table('departements')->whereNull('type')->update(['type' => 'departement']);

        // Un "departement" doit être rattaché à une direction : tout autre parent est retiré
        // (l'unité devient racine) et signalé dans le journal pour correction manuelle.
        $types = DB::table('departements')->pluck('type', 'id');

        $rattaches = DB::table('departements')
            ->whereNotNull('parent_departement_id')
            ->get(['id', 'nom', 'parent_departement_id']);

        foreach ($rattaches as $departement) {
            if (($types[$departement->parent_departement_id] ?? null) === 'direction') {
                continue;
            }

            DB::table('departements')->where('id', $departement->id)->update(['parent_departement_id' => null]);

            Log::warning('Migration departements.type : parent retiré car il n\'est pas une direction.', [
                'id' => $departement->id,
                'nom' => $departement->nom,
                'ancien_parent_departement_id' => $departement->parent_departement_id,
            ]);
        }

        Schema::table('departements', function (Blueprint $table) {
            $table->enum('type', self::TYPES)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('departements', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
