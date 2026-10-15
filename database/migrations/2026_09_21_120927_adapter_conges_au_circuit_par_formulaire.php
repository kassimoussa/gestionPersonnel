<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nouveau circuit : le service du personnel remet un formulaire, le chef le
     * vise, le RH établit l'attestation, le directeur général la signe et le congé
     * est acté. Les pieces signees sont conservees avec la demande.
     *
     * Correspondance des anciens statuts :
     *   en_attente_manager → formulaire_remis
     *   en_attente_dg      → vise_par_le_chef
     *   approuve           → acte
     *   refuse_manager / refuse_dg → refuse
     */
    private const CORRESPONDANCES = [
        'en_attente_manager' => 'formulaire_remis',
        'en_attente_dg' => 'vise_par_le_chef',
        'approuve' => 'acte',
        'refuse_manager' => 'refuse',
        'refuse_dg' => 'refuse',
    ];

    public function up(): void
    {
        Schema::table('conges', function (Blueprint $table) {
            $table->string('formulaire_signe_path')->nullable()->after('commentaire_refus');
            $table->string('formulaire_signe_nom')->nullable()->after('formulaire_signe_path');
            $table->string('attestation_signee_path')->nullable()->after('formulaire_signe_nom');
            $table->string('attestation_signee_nom')->nullable()->after('attestation_signee_path');
        });

        // La colonne statut accueille les deux jeux de valeurs le temps de la reprise.
        $this->redefinirLeStatut(array_merge(array_keys(self::CORRESPONDANCES), array_values(self::CORRESPONDANCES)));

        foreach (self::CORRESPONDANCES as $ancien => $nouveau) {
            DB::table('conges')->where('statut', $ancien)->update(['statut' => $nouveau]);
        }

        $this->redefinirLeStatut(
            array_merge(array_values(self::CORRESPONDANCES), ['annule']),
            'formulaire_remis',
        );
    }

    public function down(): void
    {
        $this->redefinirLeStatut(array_merge(array_keys(self::CORRESPONDANCES), array_values(self::CORRESPONDANCES)));

        foreach (self::CORRESPONDANCES as $ancien => $nouveau) {
            DB::table('conges')->where('statut', $nouveau)->update(['statut' => $ancien]);
        }

        $this->redefinirLeStatut(array_keys(self::CORRESPONDANCES), 'en_attente_manager');

        Schema::table('conges', function (Blueprint $table) {
            $table->dropColumn([
                'formulaire_signe_path', 'formulaire_signe_nom',
                'attestation_signee_path', 'attestation_signee_nom',
            ]);
        });
    }

    /**
     * Redéfinit l'énumération de la colonne statut. SQLite ne connaît pas ENUM :
     * la colonne y est un texte, rien à faire.
     *
     * @param  string[]  $valeurs
     */
    private function redefinirLeStatut(array $valeurs, ?string $defaut = null): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $liste = implode(', ', array_map(fn (string $valeur) => "'{$valeur}'", array_values(array_unique($valeurs))));
        $suffixe = $defaut === null ? '' : " DEFAULT '{$defaut}'";

        DB::statement("ALTER TABLE conges MODIFY statut ENUM({$liste}) NOT NULL{$suffixe}");
    }
};
