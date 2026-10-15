<?php

namespace App\Http\Controllers;

use App\Models\JustificatifAbsence;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pièce jointe d'une justification d'absence (certificat médical, convocation...).
 * Elle n'est pas publique : elle est servie ici, après contrôle des droits.
 */
class PieceJustificativeController extends Controller
{
    public function __invoke(JustificatifAbsence $justificatif): StreamedResponse
    {
        Gate::authorize('voirLaPiece', $justificatif);

        abort_unless(
            $justificatif->fichier_path !== null
                && Storage::disk(JustificatifAbsence::DISQUE)->exists($justificatif->fichier_path),
            404,
            'Aucune pièce jointe à cette justification.',
        );

        return Storage::disk(JustificatifAbsence::DISQUE)->download(
            $justificatif->fichier_path,
            $justificatif->nomDeLaPiece(),
        );
    }
}
