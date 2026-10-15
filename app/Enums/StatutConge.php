<?php

namespace App\Enums;

/**
 * Étapes du circuit de congé, tel qu'il se déroule à l'ANEFIP : le service du
 * personnel remet le formulaire, le chef le vise, le RH établit l'attestation,
 * le directeur général la signe et le congé est acté.
 */
enum StatutConge: string
{
    case FormulaireRemis = 'formulaire_remis';
    case ViseParLeChef = 'vise_par_le_chef';
    case AttestationASigner = 'attestation_a_signer';
    case Acte = 'acte';
    case Refuse = 'refuse';
    case Annule = 'annule';

    public function libelle(): string
    {
        return match ($this) {
            self::FormulaireRemis => 'Formulaire à faire viser',
            self::ViseParLeChef => 'Visé par le chef',
            self::AttestationASigner => 'Attestation à faire signer',
            self::Acte => 'Congé acté',
            self::Refuse => 'Refusé',
            self::Annule => 'Annulé',
        };
    }

    /**
     * Ce qu'il reste à faire, affiché au service du personnel.
     */
    public function prochaineEtape(): ?string
    {
        return match ($this) {
            self::FormulaireRemis => 'Remettre le formulaire à l\'employé, puis enregistrer le visa du chef',
            self::ViseParLeChef => 'Établir l\'attestation et le récapitulatif pour la direction',
            self::AttestationASigner => 'Faire signer l\'attestation, puis enregistrer la signature',
            default => null,
        };
    }

    /**
     * Le congé est acquis : il est décompté du solde de l'employé.
     */
    public function estActe(): bool
    {
        return $this === self::Acte;
    }

    /**
     * La demande suit son cours : elle réserve le solde sans le consommer.
     */
    public function estEnCours(): bool
    {
        return in_array($this, [self::FormulaireRemis, self::ViseParLeChef, self::AttestationASigner], true);
    }

    public function estClos(): bool
    {
        return in_array($this, [self::Refuse, self::Annule], true);
    }

    /**
     * Variante du composant <x-badge> (système de badges commun à toute l'application).
     */
    public function varianteBadge(): string
    {
        return match ($this) {
            self::FormulaireRemis => 'neutre',
            self::ViseParLeChef => 'info',
            self::AttestationASigner => 'attention',
            self::Acte => 'succes',
            self::Refuse => 'danger',
            self::Annule => 'neutre',
        };
    }

    /** @return self[] */
    public static function enCours(): array
    {
        return [self::FormulaireRemis, self::ViseParLeChef, self::AttestationASigner];
    }

    /** @return string[] */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
