<?php

namespace App\Http\Controllers;

use App\Models\Conge;
use App\Models\ParametresApplication;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Attestation de congé présentée à la signature du directeur général, puis
 * conservée au dossier. Son numéro est attribué quand le service du personnel
 * l'établit, après le visa du chef : il ne change plus ensuite.
 */
class AttestationCongePdfController extends Controller
{
    public function __invoke(Conge $conge): Response
    {
        Gate::authorize('telechargerLesDocuments', $conge);

        abort_unless(
            $conge->attestationEtablie(),
            404,
            "Cette attestation n'a pas encore été établie par le service du personnel.",
        );

        $conge->load(['employe.departement']);

        $pdf = Pdf::loadView('conges.attestation', [
            'conge' => $conge,
            'parametres' => ParametresApplication::courants(),
            'reprise' => $conge->jourReprise(),
        ])->setPaper('a4', 'portrait');

        $nomFichier = sprintf(
            'attestation-conge-%s-%s.pdf',
            Str::slug($conge->employe?->nomComplet() ?? 'employe'),
            $conge->date_debut->format('Y-m-d'),
        );

        return $pdf->download($nomFichier);
    }
}
