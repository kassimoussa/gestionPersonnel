<?php

namespace App\Services\Pointages;

use App\Enums\SourcePointage;
use App\Enums\TypeEvenementPointeuse;
use App\Models\Employe;
use App\Models\ImportPointages;
use App\Models\Pointage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Applique en base le contenu d'un fichier de pointeuse lu par LecteurCsvPointeuse.
 *
 * Règles :
 * - une ligne dont le matricule ne correspond à aucun employé est journalisée
 *   en erreur et ignorée, sans interrompre l'import ;
 * - les événements d'un même employé et d'un même jour sont regroupés en un
 *   seul pointage : heure_arrivee = première entrée, heure_depart = dernière sortie ;
 * - un jour déjà importé est fusionné (arrivée la plus tôt, départ le plus tard),
 *   ce qui rend un ré-import idempotent ;
 * - un jour saisi manuellement fait foi : ses lignes sont ignorées et journalisées.
 */
class ImportateurPointages
{
    public function __construct(private LecteurCsvPointeuse $lecteur) {}

    public function importer(string $chemin, string $nomFichier, User $importePar): ResultatImport
    {
        $lecture = $this->lecteur->lire($chemin);
        $erreurs = $lecture->erreurs;

        $employes = $this->employesParMatricule($lecture->lignes);
        $journees = [];

        foreach ($lecture->lignes as $ligne) {
            $employe = $employes->get($ligne->matricule);

            if ($employe === null) {
                $erreurs[] = [
                    'ligne' => $ligne->numeroLigne,
                    'message' => "Aucun employé avec le matricule pointeuse « {$ligne->matricule} ».",
                    'contenu' => $ligne->brut,
                ];

                continue;
            }

            $cle = $employe->id.'|'.$ligne->date;
            $journees[$cle] ??= [
                'employe' => $employe,
                'date' => $ligne->date,
                'arrivee' => null,
                'depart' => null,
                'lignes' => [],
            ];
            $journees[$cle]['lignes'][] = $ligne->numeroLigne;

            if ($ligne->type === TypeEvenementPointeuse::Entree) {
                if ($journees[$cle]['arrivee'] === null || $ligne->heure < $journees[$cle]['arrivee']) {
                    $journees[$cle]['arrivee'] = $ligne->heure;
                }
            } elseif ($journees[$cle]['depart'] === null || $ligne->heure > $journees[$cle]['depart']) {
                $journees[$cle]['depart'] = $ligne->heure;
            }
        }

        $crees = 0;
        $misAJour = 0;
        $ignores = 0;

        $import = DB::transaction(function () use ($nomFichier, $importePar, $lecture, $journees, &$erreurs, &$crees, &$misAJour, &$ignores): ImportPointages {
            $import = ImportPointages::create([
                'nom_fichier' => $nomFichier,
                'importe_par_id' => $importePar->id,
            ]);

            foreach ($journees as $journee) {
                /** @var Employe $employe */
                $employe = $journee['employe'];

                $existant = Pointage::query()
                    ->where('employe_id', $employe->id)
                    ->whereDate('date', $journee['date'])
                    ->first();

                if ($existant === null) {
                    Pointage::create([
                        'employe_id' => $employe->id,
                        'date' => $journee['date'],
                        'heure_arrivee' => $journee['arrivee'],
                        'heure_depart' => $journee['depart'],
                        'source' => SourcePointage::Import,
                        'import_id' => $import->id,
                    ]);
                    $crees++;

                    continue;
                }

                if ($existant->source === SourcePointage::Manuel) {
                    $erreurs[] = [
                        'ligne' => $journee['lignes'][0],
                        'message' => sprintf(
                            'Pointage saisi manuellement déjà présent pour %s le %s : ligne(s) %s ignorée(s).',
                            $employe->nomComplet(),
                            $journee['date'],
                            implode(', ', $journee['lignes']),
                        ),
                        'contenu' => '',
                    ];
                    $ignores++;

                    continue;
                }

                $existant->heure_arrivee = $this->plusTot($existant->heure_arrivee, $journee['arrivee']);
                $existant->heure_depart = $this->plusTard($existant->heure_depart, $journee['depart']);
                $existant->import_id = $import->id;
                $existant->save();
                $misAJour++;
            }

            usort($erreurs, fn (array $a, array $b) => $a['ligne'] <=> $b['ligne']);

            $import->update([
                'nombre_lignes_traitees' => $lecture->nombreLignes(),
                'nombre_erreurs' => count($erreurs),
                'erreurs_detail' => $erreurs === [] ? null : $erreurs,
            ]);

            return $import;
        });

        return new ResultatImport($import, $crees, $misAJour, $ignores);
    }

    /**
     * @param  LignePointeuse[]  $lignes
     * @return Collection<string, Employe>
     */
    private function employesParMatricule(array $lignes)
    {
        $matricules = array_values(array_unique(array_map(fn (LignePointeuse $ligne) => $ligne->matricule, $lignes)));

        if ($matricules === []) {
            return collect();
        }

        return Employe::query()
            ->whereIn('matricule_pointeuse', $matricules)
            ->get()
            ->keyBy('matricule_pointeuse');
    }

    private function plusTot(?string $a, ?string $b): ?string
    {
        if ($a === null || $b === null) {
            return $a ?? $b;
        }

        return min($a, $b);
    }

    private function plusTard(?string $a, ?string $b): ?string
    {
        if ($a === null || $b === null) {
            return $a ?? $b;
        }

        return max($a, $b);
    }
}
