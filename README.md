# Gestion du personnel

Application web de gestion des ressources humaines développée pour l'ANEFIP (Djibouti). Elle
centralise les dossiers du personnel, les contrats, les congés, la présence (pointeuse) et la
paie, avec un organigramme et des droits d'accès attribués compte par compte.

C'est un outil de **gestion du personnel**, pas un portail employé : les comptes sont réservés au
service du personnel, à la direction et à l'administrateur. La plupart des employés n'ont pas de
compte et sont gérés par le service du personnel.

> **Paie** : les bulletins ne doivent pas servir à un vrai versement de salaire tant qu'un
> comptable n'a pas confirmé les taux et un point ouvert sur l'IRPP. Voir
> [docs/points-a-verifier-paie.md](docs/points-a-verifier-paie.md).

## Fonctionnalités

- **Tableau de bord** : indicateurs en lecture seule, adaptés à la fonction de l'utilisateur.
- **Personnel** : fiche employé (état civil, coordonnées, photo), données sensibles protégées
  (pièce d'identité, CNSS, banque), dossier de pièces (contrat, diplôme, CV, certificat médical…)
  et historique disciplinaire et de carrière (avertissement, mise à pied, promotion, mutation,
  formation…).
- **Structure** : départements hiérarchiques (Direction, Division, Département, Service) avec
  responsable, et organigramme exportable en PDF.
- **Contrats** : CDI, CDD, stage, consultant, avec le salaire de base qui alimente la paie.
- **Congés** : congé annuel, maladie, sans solde, événement familial. Le circuit suit le
  document papier de l'agence : formulaire remis à l'employé, visa du chef, attestation et
  récapitulatif, signature du directeur général qui acte le congé. Les pièces signées sont
  conservées avec la demande. Les jours s'acquièrent chaque mois (2,5 jours, soit 30 par an), se
  décomptent en jours ouvrés (hors weekend et jours fériés) et se reportent d'une année sur l'autre.
- **Présence** : import des événements de la pointeuse (CSV) et de son annuaire (xlsx), saisie
  manuelle (qui prévaut sur l'import), rapport d'assiduité (présent, retard, absence, congé, mise
  à pied…), justificatifs d'absence avec pièce jointe et décision, calendrier des jours fériés.
- **Paie** : génération mensuelle de brouillons d'après le contrat, ajustements, validation,
  historique et bulletins PDF. Cotisations CNSS (parts salariale et patronale) et IRPP au barème
  progressif de Djibouti. Préparer et valider la paie sont deux droits distincts.
- **Comptes et droits** : l'administrateur attribue des droits (ou des profils pré-remplis) ;
  les positions de directeur général et de manager découlent de l'organigramme. Voir
  [docs/roles-et-droits.md](docs/roles-et-droits.md).
- **Paramètres** : nom, logo et couleurs de l'application, textes de la page de connexion,
  coordonnées et en-tête des documents, signataire, Direction générale, temps de travail
  (weekend, heure de début, tolérance de retard, jours de congé par mois) et ouverture de
  l'inscription publique (fermée par défaut).

## Technologies

Laravel 13 et PHP 8.3, Livewire 3 avec Volt, Tailwind CSS et Vite, MySQL 8, authentification
Laravel Breeze, [spatie/laravel-permission](https://spatie.be/docs/laravel-permission) pour les
rôles et droits, [laravel-dompdf](https://github.com/barryvdh/laravel-dompdf) pour les PDF.
Tests avec PHPUnit.

## Installation en développement

Prérequis : Docker et Node.js (le build des assets se fait hors du conteneur).

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan roles:assigner-admin test@example.com
npm install && npm run build
```

L'application répond sur <http://localhost:8000>. Le `--seed` crée un compte de test
(`test@example.com`, mot de passe `password`) : **réservé au développement**, il devient
administrateur avec la dernière commande. Pendant le développement, `npm run dev` lance Vite avec
rechargement à chaud.

Le service `scheduler` du `docker-compose.yml` exécute les tâches planifiées.

### Importer les données existantes

Pour repartir d'une sauvegarde (base et pièces jointes) plutôt que d'une base vide, après
l'installation ci-dessus. Les deux fichiers sont ignorés par git (`*.sql`, `*.tar.gz`) : ils
contiennent des données du personnel et ne doivent jamais être ajoutés au dépôt.

1. Placer `donnees.sql` (dump MySQL) et `fichiers.tar.gz` (dossier `storage/app/`) à la racine du
   projet, conteneurs démarrés (`docker compose up -d`).
2. Importer la base. Le dump supprime et recrée toutes les tables : les données actuelles, dont
   le compte de test du `--seed`, sont **remplacées**.

   ```bash
   docker compose exec -T mysql mysql -uroot -proot gestion_personnel < donnees.sql
   ```

3. Extraire les fichiers (photos, pièces des dossiers, justificatifs d'absence, formulaires de
   congé). L'archive contient des chemins `storage/app/...` et doit s'extraire depuis la racine du
   projet. `--exclude` conserve les `.gitignore` du dépôt.

   ```bash
   tar -xzf fichiers.tar.gz --exclude='.gitignore'
   ```

4. Appliquer les migrations éventuellement postérieures à la sauvegarde. La table `migrations` est
   importée avec le dump, seules les nouvelles sont exécutées.

   ```bash
   docker compose exec app php artisan migrate
   ```

Les comptes et mots de passe sont ceux de la sauvegarde : le compte `test@example.com` n'existe
plus. Pour donner les droits d'administration à un compte importé :
`docker compose exec app php artisan roles:assigner-admin email@exemple.com`.

## Tests

```bash
docker compose exec app php artisan test
```

Les tests utilisent SQLite en mémoire (voir `phpunit.xml`) et ne touchent pas à la base MySQL.

## Commandes artisan

| Commande | Rôle |
| --- | --- |
| `conges:accumuler-soldes` | Crédite les jours de congé du mois à chaque employé actif. Planifiée le 1er de chaque mois, sans doublon. |
| `conges:regulariser-soldes` | Complète les soldes manquants jusqu'au mois en cours. |
| `pointeuse:importer-annuaire fichier.xlsx` | Crée les fiches employés depuis l'annuaire de la pointeuse (`--simulation` pour tester). |
| `roles:assigner-admin email` | Attribue le rôle administrateur à un compte existant. |

## Configuration métier

- [config/personnel.php](config/personnel.php) : valeurs par défaut du temps de travail (weekend
  vendredi et samedi, début à 08:00, tolérance de 15 minutes, 2,5 jours de congé par mois). Les
  réglages faits dans l'écran *Paramètres* priment.
- [config/paie.php](config/paie.php) : taux CNSS et tranches de l'IRPP, jamais écrits en dur dans
  le code. Corriger ce fichier suffit quand la réglementation change.

## Mise en production

Une configuration Docker de production est fournie : PHP-FPM, nginx, MySQL, le scheduler et Caddy,
qui gère le HTTPS (certificat Let's Encrypt automatique, ou certificat local pour un réseau
interne). Au démarrage, les migrations sont appliquées.

```bash
cp .env.production.example .env.production
# renseigner APP_KEY, APP_URL, APP_DOMAIN, APP_TLS, DB_PASSWORD, DB_ROOT_PASSWORD et MAIL_*
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build
```

Première mise en service : les rôles, puis le premier compte administrateur. L'inscription publique
est fermée, le compte se crée donc en ligne de commande.

```bash
dc="docker compose -f docker-compose.prod.yml --env-file .env.production"
$dc exec -u www-data app php artisan db:seed --class=RoleSeeder --force
$dc exec -u www-data app php artisan db:seed --class=ParametresApplicationSeeder --force
$dc exec -u www-data -e HOME=/tmp app php artisan tinker --execute 'App\Models\User::create(["name" => "Administrateur", "email" => "admin@exemple.com", "password" => "mot-de-passe-fort"]);'
$dc exec -u www-data app php artisan roles:assigner-admin admin@exemple.com
```

Ne lancez pas `db:seed` sans `--class` en production : il créerait le compte de test.
Les données vivent dans les volumes Docker `mysql-data` (base) et `storage-data` (photos,
justificatifs, documents) : à sauvegarder régulièrement. Mise à jour : récupérer le nouveau code
(`git pull`), puis relancer la commande `up -d --build`.

## Documentation

- [docs/roles-et-droits.md](docs/roles-et-droits.md) : catalogue des droits, positions déduites de
  l'organigramme, circuit des congés, où chaque règle est appliquée dans le code.
- [docs/format-import-pointages.md](docs/format-import-pointages.md) : formats des fichiers de la
  pointeuse (CSV des pointages, xlsx de l'annuaire) et règles de traitement.
- [docs/points-a-verifier-paie.md](docs/points-a-verifier-paie.md) : sources des taux, point
  ouvert sur l'article 14 du CGI et hypothèses du calcul de paie.

## Organisation du code

| Dossier | Contenu |
| --- | --- |
| `app/Livewire/` | Écrans de l'application, un composant par page |
| `app/Services/` | Logique métier : calcul de paie, jours ouvrés, période de congé, import des pointages, organigramme |
| `app/Enums/` | Droits, profils, statuts et types (congé, présence, paie, contrat…) |
| `app/Policies/` | Règles d'accès par ressource |
| `app/Http/Controllers/` | Génération des PDF et téléchargement des pièces |
| `app/Console/Commands/` | Commandes artisan listées plus haut |
| `docker/` | Dockerfiles et configurations nginx, PHP et Caddy |
