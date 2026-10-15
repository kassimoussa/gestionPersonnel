<?php

namespace App\Services\Paie;

/**
 * Résultat d'un calcul de paie mensuel. Tous les montants sont en FD, arrondis au centime.
 */
final readonly class MontantsPaie
{
    /**
     * @param  array<int, array{de: float, a: ?float, taux: float, base: float, montant: float}>  $tranchesIrpp
     *                                                                                                           Détail du barème progressif (vide si taux forfaitaire)
     */
    public function __construct(
        public float $salaireBase,
        public float $primes,
        public float $indemnites,
        public float $brut,
        public float $assietteCnss,
        public float $cnssSalarie,
        public float $cnssEmployeur,
        public float $baseImposable,
        public float $irpp,
        public float $netAPayer,
        public bool $contratCourt,
        public array $tranchesIrpp,
    ) {}

    /**
     * Brut + part patronale CNSS.
     */
    public function coutEmployeur(): float
    {
        return round($this->brut + $this->cnssEmployeur, 2);
    }

    /**
     * Colonnes de fiches_paie correspondantes.
     *
     * @return array<string, float>
     */
    public function attributsFiche(): array
    {
        return [
            'salaire_base' => $this->salaireBase,
            'primes' => $this->primes,
            'indemnites' => $this->indemnites,
            'brut' => $this->brut,
            'cnss_salarie' => $this->cnssSalarie,
            'cnss_employeur' => $this->cnssEmployeur,
            'base_imposable' => $this->baseImposable,
            'irpp' => $this->irpp,
            'net_a_payer' => $this->netAPayer,
        ];
    }
}
