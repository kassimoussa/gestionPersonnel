<?php

namespace App\Livewire\TableauDeBord;

use App\Enums\StatutConge;
use App\Enums\StatutFichePaie;
use App\Models\Conge;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\FichePaie;
use App\Models\Pointage;
use App\Models\User;
use App\Services\CalculJoursOuvres;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Tableau de bord : indicateurs en lecture seule adaptés au rôle de l'utilisateur
 * (employé, manager, direction générale), sans logique métier propre.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    /**
     * Demandes de congé encore dans le circuit, pour les employés du périmètre :
     * ce sont les dossiers qui attendent une action du service du personnel.
     */
    private function demandesDuPerimetre(User $user): Builder
    {
        if (! $user->peutEtablirAttestationConge()) {
            return Conge::query()->whereRaw('1 = 0');
        }

        return Conge::query()
            ->enCours()
            ->whereIn('employe_id', $user->employesSupervises()->select('id'));
    }

    /**
     * Derniers congés actés du périmètre : ce que le service vient de clore.
     *
     * @return Collection<int, Conge>
     */
    private function derniersCongesActes(User $user): Collection
    {
        if (! $user->peutEtablirAttestationConge()) {
            return collect();
        }

        return Conge::query()
            ->where('statut', StatutConge::Acte)
            ->whereIn('employe_id', $user->employesSupervises()->select('id'))
            ->with('employe')
            ->orderByDesc('date_decision_dg')
            ->orderByDesc('id')
            ->limit(4)
            ->get();
    }

    public function render(CalculJoursOuvres $calculJoursOuvres)
    {
        $user = auth()->user();
        $employe = $user->employe;

        $presence = null;

        if ($user->peutConsulterPresence()) {
            $idsSupervises = $user->employesSupervises()->where('actif', true)->pluck('id');

            $presence = [
                'presents' => Pointage::query()->whereIn('employe_id', $idsSupervises)->whereDate('date', today())->count(),
                'effectif' => $idsSupervises->count(),
                'jour_ouvre' => $calculJoursOuvres->estOuvre(today()),
            ];
        }

        $paie = null;

        if ($user->estDg()) {
            $fichesDuMois = FichePaie::query()->where('annee', now()->year)->where('mois', now()->month);

            $paie = [
                'periode' => FichePaie::libellePeriode(now()->year, now()->month),
                'nombre' => (clone $fichesDuMois)->count(),
                'brouillons' => (clone $fichesDuMois)->where('statut', StatutFichePaie::Brouillon->value)->count(),
                'brut' => (float) (clone $fichesDuMois)->sum('brut'),
            ];
        }

        return view('livewire.tableau-de-bord.index', [
            'utilisateur' => $user,
            'employe' => $employe,
            'effectifActif' => Employe::query()->where('actif', true)->count(),
            'nombreUnites' => Departement::query()->count(),
            'nombreATraiter' => $this->demandesDuPerimetre($user)->count(),
            'demandesATraiter' => $this->demandesDuPerimetre($user)
                ->with('employe.departement')
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(5)
                ->get(),
            'dernierActes' => $this->derniersCongesActes($user),
            'presence' => $presence,
            'paie' => $paie,
        ]);
    }
}
