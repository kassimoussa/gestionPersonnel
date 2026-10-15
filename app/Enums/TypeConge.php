<?php

namespace App\Enums;

enum TypeConge: string
{
    case CongeAnnuel = 'conge_annuel';
    case Maladie = 'maladie';
    case SansSolde = 'sans_solde';
    case EvenementFamilial = 'evenement_familial';

    public function libelle(): string
    {
        return match ($this) {
            self::CongeAnnuel => 'Congé annuel',
            self::Maladie => 'Maladie',
            self::SansSolde => 'Sans solde',
            self::EvenementFamilial => 'Événement familial',
        };
    }

    /**
     * Formulation utilisée dans le corps de l'attestation :
     * « bénéficie de son congé annuel ».
     */
    public function libelleAttestation(): string
    {
        return match ($this) {
            self::CongeAnnuel => 'congé annuel',
            self::Maladie => 'congé de maladie',
            self::SansSolde => 'congé sans solde',
            self::EvenementFamilial => 'congé pour événement familial',
        };
    }

    /**
     * Seul le congé annuel consomme un solde ; les autres types n'ont
     * qu'un historique de demandes/validations.
     */
    public function consommeSolde(): bool
    {
        return $this === self::CongeAnnuel;
    }

    /** @return string[] */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
