<?php

/*
|--------------------------------------------------------------------------
| Paramètres de paie — Djibouti
|--------------------------------------------------------------------------
|
| Sources officielles, récupérées le 14/09/2026 :
| - Taux CNSS : cnss.dj, page « Déclaration et versement des cotisations ».
| - Barème IRPP : Code Général des Impôts (Direction Générale des Impôts),
|   articles 14 à 16, chapitre « Impôt sur les traitements et salaires ».
|
| Ces sources n'affichent pas de date de mise à jour claire et peuvent être
| antérieures à une loi de finances plus récente. Tous les taux et tranches
| sont ici, jamais en dur dans le code métier : corriger ce fichier suffit.
| Lire docs/points-a-verifier-paie.md avant tout versement réel de salaire.
|
*/

return [

    'devise' => 'FD',

    'cnss' => [
        // Assiette : brut mensuel, primes et indemnités incluses.
        // Plancher : si le brut est inférieur, les cotisations sont calculées sur ce montant.
        'assiette_minimale' => 20000,

        // Aucun plafond identifié sur cnss.dj : null = pas de plafond (ne pas en inventer un).
        'plafond' => null,

        // Taux en pourcentage de l'assiette. Part salariale : 6 % au total.
        'salarie' => [
            'retraite' => 4.0,
            'soins' => 2.0,
        ],

        // Part patronale : 15,7 % au total.
        'employeur' => [
            'prestations_familiales' => 5.5,
            'soins' => 5.0,
            'accident_travail' => 1.2,
            'retraite' => 4.0,
        ],

        'libelles_branches' => [
            'retraite' => 'Retraite',
            'soins' => 'Soins de santé',
            'prestations_familiales' => 'Prestations familiales',
            'accident_travail' => 'Accident de travail',
        ],
    ],

    'irpp' => [
        // Base imposable = brut − part salariale CNSS (cotisations déductibles).
        // Tranches mensuelles progressives ; 'jusqua' null = dernière tranche, sans limite.
        // Aucun arrondi ni exonération appliqué (voir le point ouvert sur l'article 14 du CGI).
        'tranches' => [
            ['jusqua' => 30000, 'taux' => 2.0],
            ['jusqua' => 50000, 'taux' => 15.0],
            ['jusqua' => 150000, 'taux' => 18.0],
            ['jusqua' => 600000, 'taux' => 20.0],
            ['jusqua' => null, 'taux' => 30.0],
        ],

        // Contrat de moins d'un mois civil : taux forfaitaire sur la totalité du brut,
        // à la place du barème progressif. La durée est déduite des dates du contrat.
        'taux_forfaitaire_contrat_court' => 15.0,
    ],

];
