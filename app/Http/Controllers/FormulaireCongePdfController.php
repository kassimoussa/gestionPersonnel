<?php

namespace App\Http\Controllers;

use App\Models\Conge;
use App\Models\ParametresApplication;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Formulaire de demande de congé, pré-rempli par le service du personnel et remis
 * à l'employé : il le fait viser par son chef, puis le rapporte signé.
 */
class FormulaireCongePdfController extends Controller
{
    public function __invoke(Conge $conge): Response
    {
        Gate::authorize('telechargerLesDocuments', $conge);

        $conge->load(['employe.departement.responsable']);

        $pdf = Pdf::loadView('conges.formulaire', [
            'conge' => $conge,
            'parametres' => ParametresApplication::courants(),
            'reprise' => $conge->jourReprise(),
            'solde' => [
                'reporte' => $conge->employe?->joursCongeReportes($conge->date_debut->year) ?? 0.0,
                'acquis' => $conge->employe?->joursCongeAcquis($conge->date_debut->year) ?? 0.0,
                'disponible' => $conge->employe?->soldeCongeDisponible($conge->date_debut->year) ?? 0.0,
            ],
        ])->setPaper('a4', 'portrait');

        $nomFichier = sprintf(
            'demande-conge-%s-%s.pdf',
            Str::slug($conge->employe?->nomComplet() ?? 'employe'),
            $conge->date_debut->format('Y-m-d'),
        );

        return $pdf->download($nomFichier);
    }
}
