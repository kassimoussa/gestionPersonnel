<?php

namespace App\Services\Conges;

use App\Services\CalculJoursOuvres;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Décompte des congés : en jours ouvrés. Les jours de weekend et les jours
 * fériés ne sont pas décomptés du solde de l'employé, et la reprise est fixée
 * au premier jour travaillé qui suit la fin du congé.
 */
class CalculPeriodeConge
{
    public function __construct(private readonly CalculJoursOuvres $joursOuvres) {}

    /**
     * Nombre de jours décomptés entre deux dates, bornes incluses : les jours
     * travaillés seulement. Retourne 0 si la fin précède le début.
     */
    public function nombreJours(DateTimeInterface|string $dateDebut, DateTimeInterface|string $dateFin): int
    {
        return $this->joursOuvres->entre($dateDebut, $dateFin);
    }

    /**
     * Nombre de jours calendaires de la période, weekend et fériés compris :
     * l'absence réelle du poste, utile pour l'attestation.
     */
    public function nombreJoursCalendaires(DateTimeInterface|string $dateDebut, DateTimeInterface|string $dateFin): int
    {
        $debut = $this->normaliser($dateDebut);
        $fin = $this->normaliser($dateFin);

        if ($fin->lessThan($debut)) {
            return 0;
        }

        return (int) $debut->diffInDays($fin) + 1;
    }

    /**
     * Premier jour travaillé suivant la fin du congé : c'est la date de reprise
     * annoncée sur l'attestation. Elle saute le weekend et les jours fériés.
     */
    public function jourReprise(DateTimeInterface|string $dateFin): CarbonImmutable
    {
        $jour = $this->normaliser($dateFin)->addDay();

        while (! $this->joursOuvres->estOuvre($jour)) {
            $jour = $jour->addDay();
        }

        return $jour;
    }

    /**
     * Une période entièrement posée sur des jours chômés ne décompterait rien :
     * elle est refusée à la saisie.
     */
    public function contientUnJourTravaille(DateTimeInterface|string $dateDebut, DateTimeInterface|string $dateFin): bool
    {
        return $this->joursOuvres->entre($dateDebut, $dateFin) > 0;
    }

    private function normaliser(DateTimeInterface|string $date): CarbonImmutable
    {
        $carbon = $date instanceof DateTimeInterface
            ? CarbonImmutable::instance($date)
            : CarbonImmutable::parse($date);

        return $carbon->startOfDay();
    }
}
