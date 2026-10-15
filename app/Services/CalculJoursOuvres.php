<?php

namespace App\Services;

use App\Models\JourFerie;
use App\Models\ParametresApplication;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Calcule le nombre de jours ouvrés entre deux dates en excluant les jours
 * de weekend définis dans les paramètres de l'application (config/personnel.php
 * par défaut) et les jours fériés enregistrés.
 */
class CalculJoursOuvres
{
    /** @var int[] Numéros ISO des jours non ouvrés (1 = lundi ... 7 = dimanche) */
    private array $joursWeekend;

    /** @var array<string, string> Dates fériées : « AAAA-MM-JJ », ou « MM-JJ » si la fête revient chaque année */
    private array $joursFeries;

    /**
     * @param  int[]|null  $joursWeekend
     * @param  array<string, string>|null  $joursFeries
     */
    public function __construct(?array $joursWeekend = null, ?array $joursFeries = null)
    {
        $this->joursWeekend = $joursWeekend ?? ParametresApplication::courants()->joursWeekend();
        $this->joursFeries = $joursFeries ?? JourFerie::pourLeCalcul();
    }

    /**
     * Nombre de jours ouvrés entre deux dates, bornes incluses.
     * Retourne 0 si la date de fin est antérieure à la date de début.
     */
    public function entre(DateTimeInterface|string $dateDebut, DateTimeInterface|string $dateFin): int
    {
        $debut = $this->normaliser($dateDebut);
        $fin = $this->normaliser($dateFin);

        if ($fin->lessThan($debut)) {
            return 0;
        }

        $total = 0;

        for ($jour = $debut; $jour->lessThanOrEqualTo($fin); $jour = $jour->addDay()) {
            if ($this->estOuvre($jour)) {
                $total++;
            }
        }

        return $total;
    }

    public function estOuvre(DateTimeInterface|string $jour): bool
    {
        $date = $this->normaliser($jour);

        return ! in_array($date->dayOfWeekIso, $this->joursWeekend, true)
            && $this->libelleFerie($date) === null;
    }

    /**
     * Libellé du jour férié correspondant, ou null si le jour n'est pas férié.
     */
    public function libelleFerie(DateTimeInterface|string $jour): ?string
    {
        $date = $this->normaliser($jour);

        return $this->joursFeries[$date->format('Y-m-d')]
            ?? $this->joursFeries[$date->format('m-d')]
            ?? null;
    }

    public function estFerie(DateTimeInterface|string $jour): bool
    {
        return $this->libelleFerie($jour) !== null;
    }

    private function normaliser(DateTimeInterface|string $date): CarbonImmutable
    {
        $carbon = $date instanceof DateTimeInterface
            ? CarbonImmutable::instance($date)
            : CarbonImmutable::parse($date);

        return $carbon->startOfDay();
    }
}
