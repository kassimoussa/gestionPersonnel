<?php

namespace App\Enums;

/**
 * Catalogue des droits attribués compte par compte par l'administrateur, dans
 * « Comptes utilisateurs ». L'administrateur les possède tous d'office, et les
 * positions déduites de l'organigramme (directeur général, manager) ajoutent les
 * leurs par-dessus.
 */
enum Droit: string
{
    case PersonnelConsulter = 'personnel.consulter';
    case PersonnelGerer = 'personnel.gerer';
    case PersonnelDonneesSensibles = 'personnel.donnees-sensibles';
    case ContratsGerer = 'contrats.gerer';

    case CongesSaisirPourAutrui = 'conges.saisir-pour-autrui';
    case CongesValider = 'conges.valider';
    case CongesAttestation = 'conges.attestation';

    case PresenceConsulter = 'presence.consulter';
    case PresenceSaisir = 'presence.saisir';
    case PresenceImporter = 'presence.importer';

    case PaiePreparer = 'paie.preparer';
    case PaieValider = 'paie.valider';

    case StructureGerer = 'structure.gerer';
    case ComptesGerer = 'comptes.gerer';
    case ParametresGerer = 'parametres.gerer';

    public const GROUPE_PERSONNEL = 'Personnel';

    public const GROUPE_CONGES = 'Congés';

    public const GROUPE_PRESENCE = 'Présence';

    public const GROUPE_PAIE = 'Paie';

    public const GROUPE_ADMINISTRATION = 'Administration';

    public function libelle(): string
    {
        return match ($this) {
            self::PersonnelConsulter => 'Consulter les dossiers du personnel',
            self::PersonnelGerer => 'Créer, modifier et désactiver les fiches',
            self::PersonnelDonneesSensibles => 'Voir les données sensibles',
            self::ContratsGerer => 'Gérer les contrats et les salaires',
            self::CongesSaisirPourAutrui => 'Saisir une demande de congé pour un employé',
            self::CongesValider => 'Valider les congés en dernier ressort',
            self::CongesAttestation => "Établir l'attestation de congé",
            self::PresenceConsulter => 'Consulter le rapport de présence',
            self::PresenceSaisir => 'Saisir et corriger les pointages',
            self::PresenceImporter => 'Importer les pointages de la pointeuse',
            self::PaiePreparer => 'Préparer les fiches de paie',
            self::PaieValider => 'Valider les fiches de paie',
            self::StructureGerer => 'Modifier la structure des départements',
            self::ComptesGerer => 'Gérer les comptes utilisateurs',
            self::ParametresGerer => "Modifier les paramètres de l'application",
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PersonnelConsulter => "Donne la vue sur tout l'effectif, y compris en présence et en congés. Sans ce droit, un manager ne voit que son équipe.",
            self::PersonnelGerer => 'Ajouter un employé, modifier son dossier, le désactiver.',
            self::PersonnelDonneesSensibles => "Pièce d'identité, numéro CNSS, banque et compte bancaire.",
            self::ContratsGerer => 'Les contrats portent les salaires servant au calcul de la paie.',
            self::CongesSaisirPourAutrui => "Indispensable pour les employés qui n'ont pas de compte utilisateur.",
            self::CongesValider => '2e étape du circuit, celle de la direction. Un manager valide déjà la 1re étape pour son équipe, sans ce droit.',
            self::CongesAttestation => 'Éditer et numéroter le document remis à l\'employé.',
            self::PresenceConsulter => "Rapport d'assiduité, retards et absences.",
            self::PresenceSaisir => 'Ajouter ou corriger un pointage en cas d\'oubli de badge.',
            self::PresenceImporter => 'Charger le fichier de la pointeuse.',
            self::PaiePreparer => 'Générer, ajuster et supprimer des brouillons, consulter tous les bulletins.',
            self::PaieValider => 'Engage les montants : à réserver à la direction.',
            self::StructureGerer => 'Créer les unités, les rattacher entre elles, nommer les responsables.',
            self::ComptesGerer => 'Créer un compte et le lier à une fiche employé. Seul un administrateur attribue les droits.',
            self::ParametresGerer => 'Identité visuelle, temps de travail, documents, Direction générale.',
        };
    }

    public function groupe(): string
    {
        return match ($this) {
            self::PersonnelConsulter, self::PersonnelGerer, self::PersonnelDonneesSensibles, self::ContratsGerer => self::GROUPE_PERSONNEL,
            self::CongesSaisirPourAutrui, self::CongesValider, self::CongesAttestation => self::GROUPE_CONGES,
            self::PresenceConsulter, self::PresenceSaisir, self::PresenceImporter => self::GROUPE_PRESENCE,
            self::PaiePreparer, self::PaieValider => self::GROUPE_PAIE,
            self::StructureGerer, self::ComptesGerer, self::ParametresGerer => self::GROUPE_ADMINISTRATION,
        };
    }

    /**
     * Droits rangés par groupe, dans l'ordre d'affichage du formulaire.
     *
     * @return array<string, self[]>
     */
    public static function parGroupe(): array
    {
        $groupes = [];

        foreach (self::cases() as $droit) {
            $groupes[$droit->groupe()][] = $droit;
        }

        return $groupes;
    }

    /** @return string[] */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
