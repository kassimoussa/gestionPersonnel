# Rôles et droits

Trois choses déterminent ce qu'un compte peut faire.

- **L'administrateur** (rôle `admin`) a **tous les droits**, sans exception, et il est le seul à
  pouvoir en attribuer aux autres comptes.
- **Les droits attribués** un par un depuis *Comptes utilisateurs*, dans un catalogue rangé par
  domaine (voir `app/Enums/Droit.php`). Des profils pré-remplis — Service du personnel, Paie,
  Présence, Administration — cochent un jeu de cases en un clic, que l'administrateur ajuste
  ensuite.
- **Les positions déduites de l'organigramme**, jamais saisies : le **directeur général** est le
  responsable de l'unité désignée comme Direction générale dans les *Paramètres*, et un
  **manager** est le responsable d'au moins une unité. Changer un responsable dans *Départements*
  transfère immédiatement les droits correspondants. Ces positions s'ajoutent aux droits cochés.

## Le catalogue des droits

| Groupe | Droit | Ce qu'il ouvre |
| --- | --- | --- |
| Personnel | `personnel.consulter` | Dossiers de tout l'effectif, et périmètre global en présence et en congés |
| Personnel | `personnel.gerer` | Créer, modifier et désactiver une fiche |
| Personnel | `personnel.donnees-sensibles` | Pièce d'identité, CNSS, banque |
| Personnel | `contrats.gerer` | Contrats et salaires |
| Congés | `conges.saisir-pour-autrui` | Ouvrir une demande et remettre le formulaire à un employé |
| Congés | `conges.valider` | Enregistrer la signature du directeur général, qui acte le congé |
| Congés | `conges.attestation` | Enregistrer le visa du chef et établir l'attestation |
| Présence | `presence.consulter` | Rapport de présence |
| Présence | `presence.saisir` | Saisie et correction des pointages |
| Présence | `presence.importer` | Import du fichier de la pointeuse |
| Paie | `paie.preparer` | Générer et ajuster les brouillons, historique, tous les bulletins |
| Paie | `paie.valider` | Valider les fiches de paie |
| Administration | `structure.gerer` | Structure des départements |
| Administration | `comptes.gerer` | Créer et modifier les comptes, sans attribuer de droits |
| Administration | `parametres.gerer` | Paramètres de l'application et calendrier des jours fériés |

L'application est un outil de **gestion du personnel** : il n'y a pas d'espace personnel. Aucun
écran « mes congés », « mes absences » ou « mes bulletins » : tout est traité de la même manière
pour tout le monde, par le service du personnel. Les comptes sont donc réservés au service du
personnel, à la direction et à l'administrateur ; la grande majorité des employés n'en a pas.

Sans aucun droit ni position, un compte ne voit que le tableau de bord, l'organigramme, l'annuaire
des départements et sa propre page de profil (nom, e-mail, mot de passe).

## Ce que les positions apportent d'office

| Fonction | Compte sans droit | Manager | Directeur général | Administrateur |
| --- | --- | --- | --- | --- |
| Tableau de bord, organigramme, annuaire des départements, son profil | oui | oui | oui | oui |
| Consulter les dossiers | non | son équipe | tous | tous |
| Créer, modifier, désactiver une fiche | non | non | oui | oui |
| Données sensibles, contrats et salaires | non | non | oui | oui |
| Ouvrir la fiche détaillée d'un employé | non | son équipe | tous | tous |
| Voir et télécharger les pièces du dossier | non | non | oui | oui |
| Ajouter ou retirer une pièce du dossier | non | non | oui | oui |
| Voir le dossier disciplinaire (sanctions, mises à pied) | non | non | oui | oui |
| Consigner une sanction ou un fait de carrière | non | non | oui | oui |
| Ouvrir une demande de congé et remettre le formulaire | non | son équipe | tous | tous |
| Conduire le circuit : visa, attestation, signature, refus | non | non | tous | tous |
| Télécharger les pièces d'un congé (formulaire, attestation) | non | non | tous | tous |
| Présence | non | son équipe | tous | tous |
| Enregistrer une justification d'absence et sa pièce | non | son équipe | tous | tous |
| Accepter ou refuser une justification | non | son équipe | tous | tous |
| Import pointeuse | non | non | oui | oui |
| Préparer la paie, consulter les bulletins | non | non | oui | oui |
| Valider la paie | non | non | oui | oui |
| Structure des départements | lecture | lecture | lecture | gestion |
| Comptes utilisateurs, paramètres | non | non | non | oui |

