<?php

namespace App\Console\Commands;

use App\Enums\TypeDepartement;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\ParametresApplication;
use App\Services\LecteurClasseurXlsx;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Crée les fiches employés à partir de l'annuaire exporté par la pointeuse
 * (export « Employee » : identifiant, nom complet, service). Ce fichier ne contient
 * aucun pointage : il sert à constituer l'effectif et le lien fiche ↔ pointeuse.
 *
 *   docker compose exec app php artisan pointeuse:importer-annuaire fichier.xlsx --simulation
 *   docker compose exec app php artisan pointeuse:importer-annuaire fichier.xlsx
 */
class ImporterAnnuairePointeuse extends Command
{
    protected $signature = 'pointeuse:importer-annuaire
        {fichier : Chemin du classeur .xlsx exporté par la pointeuse}
        {--simulation : Affiche ce qui serait fait sans rien enregistrer}';

    protected $description = 'Crée les fiches employés à partir de l\'annuaire de la pointeuse (identifiant, nom, service)';

    /** Valeur par défaut de la machine quand aucun service n'est renseigné. */
    private const SERVICE_NON_RENSEIGNE = 'department';

    /** @var array<string, Departement|null> */
    private array $unites = [];

    /** @var array<int, array{0: string, 1: string}> */
    private array $unitesCreees = [];

    public function handle(LecteurClasseurXlsx $lecteur): int
    {
        $fichier = (string) $this->argument('fichier');

        if (! is_file($fichier)) {
            $this->error("Fichier introuvable : {$fichier}");

            return self::INVALID;
        }

        try {
            $lignes = $lecteur->lignes($fichier);
        } catch (Throwable $erreur) {
            $this->error($erreur->getMessage());

            return self::FAILURE;
        }

        $colonnes = $this->colonnes($lignes);

        if ($colonnes === null) {
            $this->error('En-têtes introuvables : le classeur doit comporter les colonnes « Employee ID », « First Name » et « Department ».');

            return self::INVALID;
        }

        $simulation = (bool) $this->option('simulation');
        $existants = Employe::query()->get(['id', 'nom', 'prenom', 'matricule', 'matricule_pointeuse']);
        $crees = 0;
        $rattaches = 0;
        $ignores = 0;
        $apercu = [];

        foreach (array_slice($lignes, $colonnes['ligne'] + 1) as $ligne) {
            $matricule = trim($ligne[$colonnes['matricule']] ?? '');
            $nomComplet = trim($ligne[$colonnes['nom']] ?? '');

            if ($matricule === '' || $nomComplet === '') {
                continue;
            }

            [$prenom, $nom] = $this->separerLeNom($nomComplet);
            $service = trim($ligne[$colonnes['service']] ?? '');

            $dejaLie = $existants->first(fn (Employe $e) => (string) $e->matricule_pointeuse === $matricule);

            if ($dejaLie !== null) {
                $ignores++;

                continue;
            }

            $memeNom = $this->memeNom($existants, $prenom, $nom);
            $unite = $simulation ? $this->uniteSimulee($service) : $this->unite($service);

            if ($memeNom !== null) {
                $rattaches++;
                $apercu[] = [$matricule, $nomComplet, $service ?: '—', 'fiche existante complétée'];

                if (! $simulation) {
                    $memeNom->update([
                        'matricule_pointeuse' => $matricule,
                        'matricule' => $memeNom->matricule ?: $matricule,
                    ]);
                }

                continue;
            }

            $crees++;
            $apercu[] = [$matricule, "{$prenom} {$nom}", $unite?->nom ?? 'aucune unité', 'nouvelle fiche'];

            if ($simulation) {
                continue;
            }

            DB::transaction(function () use ($prenom, $nom, $matricule, $unite, &$existants): void {
                $employe = Employe::create([
                    'prenom' => $prenom,
                    'nom' => $nom,
                    // Le matricule de l'employé et son identifiant dans la pointeuse
                    // sont le même numéro.
                    'matricule' => $matricule,
                    'matricule_pointeuse' => $matricule,
                    'departement_id' => $unite?->id,
                    'actif' => true,
                ]);

                $existants->push($employe);
            });
        }

        $this->afficherLeBilan($apercu, $crees, $rattaches, $ignores, $simulation);

        return self::SUCCESS;
    }

    /**
     * Repère la ligne d'en-têtes et la position des trois colonnes utiles.
     *
     * @param  array<int, array<int, string>>  $lignes
     * @return array{ligne: int, matricule: int, nom: int, service: int}|null
     */
    private function colonnes(array $lignes): ?array
    {
        foreach ($lignes as $index => $ligne) {
            $entetes = array_map(fn (string $valeur) => $this->normaliser($valeur), $ligne);

            $matricule = array_search('employee id', $entetes, true);
            $nom = array_search('first name', $entetes, true);

            if ($matricule === false || $nom === false) {
                continue;
            }

            $service = array_search('department', $entetes, true);

            return [
                'ligne' => $index,
                'matricule' => (int) $matricule,
                'nom' => (int) $nom,
                'service' => $service === false ? PHP_INT_MAX : (int) $service,
            ];
        }

        return null;
    }

