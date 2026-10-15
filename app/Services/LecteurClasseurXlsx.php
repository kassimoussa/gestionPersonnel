<?php

namespace App\Services;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Lit un classeur .xlsx sans dépendance : le format est une archive zip contenant
 * du XML, que PHP sait ouvrir nativement. Seule la première feuille est lue, et
 * chaque cellule est rendue telle qu'elle est stockée (les nombres restent des
 * nombres, les textes des textes).
 */
class LecteurClasseurXlsx
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /**
     * Lignes de la première feuille, indexées à partir de 0, chaque ligne étant
     * un tableau de colonnes indexé à partir de 0 et complété pour les cellules vides.
     *
     * @return array<int, array<int, string>>
     */
    public function lignes(string $chemin): array
    {
        $archive = new ZipArchive;

        if ($archive->open($chemin) !== true) {
            throw new RuntimeException("Le fichier n'est pas un classeur .xlsx lisible.");
        }

        try {
            $chaines = $this->chainesPartagees($archive);
            $feuille = $this->premiereFeuille($archive);

            return $this->lignesDeLaFeuille($feuille, $chaines);
        } finally {
            $archive->close();
        }
    }

    /**
     * @return string[]
     */
    private function chainesPartagees(ZipArchive $archive): array
    {
        $contenu = $archive->getFromName('xl/sharedStrings.xml');

        if ($contenu === false) {
            return [];
        }

        $chaines = [];

        foreach ($this->enfants($this->xml($contenu), 'si') as $si) {
            $chaines[] = $this->texte($si);
        }

        return $chaines;
    }

    private function premiereFeuille(ZipArchive $archive): SimpleXMLElement
    {
        $contenu = $archive->getFromName('xl/worksheets/sheet1.xml');

        if ($contenu === false) {
            for ($i = 0; $i < $archive->numFiles; $i++) {
                $nom = (string) $archive->getNameIndex($i);

                if (str_starts_with($nom, 'xl/worksheets/') && str_ends_with($nom, '.xml')) {
                    $contenu = $archive->getFromName($nom);

                    break;
                }
            }
        }

        if (! is_string($contenu)) {
            throw new RuntimeException('Le classeur ne contient aucune feuille de calcul.');
        }

        return $this->xml($contenu);
    }

    /**
     * @param  string[]  $chaines
     * @return array<int, array<int, string>>
     */
    private function lignesDeLaFeuille(SimpleXMLElement $feuille, array $chaines): array
    {
        $lignes = [];

        foreach ($this->enfants($feuille, 'sheetData') as $donnees) {
            foreach ($this->enfants($donnees, 'row') as $ligne) {
                $cellules = [];

                foreach ($this->enfants($ligne, 'c') as $cellule) {
                    $cellules[$this->indiceColonne($this->attribut($cellule, 'r'))] = $this->valeur($cellule, $chaines);
                }

                $lignes[] = $cellules === []
                    ? []
                    : array_map(
                        fn (int $indice) => $cellules[$indice] ?? '',
                        range(0, max(array_keys($cellules))),
                    );
            }
        }

        return $lignes;
    }

    /**
     * @param  string[]  $chaines
     */
    private function valeur(SimpleXMLElement $cellule, array $chaines): string
    {
        $type = $this->attribut($cellule, 't');

        if ($type === 'inlineStr') {
            foreach ($this->enfants($cellule, 'is') as $inline) {
                return $this->texte($inline);
            }

            return '';
        }

        $valeur = null;

        foreach ($this->enfants($cellule, 'v') as $v) {
            $valeur = (string) $v;
        }

        if ($valeur === null) {
            return '';
        }

        if ($type === 's') {
            return $chaines[(int) $valeur] ?? '';
        }

        return $valeur;
    }

    /**
     * Texte d'un élément, y compris quand il est découpé en plusieurs morceaux mis en forme.
     */
    private function texte(SimpleXMLElement $element): string
    {
        $morceaux = [];

        foreach ($element->xpath('.//*[local-name()="t"]') ?: [] as $t) {
            $morceaux[] = (string) $t;
        }

        return implode('', $morceaux);
    }

    /**
     * « C12 » vaut 2 : la troisième colonne, comptée à partir de 0.
     */
    private function indiceColonne(string $reference): int
    {
        preg_match('/^[A-Z]+/', strtoupper($reference), $lettres);

        $indice = 0;

        foreach (str_split($lettres[0] ?? 'A') as $lettre) {
            $indice = $indice * 26 + (ord($lettre) - 64);
        }

        return $indice - 1;
    }

    /**
     * Les attributs ne portent pas de namespace : ils se lisent à part, sinon
     * SimpleXMLElement les cherche dans celui de l'élément et ne trouve rien.
     */
    private function attribut(SimpleXMLElement $element, string $nom): string
    {
        $attributs = $element->attributes();

        return $attributs === null ? '' : (string) ($attributs[$nom] ?? '');
    }

    /**
     * @return SimpleXMLElement[]
     */
    private function enfants(SimpleXMLElement $element, string $nom): array
    {
        $enfants = $element->children(self::NS)->{$nom};

        return $enfants === null ? [] : iterator_to_array($enfants, false);
    }

    private function xml(string $contenu): SimpleXMLElement
    {
        $xml = simplexml_load_string($contenu);

        if ($xml === false) {
            throw new RuntimeException('Le contenu du classeur est illisible.');
        }

        return $xml;
    }
}
