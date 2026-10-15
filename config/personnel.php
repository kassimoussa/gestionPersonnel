<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Jours de weekend
    |--------------------------------------------------------------------------
    |
    | Numéros ISO des jours de la semaine considérés comme non ouvrés
    | (1 = lundi, 2 = mardi, 3 = mercredi, 4 = jeudi, 5 = vendredi,
    | 6 = samedi, 7 = dimanche). À Djibouti, le weekend est vendredi-samedi.
    |
    */

    'jours_weekend' => [5, 6],

    /*
    |--------------------------------------------------------------------------
    | Congés
    |--------------------------------------------------------------------------
    */

    'conges' => [
        // Jours de congé annuel acquis chaque mois par employé actif : 2,5 jours
        // ouvrés, soit 30 jours par an. Un congé se décompte en jours travaillés :
        // ni les jours de weekend ci-dessus, ni les jours fériés enregistrés
        // (table jours_feries) ne sont retirés du solde. Les jours non pris ne sont
        // pas perdus au 31 décembre : ils se reportent d'une année sur l'autre.
        'jours_acquis_par_mois' => 2.5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Présence
    |--------------------------------------------------------------------------
    |
    | Un pointage est en retard si l'heure d'arrivée dépasse
    | heure_debut_travail + tolerance_retard_minutes.
    |
    */

    'heure_debut_travail' => '08:00',

    'tolerance_retard_minutes' => 15,

];
