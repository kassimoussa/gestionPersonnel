<?php

namespace App\Livewire\Presence;

use App\Enums\StatutJustificatif;
use App\Livewire\Concerns\RechercheEmploye;
use App\Livewire\Concerns\TriDeListe;
use App\Models\Employe;
use App\Models\JustificatifAbsence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Justificatifs d'absence : le responsable et le service du personnel examinent
 * les pièces fournies, acceptent ou refusent, et peuvent saisir une justification
 * reçue sur papier pour un employé de leur périmètre.
 */
#[Layout('layouts.app')]
class Justificatifs extends Component
{
    use AuthorizesRequests, RechercheEmploye, TriDeListe, WithFileUploads, WithPagination;

    public string $filtreStatut = 'en_attente';

    public ?int $filtreEmployeId = null;

    public bool $formulaireOuvert = false;

    public ?int $employe_id = null;

    public string $date = '';

    public string $motif = '';

    public $piece = null;

    /** Justification dont le formulaire de refus est ouvert. */
    public ?int $refusId = null;

    public string $motifRefus = '';

    public ?string $information = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->peutSaisirPresence(), 403);
    }

    public function updatingFiltreStatut(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreEmployeId(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $utilisateur = auth()->user();

        $justificatifs = JustificatifAbsence::query()
            ->whereIn('employe_id', $utilisateur->employesSupervises()->select('id'))
            ->when($this->filtreStatut !== 'tous', fn ($q) => $q->where('statut', $this->filtreStatut))
            ->when($this->filtreEmployeId, fn ($q) => $q->where('employe_id', $this->filtreEmployeId))
            ->with(['employe.departement', 'saisiPar', 'decidePar'])
            ->tap(fn ($requete) => $this->appliquerTri($requete))
            ->paginate(20);

        return view('livewire.presence.justificatifs', [
            'justificatifs' => $justificatifs,
            'employes' => $utilisateur->employesSupervises()->where('actif', true)->orderBy('nom')->orderBy('prenom')->get(),
            'employesTrouves' => $this->employesTrouves(),
            'employeChoisi' => $this->employeChoisi(),
            'statuts' => StatutJustificatif::cases(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function colonnesTriables(): array
    {
        return [
            'date' => 'date',
            'employe' => Employe::select('nom')->whereColumn('employes.id', 'justificatifs_absence.employe_id'),
            'statut' => 'statut',
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

    public function annuler(): void
    {
        $this->reinitialiserFormulaire();
    }

    /**
     * Enregistre une justification reçue sur papier pour un employé du périmètre.
     */
    public function enregistrer(): void
    {
        $donnees = $this->validate([
            'employe_id' => ['required', Rule::exists('employes', 'id')],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'motif' => ['required', 'string', 'max:1000'],
            'piece' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ], [
            'piece.max' => 'La pièce ne doit pas dépasser 2 Mo.',
            'piece.mimes' => 'La pièce doit être un PDF ou une image (JPG, PNG).',
        ]);

        $employe = Employe::findOrFail($donnees['employe_id']);

        $this->authorize('creerPour', [JustificatifAbsence::class, $employe]);

        // whereDate() est indispensable : SQLite stocke la colonne DATE avec une heure,
        // une comparaison directe ne retrouverait pas la justification existante.
        $justificatif = JustificatifAbsence::query()
            ->where('employe_id', $employe->id)
            ->whereDate('date', $donnees['date'])
            ->first()
            ?? new JustificatifAbsence(['employe_id' => $employe->id, 'date' => $donnees['date']]);

        if ($this->piece !== null) {
            if ($justificatif->fichier_path !== null) {
                Storage::disk(JustificatifAbsence::DISQUE)->delete($justificatif->fichier_path);
            }

            $justificatif->fichier_path = $this->piece->store(JustificatifAbsence::DOSSIER, JustificatifAbsence::DISQUE);
            $justificatif->fichier_nom = $this->piece->getClientOriginalName();
        }

        $justificatif->fill([
            'motif' => $donnees['motif'],
            'saisi_par_id' => auth()->id(),
        ])->save();

        $this->reinitialiserFormulaire();
        $this->information = sprintf('Justification enregistrée pour %s.', $employe->nomComplet());
    }

    public function accepter(int $id): void
    {
        $justificatif = JustificatifAbsence::findOrFail($id);

        $this->authorize('decider', $justificatif);

        $justificatif->accepterPar(auth()->user());

        $this->information = sprintf(
            'Absence du %s justifiée pour %s.',
            $justificatif->date->format('d/m/Y'),
            $justificatif->employe?->nomComplet() ?? '',
        );
    }

    public function refuser(int $id): void
    {
        $justificatif = JustificatifAbsence::findOrFail($id);

        $this->authorize('decider', $justificatif);

        $this->refusId = $justificatif->id;
        $this->motifRefus = '';
        $this->resetValidation();
    }

    public function confirmerRefus(): void
    {
        $this->validate(['motifRefus' => ['required', 'string', 'max:1000']]);

        $justificatif = JustificatifAbsence::findOrFail($this->refusId);

        $this->authorize('decider', $justificatif);

        $justificatif->refuserPar(auth()->user(), $this->motifRefus);

        $this->annulerRefus();
        $this->information = "Justification refusée : l'absence reste non justifiée.";
    }

    public function annulerRefus(): void
    {
        $this->reset(['refusId', 'motifRefus']);
        $this->resetValidation();
    }

    private function reinitialiserFormulaire(): void
    {
        $this->reset(['formulaireOuvert', 'employe_id', 'date', 'motif', 'piece', 'information', 'rechercheEmploye']);
        $this->resetValidation();
    }
}