Personne ne traite son propre dossier : un compte qui a des droits ne peut pas trancher la
justification d'absence de sa propre fiche employé.

Le directeur général tient donc tout du poste : nommer quelqu'un d'autre responsable de la
Direction générale lui transfère ces droits le jour même, sans toucher aux comptes.

La préparation et la validation de la paie restent séparées : un compte peut préparer sans
valider. C'est le sens du profil *Service du personnel*, qui donne `paie.preparer` sans
`paie.valider`.

Les congés suivent le circuit papier de l'agence, conduit de bout en bout par le service du
personnel : il ouvre la demande et remet le formulaire pré-rempli, l'employé le fait viser par son
chef et le rapporte, le service enregistre le visa puis établit l'attestation et le récapitulatif,
le directeur général signe, et l'enregistrement de cette signature acte le congé. Les pièces
signées — formulaire visé, attestation signée — sont conservées avec la demande. Tant que le congé
n'est pas acté, les jours sont réservés sans être décomptés.

## Où c'est appliqué dans le code

| Règle | Fichier |
| --- | --- |
| Catalogue et profils | `app/Enums/Droit.php`, `app/Enums/ProfilDroits.php` |
| Droits et positions | `app/Models/User.php` (`aLeDroit`, `estAdmin`, `estDg`, `estManager`, puis les `peut…()`) |
| Attribution des droits | `app/Livewire/Comptes/Index.php` |
| Fiches employés | `app/Policies/EmployePolicy.php` (`consulter`, `gerer`, `voir`, `voirLaFiche`) |
| Documents du dossier | `app/Policies/DocumentEmployePolicy.php`, téléchargement par `app/Http/Controllers/DocumentEmployeController.php` |
| Sanctions et faits de carrière | `app/Policies/EvenementEmployePolicy.php`, `app/Enums/TypeEvenementEmploye.php` |
| Contrats | `app/Policies/ContratPolicy.php` |
| Paie | `app/Policies/FichePaiePolicy.php` (`preparer`, `valider`, `voir`) |
| Présence | `app/Policies/PointagePolicy.php` |
| Justificatifs d'absence | `app/Policies/JustificatifAbsencePolicy.php` (`creerPour`, `decider`, `voirLaPiece`, `retirer`) |
| Congés | `app/Policies/CongePolicy.php` (`creerPour`, `conduire`, `enregistrerCommeAccorde`, `annuler`, `telechargerLesDocuments`) |
| Étapes du circuit | `app/Enums/StatutConge.php`, `app/Models/Conge.php` (`enregistrerLeVisa`, `etablirAttestation…`, `acterPar`, `refuserPar`) |
| Pièces du circuit | `app/Http/Controllers/FormulaireCongePdfController.php`, `RecapitulatifCongesPdfController.php`, `AttestationCongePdfController.php`, `PieceCongeController.php` |
| Décompte en jours ouvrés et date de reprise | `app/Services/Conges/CalculPeriodeConge.php`, `app/Services/CalculJoursOuvres.php` |
| Périmètre d'un manager | `User::employesSupervises()` et `User::departementsSupervises()` |

## Comptes désactivés

Désactiver un employé (case « Employé actif » ou action *Désactiver*) coupe l'accès de son compte :
la connexion est refusée et une session déjà ouverte est fermée au premier écran protégé
(middleware `compte.actif`). Le compte est conservé, ainsi que le dossier de l'employé — congés,
présence et bulletins. Réactiver l'employé rétablit l'accès.

## Attribuer les droits

- Depuis *Comptes utilisateurs*, bouton *Modifier* : interrupteur « Administrateur » ou cases du
  catalogue, avec les profils pour aller vite.
- Retirer une case ferme l'accès correspondant à la connexion suivante de la personne.
- Un compte à qui l'on donne `comptes.gerer` peut créer des comptes mais pas distribuer de droits :
  cette partie du formulaire ne s'affiche que pour un administrateur.
- En ligne de commande pour le premier administrateur :
  `docker compose exec app php artisan roles:assigner-admin admin@rh.test`
- Il doit toujours rester au moins un administrateur : l'application refuse de retirer le dernier.
