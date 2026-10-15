<?php

namespace App\Http\Controllers;

use App\Models\EvenementEmploye;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pièce jointe à un événement du dossier (décision de mise à pied, note de
 * service). Servie ici après contrôle des droits, jamais depuis l'espace public.
 */
class PieceEvenementEmployeController extends Controller
{
    public function __invoke(EvenementEmploye $evenement): StreamedResponse
    {
        Gate::authorize('telechargerLaPiece', $evenement);

        abort_unless(
            $evenement->fichier_path !== null
                && Storage::disk(EvenementEmploye::DISQUE)->exists($evenement->fichier_path),
            404,
            'Aucune pièce jointe à cet événement.',
        );

        return Storage::disk(EvenementEmploye::DISQUE)->download(
            $evenement->fichier_path,
            $evenement->fichier_nom ?? 'decision.pdf',
        );
    }
}