    /**
     * « IBADO BOUH GUESSOD » donne le prénom Ibado et le nom Bouh Guessod.
     *
     * @return array{0: string, 1: string}
     */
    private function separerLeNom(string $nomComplet): array
    {
        $morceaux = preg_split('/\s+/', trim($nomComplet)) ?: [];
        $morceaux = array_map(fn (string $mot) => mb_convert_case($mot, MB_CASE_TITLE, 'UTF-8'), $morceaux);

        $prenom = array_shift($morceaux) ?? '';
        $nom = implode(' ', $morceaux);

        return [$prenom, $nom !== '' ? $nom : $prenom];
    }

    /**
     * @param  Collection<int, Employe>  $existants
     */
    private function memeNom(Collection $existants, string $prenom, string $nom): ?Employe
    {
        $recherche = $this->normaliser("{$prenom} {$nom}");

        return $existants->first(fn (Employe $employe) => $this->normaliser($employe->nomComplet()) === $recherche
            && $employe->matricule_pointeuse === null);
    }

    /**
     * Retrouve l'unité correspondant au service de la pointeuse, ou la crée.
     * Les libellés sont comparés sans accent, sans casse et sans le préfixe
     * « Direction / Service / Département », pour que « Service Informatique »
     * retrouve l'unité « Informatique » déjà enregistrée.
     */
    private function unite(string $libelle): ?Departement
    {
        $cle = $this->clefUnite($libelle);

        if ($cle === null) {
            return null;
        }

        if (array_key_exists($cle, $this->unites)) {
            return $this->unites[$cle];
        }

        $existante = Departement::all()->first(fn (Departement $unite) => $this->clefUnite($unite->nom) === $cle);

        if ($existante !== null) {
            return $this->unites[$cle] = $existante;
        }

        $estDirection = str_starts_with($this->normaliser($libelle), 'direction');
        $parent = $estDirection ? null : $this->directionDesServices();

        $unite = Departement::create([
            'nom' => $this->nettoyerLibelle($libelle),
            'type' => $parent === null ? TypeDepartement::Direction : TypeDepartement::Service,
            'parent_departement_id' => $parent?->id,
        ]);

        $this->unitesCreees[] = [$unite->nom, $unite->type->libelle().($parent ? ' rattaché à '.$parent->nom : ' au sommet')];

        return $this->unites[$cle] = $unite;
    }

    /**
     * En simulation, aucune unité n'est créée : seul le nom est affiché.
     */
    private function uniteSimulee(string $libelle): ?Departement
    {
        $cle = $this->clefUnite($libelle);

        if ($cle === null) {
            return null;
        }

        return Departement::all()->first(fn (Departement $unite) => $this->clefUnite($unite->nom) === $cle)
            ?? new Departement(['nom' => $this->nettoyerLibelle($libelle).' (à créer)']);
    }

    private function directionDesServices(): ?Departement
    {
        $parametres = ParametresApplication::courants();

        return $parametres->directionGenerale
            ?? Departement::query()->where('type', TypeDepartement::Direction)->orderBy('id')->first();
    }

    /**
     * Clé de comparaison d'une unité, ou null quand le service n'est pas renseigné.
     */
    private function clefUnite(string $libelle): ?string
    {
        $normalise = $this->normaliser($libelle);

        if ($normalise === '' || $normalise === self::SERVICE_NON_RENSEIGNE) {
            return null;
        }

        return trim(preg_replace('/^(direction|service|departement|division)\s+(de\s+|du\s+|des\s+)?/', '', $normalise) ?? $normalise);
    }

    private function nettoyerLibelle(string $libelle): string
    {
        return trim(preg_replace('/\s+/', ' ', $libelle) ?? $libelle);
    }

    private function normaliser(string $valeur): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower(Str::ascii($valeur))) ?? '');
    }

    /**
     * @param  array<int, array<int, string>>  $apercu
     */
    private function afficherLeBilan(array $apercu, int $crees, int $rattaches, int $ignores, bool $simulation): void
    {
        if ($apercu !== []) {
            $this->table(['Matricule', 'Employé', 'Unité', 'Action'], array_slice($apercu, 0, 15));

            if (count($apercu) > 15) {
                $this->line(sprintf('  ... et %d autre(s).', count($apercu) - 15));
            }
        }

        if ($this->unitesCreees !== []) {
            $this->newLine();
            $this->table(['Unité créée', 'Type'], $this->unitesCreees);
        }

        $this->newLine();
        $this->info(sprintf('%d fiche(s) %s.', $crees, $simulation ? 'à créer (simulation)' : 'créée(s)'));
        $this->info(sprintf('%d rattachée(s) à une fiche existante.', $rattaches));
        $this->info(sprintf('%d déjà liée(s) à la pointeuse.', $ignores));
    }
}
