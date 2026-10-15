<?php

namespace App\Services\Pointages;

use App\Enums\TypeEvenementPointeuse;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Lecture du fichier CSV exporté par la pointeuse.
 *
 * FORMAT PROVISOIRE : le modèle de la machine n'est pas encore connu. Le format
 * attendu est décrit dans docs/format-import-pointages.md. Quand un vrai export
 * sera disponible, seule cette classe doit être adaptée : elle est la seule à
 * connaître la structure du fichier. Le reste du module (ImportateurPointages,
 * composants Livewire) ne manipule que des objets LignePointeuse.
 */
class LecteurCsvPointeuse
{
    public const COLONNES = ['matricule_pointeuse', 'date', 'heure', 'type'];

    private const DELIMITEURS = [';', ',', "\t"];

    private const FORMATS_DATE = ['Y-m-d', 'd/m/Y', 'Y/m/d', 'd-m-Y'];

    public function lire(string $chemin): ResultatLecture
    {
        $resultat = new ResultatLecture;

        $flux = @fopen($chemin, 'r');

        if ($flux === false) {
            throw new RuntimeException("Impossible d'ouvrir le fichier {$chemin}.");
        }

        try {
            $entete = null;
            $delimiteur = ';';
            $numero = 0;

            while (($brut = fgets($flux)) !== false) {
                $numero++;
                $brut = rtrim($brut, "\r\n");

                if ($numero === 1) {
                    $brut = $this->retirerBom($brut);
                }

                if (trim($brut) === '') {
                    continue;
                }

                if ($entete === null) {
                    $delimiteur = $this->detecterDelimiteur($brut);
                    $entete = $this->lireEntete($brut, $delimiteur);

                    $manquantes = array_diff(self::COLONNES, $entete);

                    if ($manquantes !== []) {
                        $resultat->ajouterErreur(
                            $numero,
                            'En-tête invalide, colonnes manquantes : '.implode(', ', $manquantes)
                                .' (attendu : '.implode($delimiteur, self::COLONNES).').',
                            $brut,
                        );

                        return $resultat;
                    }

                    continue;
                }

                $cellules = str_getcsv($brut, $delimiteur, '"', '\\');
                $valeurs = [];

                foreach (self::COLONNES as $colonne) {
                    $index = array_search($colonne, $entete, true);
                    $valeurs[$colonne] = trim((string) ($cellules[$index] ?? ''));
                }

                try {
                    $resultat->lignes[] = $this->convertir($numero, $valeurs, $brut);
                } catch (InvalidArgumentException $exception) {
                    $resultat->ajouterErreur($numero, $exception->getMessage(), $brut);
                }
            }
        } finally {
            fclose($flux);
        }

        return $resultat;
    }

    /**
     * @param  array{matricule_pointeuse: string, date: string, heure: string, type: string}  $valeurs
     */
    private function convertir(int $numero, array $valeurs, string $brut): LignePointeuse
    {
        if ($valeurs['matricule_pointeuse'] === '') {
            throw new InvalidArgumentException('Matricule pointeuse vide.');
        }

        $date = $this->normaliserDate($valeurs['date'])
            ?? throw new InvalidArgumentException("Date invalide « {$valeurs['date']} » (attendu AAAA-MM-JJ ou JJ/MM/AAAA).");

        $heure = $this->normaliserHeure($valeurs['heure'])
            ?? throw new InvalidArgumentException("Heure invalide « {$valeurs['heure']} » (attendu HH:MM ou HH:MM:SS).");

        $type = TypeEvenementPointeuse::depuisTexte($valeurs['type'])
            ?? throw new InvalidArgumentException("Type invalide « {$valeurs['type']} » (attendu entree ou sortie).");

        return new LignePointeuse($numero, $valeurs['matricule_pointeuse'], $date, $heure, $type, $brut);
    }

    /**
     * @return string[] Noms de colonnes en minuscules, dans l'ordre du fichier
     */
    private function lireEntete(string $ligne, string $delimiteur): array
    {
        return array_map(
            fn (?string $colonne) => mb_strtolower(trim((string) $colonne)),
            str_getcsv($ligne, $delimiteur, '"', '\\'),
        );
    }

    private function detecterDelimiteur(string $entete): string
    {
        $meilleur = ';';
        $maximum = 0;

        foreach (self::DELIMITEURS as $candidat) {
            $occurrences = substr_count($entete, $candidat);

            if ($occurrences > $maximum) {
                $maximum = $occurrences;
                $meilleur = $candidat;
            }
        }

        return $meilleur;
    }

    private function normaliserDate(string $valeur): ?string
    {
        foreach (self::FORMATS_DATE as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $valeur);
            $erreurs = DateTimeImmutable::getLastErrors();
            $sansErreur = $erreurs === false || ($erreurs['warning_count'] === 0 && $erreurs['error_count'] === 0);

            if ($date !== false && $sansErreur) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function normaliserHeure(string $valeur): ?string
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $valeur, $parties)) {
            return null;
        }

        [$heures, $minutes, $secondes] = [(int) $parties[1], (int) $parties[2], (int) ($parties[3] ?? 0)];

        if ($heures > 23 || $minutes > 59 || $secondes > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', $heures, $minutes, $secondes);
    }

    private function retirerBom(string $ligne): string
    {
        return str_starts_with($ligne, "\xEF\xBB\xBF") ? substr($ligne, 3) : $ligne;
    }
}
