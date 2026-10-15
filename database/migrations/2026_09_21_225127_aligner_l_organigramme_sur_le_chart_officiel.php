<?php

use App\Enums\TypeDepartement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Met la table `departements` en conformité avec l'organigramme officiel de
     * l'ANEFIP : les unités déjà saisies sont renommées, retypées et rattachées
     * à la Direction générale, et les unités manquantes sont créées. Aucun
     * employé n'est déplacé : les rattachements existants sont conservés.
     *
     * L'unité « Securite et Femme de Menage » ne figure pas sur l'organigramme ;
     * elle est laissée en l'état, ses agents devant être répartis ailleurs.
     *
     * @var array<string, array{nom?: string, type: string, parent: ?string, enfants?: string[]}>
     */
    private const ORGANIGRAMME = [
        'Direction générale' => [
            'type' => 'direction',
            'parent' => null,
        ],
        'Administration' => [
            'nom' => 'Service Administration',
            'type' => 'service',
            'parent' => 'Direction générale',
        ],
        'Ressources Humaines' => [
            'nom' => 'Service Ressources Humaines',
            'type' => 'service',
            'parent' => 'Direction générale',
        ],
        'Service Autorisation de Travail' => [
            'type' => 'service',
            'parent' => 'Direction générale',
        ],
        'Service Communication' => [
            'type' => 'service',
            'parent' => 'Direction générale',
        ],
        'Direction ONEQ' => [
            'nom' => "Direction de l'Observatoire National de l'Emploi et de la Qualification (ONEQ)",
            'type' => 'direction',
            'parent' => 'Direction générale',
            'enfants' => [
                'Service de la statistique et des études',
                'Service du système d\'information',
            ],
        ],
        'Direction de Renforcement' => [
            'nom' => 'Direction du Renforcement des Capacités',
            'type' => 'direction',
            'parent' => 'Direction générale',
            'enfants' => [
                'Service de renforcement des capacités des demandeurs d\'emploi',
                'Service d\'insertion dans l\'apprentissage et la formation aux métiers',
            ],
        ],
        'Direction Emploi' => [
            'nom' => "Direction de l'Emploi",
            'type' => 'direction',
            'parent' => 'Direction générale',
            'enfants' => [
                'Service d\'accueil et d\'orientation professionnelle',
                'Service de la prospection, du placement et des antennes régionales',
                'Service de suivi-évaluation des antennes régionales',
            ],
        ],
        'Service Comptablite' => [
            'nom' => 'Service Comptable',
            'type' => 'departement',
            'parent' => 'Direction générale',
            'enfants' => [
                'Caisse',
                'Service suivi budgétaire et trésorerie',
            ],
        ],
        'Informatique' => [
            'nom' => 'Service Informatique',
            'type' => 'departement',
            'parent' => 'Direction générale',
            'enfants' => [
                'Service système et réseaux',
                'Service développement',
            ],
        ],
        'Securite et Femme de Menage' => [
            'type' => 'service',
            'parent' => 'Direction générale',
        ],
    ];

    public function up(): void
    {
        // Les bases de test partent vides : il n'y a aucune unité à aligner.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $maintenant = now();
        $idsParNom = DB::table('departements')->pluck('id', 'nom')->all();

        foreach (self::ORGANIGRAMME as $nomActuel => $unite) {
            $nom = $unite['nom'] ?? $nomActuel;
            $parentId = $unite['parent'] === null ? null : ($idsParNom[$unite['parent']] ?? null);

            if (isset($idsParNom[$nomActuel])) {
                DB::table('departements')->where('id', $idsParNom[$nomActuel])->update([
                    'nom' => $nom,
                    'type' => $unite['type'],
                    'parent_departement_id' => $parentId,
                    'updated_at' => $maintenant,
                ]);
            } else {
                $idsParNom[$nomActuel] = DB::table('departements')->insertGetId([
                    'nom' => $nom,
                    'type' => $unite['type'],
                    'parent_departement_id' => $parentId,
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ]);
            }

            $idsParNom[$nom] = $idsParNom[$nomActuel];

            foreach ($unite['enfants'] ?? [] as $nomEnfant) {
                if (isset($idsParNom[$nomEnfant])) {
                    continue;
                }

                $idsParNom[$nomEnfant] = DB::table('departements')->insertGetId([
                    'nom' => $nomEnfant,
                    'type' => TypeDepartement::Service->value,
                    'parent_departement_id' => $idsParNom[$nom],
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ]);
            }
        }
    }

    /**
     * Les unités créées sont supprimées ; les renommages et rattachements ne
     * sont pas défaits, l'organigramme d'avant ne correspondant à rien de réel.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $creees = [];

        foreach (self::ORGANIGRAMME as $nomActuel => $unite) {
            if (! isset($unite['nom']) && ! in_array($nomActuel, ['Direction générale', 'Securite et Femme de Menage'], true)) {
                $creees[] = $nomActuel;
            }

            foreach ($unite['enfants'] ?? [] as $nomEnfant) {
                $creees[] = $nomEnfant;
            }
        }

        DB::table('departements')
            ->whereIn('nom', $creees)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('employes')->whereColumn('employes.departement_id', 'departements.id'))
            ->delete();
    }
};
