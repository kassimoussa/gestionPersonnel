<?php

namespace App\Services\Pointages;

use App\Enums\StatutConge;
use App\Enums\StatutPresence;
use App\Models\Conge;
use App\Models\Employe;
use App\Models\EvenementEmploye;
use App\Models\JustificatifAbsence;
use App\Models\Pointage;
use App\Services\CalculJoursOuvres;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Calcule, pour chaque employé et chaque jour d'une période, un statut de
 * présence en croisant les pointages, les congés approuvés (module Congés)
 * et le calendrier (jours de weekend de config/personnel.php).
 */
class RapportPresence
{
    public function __construct(private CalculJoursOuvres $calculJoursOuvres) {}

    /**
     * @param  Collection<int, Employe>  $employes
     * @return array<int, array{
     *     employe: Employe,
     *     jours: array<string, array{statut: StatutPresence, pointage: ?Pointage}>,
     *     totaux: array{present: int, retard: int, conge: int, mise_a_pied: int, absence: int, absence_justifiee: int}
     * }>
     */
    public function generer(Collection $employes, CarbonImmutable $debut, CarbonImmutable $fin): array
    {
        $debut = $debut->startOfDay();
        $fin = $fin->startOfDay();
        $identifiants = $employes->pluck('id');

        $pointages = Pointage::query()
            ->whereIn('employe_id', $identifiants)
            ->whereDate('date', '>=', $debut->toDateString())
            ->whereDate('date', '<=', $fin->toDateString())
            ->get()
            ->keyBy(fn (Pointage $pointage) => $pointage->employe_id.'|'.$pointage->date->toDateString());

        $conges = Conge::query()
            ->whereIn('employe_id', $identifiants)
            ->where('statut', StatutConge::Acte)
            ->whereDate('date_debut', '<=', $fin->toDateString())
            ->whereDate('date_fin', '>=', $debut->toDateString())
            ->get()
            ->groupBy('employe_id');

        // Une mise à pied suspend le travail : ce n'est ni une présence ni une absence.
        $misesAPied = EvenementEmploye::query()
            ->misesAPied()
            ->whereIn('employe_id', $identifiants)
            ->whereDate('date_debut', '<=', $fin->toDateString())
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $debut->toDateString()))
            ->get()
            ->groupBy('employe_id');

        // Une absence justifiée acceptée change le statut de la journée.
        $justifications = JustificatifAbsence::query()
            ->acceptes()
            ->whereIn('employe_id', $identifiants)
            ->whereDate('date', '>=', $debut->toDateString())
            ->whereDate('date', '<=', $fin->toDateString())
            ->get()
            ->keyBy(fn (JustificatifAbsence $justificatif) => $justificatif->employe_id.'|'.$justificatif->date->toDateString());

        $aujourdhui = CarbonImmutable::today();
        $lignes = [];

        foreach ($employes as $employe) {
            $jours = [];
            $totaux = ['present' => 0, 'retard' => 0, 'conge' => 0, 'mise_a_pied' => 0, 'absence' => 0, 'absence_justifiee' => 0];

            for ($jour = $debut; $jour->lessThanOrEqualTo($fin); $jour = $jour->addDay()) {
                $cle = $jour->toDateString();
                $pointage = $pointages->get($employe->id.'|'.$cle);

                $statut = $this->determinerStatut(
                    $employe,
                    $jour,
                    $pointage,
                    $conges->get($employe->id, collect()),
                    $aujourdhui,
                    $justifications->has($employe->id.'|'.$cle),
                    $misesAPied->get($employe->id, collect()),
                );

                $jours[$cle] = ['statut' => $statut, 'pointage' => $pointage];

                if ($statut->estComptabilise()) {
                    $totaux[$statut->value]++;
                }
            }

            $lignes[] = ['employe' => $employe, 'jours' => $jours, 'totaux' => $totaux];
        }

        return $lignes;
    }

    /**
     * Ordre des règles : pointage réel > weekend ou jour férié > mise à pied > congé approuvé > hors période
     * > en cours (jour courant sans pointage) > absence, justifiée ou non.
     *
     * @param  Collection<int, Conge>  $congesApprouves
     */
    public function determinerStatut(
        Employe $employe,
        CarbonImmutable $jour,
        ?Pointage $pointage,
        Collection $congesApprouves,
        CarbonImmutable $aujourdhui,
        bool $absenceJustifiee = false,
        ?Collection $misesAPied = null,
    ): StatutPresence {
        $suspendu = ($misesAPied ?? collect())
            ->contains(fn (EvenementEmploye $evenement) => $evenement->couvre($jour));
        $jourOuvre = $this->calculJoursOuvres->estOuvre($jour);

        if ($pointage !== null && ($pointage->heure_arrivee !== null || $pointage->heure_depart !== null)) {
            return $jourOuvre && $pointage->estEnRetard() ? StatutPresence::Retard : StatutPresence::Present;
        }

        if (! $jourOuvre) {
            return $this->calculJoursOuvres->estFerie($jour) ? StatutPresence::Ferie : StatutPresence::Weekend;
        }

        // La mise à pied prime sur le congé et sur l'absence : l'agent est écarté du service.
        if ($suspendu) {
            return StatutPresence::MiseAPied;
        }

        $enConge = $congesApprouves->contains(
            fn (Conge $conge) => $conge->date_debut->lessThanOrEqualTo($jour) && $conge->date_fin->greaterThanOrEqualTo($jour)
        );

        if ($enConge) {
            return StatutPresence::Conge;
        }

        $avantEmbauche = $employe->date_embauche !== null && $jour->lessThan($employe->date_embauche->toImmutable()->startOfDay());

        if ($jour->greaterThan($aujourdhui) || $avantEmbauche) {
            return StatutPresence::NonApplicable;
        }

        // Jour courant sans pointage : la journée n'est pas terminée, on ne conclut pas à l'absence.
        if ($jour->equalTo($aujourdhui)) {
            return StatutPresence::EnCours;
        }

        return $absenceJustifiee ? StatutPresence::AbsenceJustifiee : StatutPresence::Absence;
    }
}
