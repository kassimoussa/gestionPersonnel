<?php

namespace App\Livewire\Presence;

use App\Models\ImportPointages;
use App\Models\Pointage;
use App\Services\Pointages\ImportateurPointages;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Import extends Component
{
    use AuthorizesRequests, WithFileUploads;

    public $fichier = null;

    /** Fenêtre modale d'import ouverte. */
    public bool $formulaireOuvert = false;

    /** Import dont le détail des erreurs est déplié. */
    public ?int $detailsImportId = null;

    /** Bilan du dernier import réalisé dans cette session de page. */
    public ?int $dernierImportId = null;

    public int $pointagesCrees = 0;

    public int $pointagesMisAJour = 0;

    public int $joursIgnores = 0;

    public function mount(): void
    {
        $this->authorize('importer', Pointage::class);
    }

    public function render()
    {
        $imports = ImportPointages::query()
            ->with('importePar')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('livewire.presence.import', [
            'imports' => $imports,
            'dernierImport' => $this->dernierImportId ? $imports->firstWhere('id', $this->dernierImportId) : null,
            'importDetaille' => $this->detailsImportId ? $imports->firstWhere('id', $this->detailsImportId) : null,
        ]);
    }

    public function importer(ImportateurPointages $importateur): void
    {
        $this->authorize('importer', Pointage::class);

        $this->validate([
            'fichier' => ['required', 'file', 'extensions:csv,txt', 'max:5120'],
        ]);

        $resultat = $importateur->importer(
            $this->fichier->getRealPath(),
            $this->fichier->getClientOriginalName(),
            auth()->user(),
        );

        $this->dernierImportId = $resultat->import->id;
        $this->detailsImportId = $resultat->import->nombre_erreurs > 0 ? $resultat->import->id : null;
        $this->pointagesCrees = $resultat->pointagesCrees;
        $this->pointagesMisAJour = $resultat->pointagesMisAJour;
        $this->joursIgnores = $resultat->joursIgnores;

        $this->reset(['fichier', 'formulaireOuvert']);
    }

    public function nouveau(): void
    {
        $this->reset('fichier');
        $this->resetValidation();
        $this->formulaireOuvert = true;
    }

    public function annuler(): void
    {
        $this->reset(['fichier', 'formulaireOuvert']);
        $this->resetValidation();
    }

    public function voirDetails(int $id): void
    {
        $this->detailsImportId = $this->detailsImportId === $id ? null : $id;
    }
}
