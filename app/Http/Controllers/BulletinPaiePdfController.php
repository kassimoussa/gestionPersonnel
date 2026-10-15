<?php

namespace App\Http\Controllers;

use App\Models\FichePaie;
use App\Models\ParametresApplication;
use App\Services\Paie\PaieCalculateur;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Bulletin de paie individuel en PDF (A4 portrait). La policy garantit qu'un employé
 * ne télécharge que ses propres fiches validées, quelle que soit l'URL saisie.
 */
class BulletinPaiePdfController extends Controller
{
    public function __invoke(FichePaie $fiche, PaieCalculateur $calculateur): Response
    {
        Gate::authorize('voir', $fiche);

        $fiche->load(['employe.departement', 'valideePar']);

        $pdf = Pdf::loadView('paie.bulletin', [
            'fiche' => $fiche,
            'parametres' => ParametresApplication::courants(),
            'detailCnss' => $calculateur->detailCnss((float) $fiche->brut),
            'detailIrpp' => $calculateur->detailIrppPourFiche($fiche),
            'genereLe' => now(),
        ])->setPaper('a4', 'portrait');

        $nomFichier = sprintf(
            'bulletin-%d-%02d-%s.pdf',
            $fiche->annee,
            $fiche->mois,
            Str::slug($fiche->employe?->nomComplet() ?? 'employe'),
        );

        return $pdf->download($nomFichier);
    }
}
