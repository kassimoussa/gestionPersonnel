<?php

namespace App\Livewire\Presence;

use App\Enums\SourcePointage;
use App\Livewire\Concerns\RechercheEmploye;
use App\Livewire\Concerns\TriDeListe;
use App\Models\Employe;
use App\Models\Pointage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Saisie extends Component
{
    use AuthorizesRequests, RechercheEmploye, TriDeListe, WithPagination;

    public bool $formulaireOuvert = false;

    public ?int $editId = null;

    public ?int $employe_id = null;

    public string $date = '';

    public ?string $heure_arrivee = '';

    public ?string $heure_depart = '';

    public ?string $commentaire = '';

    public ?int $filtreEmployeId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->peutSaisirPresence(), 403);

        $this->date = now()->toDateString();
    }

    public function updatingFiltreEmployeId(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.presence.saisie', [
            'employes' => $user->employesSupervises()->where('actif', true)->orderBy('nom')->orderBy('prenom')->get(),
            'employesTrouves' => $this->employesTrouves(),
            'employeChoisi' => $this->employeChoisi(),
            'pointages' => Pointage::query()
                ->whereIn('employe_id', $user->employesSupervises()->select('id'))
                ->when($this->filtreEmployeId, fn ($q) => $q->where('employe_id', $this->filtreEmployeId))
                ->with(['employe', 'saisiPar'])
                ->tap(fn ($requete) => $this->appliquerTri($requete))
                ->orderBy('employe_id')
                ->paginate(20),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function colonnesTriables(): array
    {
        return [
            'date' => 'date',
            'employe' => Employe::select('nom')->whereColumn('employes.id', 'pointages.employe_id'),
            'heure_arrivee' => 'heure_arrivee',
            'heure_depart' => 'heure_depart',
            'source' => 'source',
        ];
    }

    /**
     * @return string[]
     */
    protected function colonnesDecroissantesDAbord(): array
    {
        return ['date'];
    }

    /**
     * Périmètre de la recherche : les employés actifs suivis par l'utilisateur.
     */
    protected function employesRecherchables(): Builder
    {
        return auth()->user()->employesSupervises()->where('actif', true)->with('departement');
    }

    public function nouveau(): void
    {
        $this->reinitialiserFormulaire();
        $this->formulaireOuvert = true;
    }

    public function editer(int $id): void
    {
        $pointage = Pointage::findOrFail($id);

        $this->authorize('modifier', $pointage);

        $this->editId = $pointage->id;
        $this->employe_id = $pointage->employe_id;
        $this->date = $pointage->date->toDateString();
        $this->heure_arrivee = $pointage->heureArriveeCourte() ?? '';
        $this->heure_depart = $pointage->heureDepartCourte() ?? '';
        $this->commentaire = $pointage->commentaire ?? '';
        $this->formulaireOuvert = true;
    }

    public function enregistrer(): void
    {
        $this->heure_arrivee = $this->heure_arrivee ?: null;
        $this->heure_depart = $this->heure_depart ?: null;
        $this->commentaire = $this->commentaire ?: null;

        $donnees = $this->validate([
            'employe_id' => ['required', 'exists:employes,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'heure_arrivee' => ['nullable', 'date_format:H:i,H:i:s', 'required_without:heure_depart'],
            'heure_depart' => ['nullable', 'date_format:H:i,H:i:s'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);

        $arrivee = $this->normaliserHeure($donnees['heure_arrivee']);
        $depart = $this->normaliserHeure($donnees['heure_depart']);

        if ($arrivee !== null && $depart !== null && $depart <= $arrivee) {
            throw ValidationException::withMessages([
                'heure_depart' => "L'heure de départ doit être postérieure à l'heure d'arrivée.",
            ]);
        }

        $employe = Employe::findOrFail($donnees['employe_id']);

        $this->authorize('saisir', [Pointage::class, $employe]);

        // whereDate : la colonne est une DATE mais SQLite (tests) stocke aussi l'heure.
        $pointage = Pointage::query()
            ->where('employe_id', $employe->id)
            ->whereDate('date', $donnees['date'])
            ->firstOrNew();

        $pointage->fill([
            'employe_id' => $employe->id,
            'date' => $donnees['date'],
            'heure_arrivee' => $arrivee,
            'heure_depart' => $depart,
            'source' => SourcePointage::Manuel,
            'saisi_par_id' => auth()->id(),
            'commentaire' => $donnees['commentaire'],
        ])->save();

        $this->reinitialiserFormulaire();
    }

    public function supprimer(int $id): void
    {
        $pointage = Pointage::findOrFail($id);

        $this->authorize('supprimer', $pointage);

        $pointage->delete();
    }

    public function annuler(): void
    {
        $this->reinitialiserFormulaire();
    }

    private function normaliserHeure(?string $heure): ?string
    {
        if ($heure === null) {
            return null;
        }

        return strlen($heure) === 5 ? $heure.':00' : $heure;
    }

    private function reinitialiserFormulaire(): void
    {
        $this->reset(['formulaireOuvert', 'editId', 'employe_id', 'heure_arrivee', 'heure_depart', 'commentaire', 'rechercheEmploye']);
        $this->date = now()->toDateString();
        $this->resetValidation();
    }
}
