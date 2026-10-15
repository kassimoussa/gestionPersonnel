<?php

namespace App\Enums;

/**
 * Nature d'une pièce rangée dans le dossier d'un employé.
 */
enum TypeDocumentEmploye: string
{
    case Contrat = 'contrat';
    case PieceIdentite = 'piece_identite';
    case Diplome = 'diplome';
    case Cv = 'cv';
    case CertificatMedical = 'certificat_medical';
    case Attestation = 'attestation';
    case Correspondance = 'correspondance';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::Contrat => 'Contrat',
            self::PieceIdentite => "Pièce d'identité",
            self::Diplome => 'Diplôme ou attestation de formation',
            self::Cv => 'CV',
            self::CertificatMedical => 'Certificat médical',
            self::Attestation => 'Attestation délivrée',
            self::Correspondance => 'Courrier',
            self::Autre => 'Autre document',
        };
    }

    /**
     * Variante du composant <x-badge> (système de badges commun à toute l'application).
     */
    public function varianteBadge(): string
    {
        return match ($this) {
            self::Contrat => 'accent',
            self::PieceIdentite, self::CertificatMedical => 'attention',
            self::Diplome, self::Attestation => 'info',
            default => 'neutre',
        };
    }

    /** @return string[] */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
