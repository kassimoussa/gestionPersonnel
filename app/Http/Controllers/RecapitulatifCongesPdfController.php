<?php

namespace App\Http\Controllers;

use App\Enums\TypeConge;
use App\Models\Conge;
use App\Models\ParametresApplication;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Récapitulatif des congés d'un employé, joint à l'attestation présentée à la
 * signature du directeur général : droits acquis, report, congés déjà pris et
 * solde restant après la demande en cours.
 */
class RecapitulatifCongesPdfController extends Controller
{
    public function __invoke(Conge $conge): Response
    {
        Gate::authorize('telechargerLesDocuments', $conge);

        $conge->load(['employe.departement']);
        $employe = $conge->employe;
        $annee = $conge->date_debut->year;

        $pdf = Pdf::loadView('conges.recapitulatif', [
            'conge' => $conge,
            'employe' => $employe,
            'annee' => $annee,
            'parametres' => ParametresApplication::courants(),
            'solde' => [
                'reporte' => $employe?->joursCongeReportes($annee) ?? 0.0,
                'acquis' => $employe?->joursCongeAcquis($annee) ?? 0.0,
                'pris' => $employe?->joursCongeAnnuelApprouves($annee, true) ?? 0.0,
                'restant' => $employe?->soldeCongeRestant($annee) ?? 0.0,
                'disponible' => $employe?->soldeCongeDisponible($annee) ?? 0.0,
            ],
            'historique' => $employe
                ? $employe->conges()
                    ->where('type', TypeConge::CongeAnnuel)
                    ->orderByDesc('date_debut')
                    ->limit(15)
                    ->get()
                : collect(),
            'genereLe' => now(),
        ])->setPaper('a4', 'portrait');

        $nomFichier = sprintf(
            'recapitulatif-conges-%s.pdf',
            Str::slug($employe?->nomComplet() ?? 'employe'),
        );

        return $pdf->download($nomFichier);
    }
}
