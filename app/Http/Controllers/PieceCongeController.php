<?php

namespace App\Http\Controllers;

use App\Models\Conge;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pièces signées conservées avec la demande de congé : le formulaire visé par le
 * chef et l'attestation signée par le directeur général.
 */
class PieceCongeController extends Controller
{
    private const PIECES = [
        'formulaire' => ['formulaire_signe_path', 'formulaire_signe_nom', 'formulaire-vise.pdf'],
        'attestation' => ['attestation_signee_path', 'attestation_signee_nom', 'attestation-signee.pdf'],
    ];

    public function __invoke(Conge $conge, string $piece): StreamedResponse
    {
        Gate::authorize('telechargerLesDocuments', $conge);

        abort_unless(array_key_exists($piece, self::PIECES), 404);

        [$colonneChemin, $colonneNom, $nomParDefaut] = self::PIECES[$piece];
        $chemin = $conge->{$colonneChemin};

        abort_unless(
            $chemin !== null && Storage::disk(Conge::DISQUE)->exists($chemin),
            404,
            'Cette pièce n\'a pas encore été déposée.',
        );

        return Storage::disk(Conge::DISQUE)->download($chemin, $conge->{$colonneNom} ?? $nomParDefaut);
    }
}
