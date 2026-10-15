<?php

namespace App\Console\Commands;

use App\Models\Employe;
use App\Models\ParametresApplication;
use App\Models\SoldeCongeAnnuel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AccumulerSoldesConges extends Command
{
    /**
     * Lancement manuel pour test :
     *   docker compose exec app php artisan conges:accumuler-soldes
     *   docker compose exec app php artisan conges:accumuler-soldes --mois=2026-08
     *   docker compose exec app php artisan conges:accumuler-soldes --forcer
     */
    protected $signature = 'conges:accumuler-soldes
        {--mois= : Mois à créditer au format AAAA-MM (par défaut : le mois en cours)}
        {--jours= : Jours à créditer (par défaut : config personnel.conges.jours_acquis_par_mois)}
        {--forcer : Créditer même si ce mois a déjà été pris en compte pour l\'employé}';

    protected $description = 'Ajoute les jours de congé annuel acquis mensuellement (2,5 j/mois) à chaque employé actif pour l\'année en cours';

    public function handle(): int
    {
        $mois = $this->option('mois') ?? now()->format('Y-m');

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mois)) {
            $this->error("Le mois doit être au format AAAA-MM (reçu : {$mois}).");

            return self::INVALID;
        }

        $annee = CarbonImmutable::createFromFormat('Y-m', $mois)->year;
        $jours = (float) ($this->option('jours') ?? ParametresApplication::courants()->joursCongeParMois());
        $forcer = (bool) $this->option('forcer');

        $credites = 0;
        $ignores = 0;

        Employe::query()->where('actif', true)->orderBy('id')->each(function (Employe $employe) use ($annee, $mois, $jours, $forcer, &$credites, &$ignores): void {
            DB::transaction(function () use ($employe, $annee, $mois, $jours, $forcer, &$credites, &$ignores): void {
                $solde = SoldeCongeAnnuel::query()->firstOrCreate(
                    ['employe_id' => $employe->id, 'annee' => $annee],
                    ['jours_acquis' => 0],
                );

                // Idempotence : un mois déjà crédité (ou antérieur au dernier mois crédité) est ignoré.
                if (! $forcer && $solde->dernier_mois_accumule !== null && $solde->dernier_mois_accumule >= $mois) {
                    $ignores++;

                    return;
                }

                $solde->jours_acquis = (float) $solde->jours_acquis + $jours;
                $solde->dernier_mois_accumule = max($mois, (string) $solde->dernier_mois_accumule);
                $solde->save();

                $credites++;
            });
        });

        $this->info(sprintf(
            'Mois %s : %s jour(s) crédité(s) à %d employé(s) actif(s), %d ignoré(s) (mois déjà pris en compte).',
            $mois,
            rtrim(rtrim(number_format($jours, 2, ',', ''), '0'), ','),
            $credites,
            $ignores,
        ));

        return self::SUCCESS;
    }
}
