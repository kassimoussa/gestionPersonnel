<?php

namespace App\Services\Paie;

use App\Models\Contrat;
use App\Models\Employe;
use App\Models\FichePaie;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Calcul de la paie mensuelle djiboutienne (CNSS + IRPP) à partir de config/paie.php.
 *
 * Aucun taux n'est écrit ici : tout vient de la configuration, injectable pour les tests.
 * Les méthodes calculerDepuisMontants() et estContratCourt() n'ont besoin d'aucune base de données.
 *
 * TODO (à confirmer par un comptable avant tout versement réel) : l'article 14 du CGI indique
 * que « toute fraction de revenu inférieure à 5 000 FD est négligée ». Cela peut signifier une
 * exonération sous ce seuil ou un arrondi de la base imposable à la tranche de 5 000 FD
 * inférieure. Aucune de ces deux interprétations n'est appliquée : le barème progressif est
 * calculé sans arrondi ni exonération. Voir docs/points-a-verifier-paie.md.
 */
class PaieCalculateur
{
    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param  array<string, mixed>|null  $config  Contenu de config/paie.php (par défaut : la config de l'application)
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('paie');
    }

    /**
     * Paie d'un employé pour un mois donné, d'après le contrat en vigueur sur ce mois.
     *
     * @throws AucunContratActif
     */
    public function calculer(Employe $employe, int $annee, int $mois, float $primes, float $indemnites): MontantsPaie
    {
        $contrat = $this->contratPour($employe, $annee, $mois) ?? throw new AucunContratActif($employe, $annee, $mois);

        return $this->calculerDepuisMontants(
            (float) $contrat->salaire_base,
            $primes,
            $indemnites,
            $this->estContratCourt($contrat->date_debut, $contrat->date_fin),
        );
    }

    /**
     * Recalcule une fiche existante avec de nouvelles primes/indemnités, en conservant
     * le salaire de base figé à la génération.
     */
    public function recalculer(FichePaie $fiche, float $primes, float $indemnites): MontantsPaie
    {
        $contrat = $fiche->employe !== null ? $this->contratPour($fiche->employe, $fiche->annee, $fiche->mois) : null;
        $contratCourt = $contrat !== null && $this->estContratCourt($contrat->date_debut, $contrat->date_fin);

        return $this->calculerDepuisMontants((float) $fiche->salaire_base, $primes, $indemnites, $contratCourt);
    }

    /**
     * Cœur du calcul, indépendant de la base de données.
     */
    public function calculerDepuisMontants(float $salaireBase, float $primes, float $indemnites, bool $contratCourt = false): MontantsPaie
    {
        $brut = round($salaireBase + $primes + $indemnites, 2);

        $assietteCnss = $this->assietteCnss($brut);
        $cnssSalarie = round($assietteCnss * $this->tauxTotal('salarie') / 100, 2);
        $cnssEmployeur = round($assietteCnss * $this->tauxTotal('employeur') / 100, 2);

        // Les cotisations salariales sont déductibles avant impôt.
        $baseImposable = max(0.0, round($brut - $cnssSalarie, 2));

        if ($contratCourt) {
            $irpp = round($brut * (float) $this->config['irpp']['taux_forfaitaire_contrat_court'] / 100, 2);
            $tranches = [];
        } else {
            [$irpp, $tranches] = $this->irppProgressif($baseImposable);
        }

        return new MontantsPaie(
            salaireBase: round($salaireBase, 2),
            primes: round($primes, 2),
            indemnites: round($indemnites, 2),
            brut: $brut,
            assietteCnss: $assietteCnss,
            cnssSalarie: $cnssSalarie,
            cnssEmployeur: $cnssEmployeur,
            baseImposable: $baseImposable,
            irpp: $irpp,
            netAPayer: round($baseImposable - $irpp, 2),
            contratCourt: $contratCourt,
            tranchesIrpp: $tranches,
        );
    }

    /**
     * Assiette CNSS : le brut, relevé au plancher de cotisation (aucun plafond identifié).
     */
    public function assietteCnss(float $brut): float
    {
        $assiette = max($brut, (float) $this->config['cnss']['assiette_minimale']);
        $plafond = $this->config['cnss']['plafond'] ?? null;

        return $plafond === null ? $assiette : min($assiette, (float) $plafond);
    }

