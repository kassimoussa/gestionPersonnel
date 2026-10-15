<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coupe la session d'un utilisateur dont la fiche employé vient d'être désactivée :
 * le blocage vaut aussi pour les sessions déjà ouvertes.
 */
class VerifierCompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = Auth::user();

        if ($utilisateur?->employe !== null && ! $utilisateur->employe->actif) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'form.email' => 'Ce compte est désactivé. Contactez le service RH.',
            ]);
        }

        return $next($request);
    }
}
