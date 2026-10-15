<?php

namespace App\Enums;

/**
 * Jeux de droits prêts à l'emploi proposés dans « Comptes utilisateurs ».
 * Choisir un profil coche les cases correspondantes ; l'administrateur reste
 * libre de les ajuster ensuite, compte par compte.
 */
enum ProfilDroits: string
{
    case Rh = 'rh';
    case Paie = 'paie';
    case Presence = 'presence';
    case Administration = 'administration';

    public function libelle(): string
    {
        return match ($this) {
            self::Rh => 'Service du personnel',
            self::Paie => 'Paie',
            self::Presence => 'Présence',
            self::Administration => "Administration de l'application",
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Rh => 'Dossiers, contrats, présence, congés et préparation de la paie. Ne valide pas la paie.',
            self::Paie => 'Prépare les fiches de paie et consulte les dossiers, sans les modifier.',
            self::Presence => 'Import de la pointeuse, saisie et rapport de présence.',
            self::Administration => 'Structure, comptes et paramètres, sans accès aux dossiers ni à la paie.',
        };
    }

    /** @return Droit[] */
    public function droits(): array
    {
        return match ($this) {
            self::Rh => [
                Droit::PersonnelConsulter,
                Droit::PersonnelGerer,
                Droit::PersonnelDonneesSensibles,
                Droit::ContratsGerer,
                Droit::CongesSaisirPourAutrui,
                Droit::CongesAttestation,
                Droit::PresenceConsulter,
                Droit::PresenceSaisir,
                Droit::PresenceImporter,
                Droit::PaiePreparer,
            ],
            self::Paie => [
                Droit::PersonnelConsulter,
                Droit::PaiePreparer,
            ],
            self::Presence => [
                Droit::PersonnelConsulter,
                Droit::PresenceConsulter,
                Droit::PresenceSaisir,
                Droit::PresenceImporter,
            ],
            self::Administration => [
                Droit::StructureGerer,
                Droit::ComptesGerer,
                Droit::ParametresGerer,
            ],
        };
    }

    /** @return string[] */
    public function valeursDroits(): array
    {
        return array_map(fn (Droit $droit) => $droit->value, $this->droits());
    }
}
