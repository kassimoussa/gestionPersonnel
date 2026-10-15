<?php

namespace App\Http\Controllers;

use App\Models\DocumentEmploye;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pièce du dossier d'un employé. Les fichiers vivent sur le disque privé et ne
 * sortent que par ici, après contrôle des droits.
 */
class DocumentEmployeController extends Controller
{
    public function __invoke(DocumentEmploye $document): StreamedResponse
    {
        Gate::authorize('telecharger', $document);

        abort_unless($document->existeSurLeDisque(), 404, 'Le fichier de ce document est introuvable.');

        return Storage::disk(DocumentEmploye::DISQUE)->download($document->fichier_path, $document->fichier_nom);
    }
}
