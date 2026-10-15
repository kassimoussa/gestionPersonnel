# Format d'import des pointages

> **Format provisoire, à ajuster une fois qu'un vrai export de la machine sera disponible.**
> Le modèle exact de la pointeuse à empreinte n'est pas encore connu. Ce document décrit
> le format CSV générique accepté aujourd'hui par l'écran *Présence → Import pointeuse*.
> Quand un export réel sera disponible, seule la classe
> `app/Services/Pointages/LecteurCsvPointeuse.php` devra être adaptée : elle est la seule
> partie du module qui connaît la structure du fichier.

## Annuaire de la machine (export « Employee »)

La pointeuse produit aussi un annuaire, différent des pointages : un classeur `.xlsx`
nommé `Employee_<date>_export.xlsx`, avec les colonnes `Employee ID`, `First Name`,
`Department`, `Device Privilege`, `Area`, `APP Status`, `Valid` et l'état d'enrôlement
biométrique. Il ne contient ni date ni heure, donc aucun pointage.

Il sert à constituer l'effectif et le lien fiche employé ↔ pointeuse :

```
docker compose exec app php artisan pointeuse:importer-annuaire fichier.xlsx --simulation
docker compose exec app php artisan pointeuse:importer-annuaire fichier.xlsx
```

- `Employee ID` remplit à la fois le *matricule* et le *matricule pointeuse* : à l'ANEFIP,
  c'est le même numéro. Une personne déjà liée à ce matricule est ignorée, ce qui rend la
  commande rejouable. Dans le formulaire employé, les deux champs se recopient l'un dans
  l'autre quand l'un des deux est laissé vide.
- `First Name` contient le nom complet : le premier mot devient le prénom, le reste le nom.
- `Department` retrouve l'unité existante en ignorant accents, casse et préfixe
  (« Service Informatique » rejoint « Informatique »), la crée sinon. La valeur par défaut
  de la machine, `Department`, laisse l'employé sans unité.
- Le classeur est lu sans dépendance par `app/Services/LecteurClasseurXlsx.php`.

## Fichier attendu


- Fichier texte CSV, encodage UTF-8 (un BOM en début de fichier est toléré).
- Première ligne : en-tête obligatoire avec les noms de colonnes ci-dessous (en minuscules,
  ordre libre, colonnes supplémentaires ignorées).
- Séparateur recommandé : `;`. La virgule et la tabulation sont détectées automatiquement
  à partir de la ligne d'en-tête.
- Une ligne par événement de badge (une entrée ou une sortie). Les lignes vides sont ignorées.
- Taille maximale acceptée par l'écran d'import : 5 Mo.

## Colonnes

| Colonne | Contenu | Formats acceptés | Exemple |
| --- | --- | --- | --- |
| `matricule_pointeuse` | Identifiant de l'employé dans la machine. Doit correspondre au champ *Matricule pointeuse* de la fiche employé (comparaison exacte, sensible à la casse). | texte, 50 caractères max | `A001` |
| `date` | Jour de l'événement | `AAAA-MM-JJ` (recommandé), `JJ/MM/AAAA`, `AAAA/MM/JJ`, `JJ-MM-AAAA` | `2026-09-14` |
| `heure` | Heure de l'événement | `HH:MM` ou `HH:MM:SS` (24 h) | `07:58` |
| `type` | Nature de l'événement | `entree` ou `sortie` (alias tolérés : `entrée`, `e`, `in`, `0` / `s`, `out`, `1`, sans distinction de casse) | `entree` |

## Exemple

```csv
matricule_pointeuse;date;heure;type
A001;2026-09-14;07:58;entree
A001;2026-09-14;12:02;sortie
A001;2026-09-14;13:05;entree
A001;2026-09-14;17:31;sortie
B014;2026-09-14;08:22;entree
B014;2026-09-14;17:00;sortie
```

## Règles de traitement

- Les événements d'un même employé et d'un même jour sont regroupés en **un seul pointage** :
  `heure_arrivee` = première entrée de la journée, `heure_depart` = dernière sortie.
  Dans l'exemple, A001 obtient un pointage 07:58 → 17:31 pour le 14/09/2026.
- Une ligne dont le matricule ne correspond à aucun employé est **ignorée et journalisée**
  en erreur ; le reste du fichier est importé normalement.
- Une ligne mal formée (date, heure ou type invalide, matricule vide) est ignorée et
  journalisée avec son numéro de ligne et son contenu.
- Un fichier sans les quatre colonnes attendues dans l'en-tête est rejeté entièrement
  (une seule erreur, ligne 1).
- Si un pointage **importé** existe déjà pour l'employé et le jour, il est fusionné
  (arrivée la plus tôt, départ le plus tard) : ré-importer le même fichier ne change rien.
- Si un pointage **saisi manuellement** existe déjà pour ce jour, il fait foi : les lignes
  correspondantes sont ignorées et journalisées.

Chaque import est enregistré dans la table `imports_pointages` (fichier, auteur, nombre de
lignes traitées, nombre d'erreurs, détail des erreurs) et consultable depuis l'écran d'import.

## Où adapter le code quand le vrai format sera connu

| Besoin | Fichier |
| --- | --- |
| Lire un autre format (colonnes, séparateur, encodage, fichier Excel, etc.) | `app/Services/Pointages/LecteurCsvPointeuse.php` |
| Reconnaître d'autres libellés d'entrée/sortie | `app/Enums/TypeEvenementPointeuse.php` |
| Changer les règles de regroupement ou de fusion | `app/Services/Pointages/ImportateurPointages.php` |
| Documenter le format réel | ce fichier |

Le lecteur doit simplement continuer à produire des objets `LignePointeuse`
(matricule, date `AAAA-MM-JJ`, heure `HH:MM:SS`, type entrée/sortie) : l'importateur,
les écrans et les tests ne dépendent que de cette structure.