    /**
     * Décomposition des cotisations par branche, pour l'affichage du bulletin.
     *
     * @return array{assiette: float, salarie: array<string, array{libelle: string, taux: float, montant: float}>, employeur: array<string, array{libelle: string, taux: float, montant: float}>}
     */
    public function detailCnss(float $brut): array
    {
        $assiette = $this->assietteCnss($brut);
        $detail = ['assiette' => $assiette, 'salarie' => [], 'employeur' => []];

        foreach (['salarie', 'employeur'] as $part) {
            foreach ($this->config['cnss'][$part] as $branche => $taux) {
                $detail[$part][$branche] = [
                    'libelle' => $this->config['cnss']['libelles_branches'][$branche] ?? ucfirst(str_replace('_', ' ', $branche)),
                    'taux' => (float) $taux,
                    'montant' => round($assiette * (float) $taux / 100, 2),
                ];
            }
        }

        return $detail;
    }

    /**
     * Détail IRPP d'une fiche enregistrée : barème par tranche ou taux forfaitaire.
     * Si le montant recalculé ne correspond plus au montant figé (contrat modifié après
     * coup), seul le total enregistré fait foi et aucun détail n'est fourni.
     *
     * @return array{mode: 'progressif'|'forfaitaire'|'indisponible', taux_forfaitaire: ?float, tranches: array<int, array{de: float, a: ?float, taux: float, base: float, montant: float}>}
     */
    public function detailIrppPourFiche(FichePaie $fiche): array
    {
        $montants = $this->recalculer($fiche, (float) $fiche->primes, (float) $fiche->indemnites);

        if (abs($montants->irpp - (float) $fiche->irpp) > 0.005) {
            return ['mode' => 'indisponible', 'taux_forfaitaire' => null, 'tranches' => []];
        }

        if ($montants->contratCourt) {
            return [
                'mode' => 'forfaitaire',
                'taux_forfaitaire' => (float) $this->config['irpp']['taux_forfaitaire_contrat_court'],
                'tranches' => [],
            ];
        }

        return ['mode' => 'progressif', 'taux_forfaitaire' => null, 'tranches' => $montants->tranchesIrpp];
    }

    /**
     * Contrat de moins d'un mois civil : durée inclusive strictement inférieure à un mois.
     * Du 1er au 30 septembre = un mois entier (pas court) ; du 1er au 29 septembre = court.
     * Un contrat sans date de fin n'est jamais court.
     */
    public function estContratCourt(DateTimeInterface|string $dateDebut, DateTimeInterface|string|null $dateFin): bool
    {
        if ($dateFin === null) {
            return false;
        }

        $debut = CarbonImmutable::parse($dateDebut)->startOfDay();
        $fin = CarbonImmutable::parse($dateFin)->startOfDay();

        return $fin->lessThan($debut->addMonthNoOverflow()->subDay());
    }

    /**
     * Contrat en vigueur sur le mois demandé (le plus récent s'il y en a plusieurs).
     * Utilise la relation `contrats` déjà chargée si elle l'est.
     */
    public function contratPour(Employe $employe, int $annee, int $mois): ?Contrat
    {
        $debutMois = CarbonImmutable::create($annee, $mois, 1)->startOfDay();
        $finMois = $debutMois->endOfMonth();

        return $employe->contrats
            ->filter(function (Contrat $contrat) use ($debutMois, $finMois): bool {
                $debut = CarbonImmutable::parse($contrat->date_debut)->startOfDay();
                $fin = $contrat->date_fin === null ? null : CarbonImmutable::parse($contrat->date_fin)->startOfDay();

                return $debut->lessThanOrEqualTo($finMois) && ($fin === null || $fin->greaterThanOrEqualTo($debutMois));
            })
            ->sortByDesc(fn (Contrat $contrat) => CarbonImmutable::parse($contrat->date_debut)->timestamp)
            ->first();
    }

    /**
     * Barème progressif par tranches, sans arrondi ni exonération (voir TODO en tête de classe).
     *
     * @return array{0: float, 1: array<int, array{de: float, a: ?float, taux: float, base: float, montant: float}>}
     */
    private function irppProgressif(float $baseImposable): array
    {
        $total = 0.0;
        $detail = [];
        $plancher = 0.0;

        foreach ($this->config['irpp']['tranches'] as $tranche) {
            $plafond = $tranche['jusqua'] === null ? null : (float) $tranche['jusqua'];
            $haut = $plafond === null ? $baseImposable : min($baseImposable, $plafond);
            $base = round(max(0.0, $haut - $plancher), 2);

            if ($base > 0) {
                $montant = round($base * (float) $tranche['taux'] / 100, 2);
                $detail[] = ['de' => $plancher, 'a' => $plafond, 'taux' => (float) $tranche['taux'], 'base' => $base, 'montant' => $montant];
                $total += $montant;
            }

            if ($plafond === null || $baseImposable <= $plafond) {
                break;
            }

            $plancher = $plafond;
        }

        return [round($total, 2), $detail];
    }

    private function tauxTotal(string $part): float
    {
        return array_sum(array_map('floatval', $this->config['cnss'][$part]));
    }
}
