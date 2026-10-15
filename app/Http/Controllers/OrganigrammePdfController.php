<?php

namespace App\Http\Controllers;

use App\Models\ParametresApplication;
use App\Services\Organigramme\OrganigrammeBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Export PDF de l'organigramme : arbre entièrement déplié, A4 paysage,
 * en-tête avec l'identité de l'entreprise définie dans les paramètres.
 */
class OrganigrammePdfController extends Controller
{
    public function __invoke(OrganigrammeBuilder $builder): Response
    {
        $pdf = Pdf::loadView('organigramme.pdf', [
            'organigramme' => $builder->construire(),
            'parametres' => ParametresApplication::courants(),
            'genereLe' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('organigramme-'.now()->format('Y-m-d').'.pdf');
    }
}
