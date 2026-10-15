<?php

use App\Enums\StatutConge;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * L'énumération MySQL de la colonne statut avait été reconstruite à partir des
     * anciens statuts seulement : « attestation_a_signer », qui n'était la
     * traduction d'aucun d'entre eux, y manquait, et l'étape 3 du circuit échouait.
     * Elle est ici redéfinie à partir de l'énumération de l'application.
     */
    public function up(): void
    {
        $this->redefinirLeStatut(StatutConge::valeurs());
    }

    public function down(): void
    {
        $this->redefinirLeStatut(array_values(array_filter(
            StatutConge::valeurs(),
            fn (string $valeur) => $valeur !== StatutConge::AttestationASigner->value,
        )));
    }

    /**
     * SQLite, utilisé par les tests, stocke la colonne en texte : rien à faire.
     *
     * @param  string[]  $valeurs
     */
    private function redefinirLeStatut(array $valeurs): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $liste = implode(', ', array_map(fn (string $valeur) => "'{$valeur}'", $valeurs));

        DB::statement(
            "ALTER TABLE conges MODIFY statut ENUM({$liste}) NOT NULL DEFAULT '".StatutConge::FormulaireRemis->value."'"
        );
    }
};
