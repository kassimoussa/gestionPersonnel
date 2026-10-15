<?php

namespace App\Enums;

/**
 * Statut calculé d'un employé pour un jour donné dans le rapport de présence.
 */
enum StatutPresence: string
{
    case Present = 'present';
    case Retard = 'retard';
    case Conge = 'conge';
    case MiseAPied = 'mise_a_pied';
    case Absence = 'absence';
    case AbsenceJustifiee = 'absence_justifiee';
    case EnCours = 'en_cours';
    case Weekend = 'weekend';
    case Ferie = 'ferie';
    case NonApplicable = 'non_applicable';

    public function libelle(): string
    {
        return match ($this) {
            self::Present => 'Présent',
            self::Retard => 'Retard',
            self::Conge => 'En congé',
            self::MiseAPied => 'Mise à pied',
            self::Absence => 'Absence non justifiée',
            self::AbsenceJustifiee => 'Absence justifiée',
            self::EnCours => 'En cours (jour courant, pas encore de pointage)',
            self::Weekend => 'Weekend',
            self::Ferie => 'Jour férié',
            self::NonApplicable => 'Hors période (avant embauche ou à venir)',
        };
    }

    public function abreviation(): string
    {
        return match ($this) {
            self::Present => 'P',
            self::Retard => 'R',
            self::Conge => 'C',
            self::MiseAPied => 'M',
            self::Absence => 'A',
            self::AbsenceJustifiee => 'J',
            self::EnCours => 'E',
            self::Weekend => 'W',
            self::Ferie => 'F',
            self::NonApplicable => '–',
        };
    }

    /**
     * Variante de couleur du système de badges commun (cellule du rapport et légende).
     */
    public function varianteCellule(): string
    {
        return match ($this) {
            self::Present => 'succes',
            self::Retard => 'attention',
            self::Conge => 'info',
            self::MiseAPied => 'danger',
            self::Absence => 'danger',
            self::AbsenceJustifiee => 'attention',
            self::EnCours => 'pointille',
            self::Weekend => 'neutre',
            self::Ferie => 'info',
            self::NonApplicable => 'vide',
        };
    }

    /**
     * Statuts additionnés dans les totaux du rapport. "En cours" n'est pas
     * comptabilisé : la journée n'est pas terminée.
     */
    public function estComptabilise(): bool
    {
        return in_array($this, [self::Present, self::Retard, self::Conge, self::MiseAPied, self::Absence, self::AbsenceJustifiee], true);
    }
}
