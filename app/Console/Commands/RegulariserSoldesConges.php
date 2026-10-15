<?php

namespace App\Console\Commands;

use App\Models\Employe;
use App\Models\ParametresApplication;
use App\Models\SoldeCongeAnnuel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegulariserSoldesConges extends Command
{
    /**
     * Remet les compteurs à leur juste niveau quand des crédits mensuels ont été
     * manqués (planificateur arrêté, employé créé en cours d'année). Un solde
     * n'est jamais diminué : la commande complète, elle ne corrige pas à la baisse.
     *
     *   docker compose exec app php artisan conges:regulariser-soldes --simulation
     *   docker compose exec app php artisan conges:regulariser-soldes
     *   docker compose exec app php artisan conges:regulariser-soldes --annee=2025
     */
    protected $signature = 'conges:regulariser-soldes
        {--annee= : Année à régulariser (par défaut : l\'année en cours)}
        {--simulation : Affiche ce qui serait fait sans rien enregistrer}';

    protected $description = 'Complète les soldes de congé annuel des employés actifs jusqu\'au mois en cours';

    public function handle(): int
    {
        $annee = (int) ($this->option('annee') ?? now()->year);

        if ($annee < 2000 || $annee > 2100) {
            $this->error("L'année doit être comprise entre 2000 et 2100 (reçue : {$annee}).");

            return self::INVALID;
        }

        $simulation = (bool) $this->option('simulation');
        $joursParMois = (float) ParametresApplication::courants()->joursCongeParMois();
        $lignes = [];
        $regularises = 0;

        Employe::query()->where('actif', true)->orderBy('nom')->orderBy('prenom')
            ->each(function (Employe $employe) use ($annee, $joursParMois, $simulation, &$lignes, &$regularises): void {
                $mois = $this->moisACrediter($employe, $annee);

                if ($mois === 0) {
                    return;
                }

                $theorique = round($mois * $joursParMois, 2);
                $actuel = $employe->joursCongeAcquis($annee);

                if ($theorique <= $actuel) {
                    return;
                }

                $lignes[] = [
                    $employe->nomComplet(),
                    $employe->date_embauche?->format('d/m/Y') ?? '—',
                    $mois,
                    $this->jours($actuel),
                    $this->jours($theorique),
                ];
                $regularises++;

                if ($simulation) {
                    return;
                }

                DB::transaction(function () use ($employe, $annee, $theorique): void {
                    $solde = SoldeCongeAnnuel::query()->firstOrCreate(
                        ['employe_id' => $employe->id, 'annee' => $annee],
                        ['jours_acquis' => 0],
                    );

                    $solde->jours_acquis = $theorique;
                    $solde->dernier_mois_accumule = max(
                        (string) $solde->dernier_mois_accumule,
                        $this->dernierMoisCompte($annee),
                    );
                    $solde->save();
                });
            });

        if ($lignes !== []) {
            $this->table(['Employé', 'Embauche', 'Mois comptés', 'Avant', 'Après'], $lignes);
        }

        $this->info(sprintf(
            '%s : %d solde(s) %s, %s jour(s) par mois.',
            $annee,
            $regularises,
            $simulation ? 'à régulariser (simulation)' : 'régularisé(s)',
            $this->jours($joursParMois),
        ));

        return self::SUCCESS;
    }

    /**
     * Nombre de mois ouvrant droit sur l'année : de l'embauche (ou de janvier)
     * jusqu'au mois en cours, sans dépasser décembre de l'année demandée.
     */
    private function moisACrediter(Employe $employe, int $annee): int
    {
        $debut = CarbonImmutable::create($annee, 1, 1);

        if ($employe->date_embauche !== null) {
            $embauche = CarbonImmutable::instance($employe->date_embauche)->startOfMonth();

            if ($embauche->year > $annee) {
                return 0;
            }

            if ($embauche->year === $annee) {
                $debut = $embauche;
            }
        }

        $fin = CarbonImmutable::createFromFormat('Y-m', $this->dernierMoisCompte($annee))->startOfMonth();

        if ($fin->lessThan($debut)) {
            return 0;
        }

        return (int) $debut->diffInMonths($fin) + 1;
    }

    /**
     * Dernier mois pris en compte : le mois en cours, ou décembre pour une année passée.
     */
    private function dernierMoisCompte(int $annee): string
    {
        return $annee < now()->year ? "{$annee}-12" : now()->format('Y-m');
    }

    private function jours(float $valeur): string
    {
        return rtrim(rtrim(number_format($valeur, 2, ',', ' '), '0'), ',');
    }
}
