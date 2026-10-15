<?php

namespace App\Livewire\Contrat;

use App\Livewire\Concerns\RechercheEmploye;
use App\Livewire\Concerns\TriDeListe;
use App\Models\Contrat;
use App\Models\Employe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesRequests, RechercheEmploye, TriDeListe;

    public function mount(): void
    {
        $this->authorize('gerer', Contrat::class);
    }

    public bool $formulaireOuvert = false;

    public ?int $editId = null;

    public ?int $employe_id = null;

    public string $type = 'CDI';

    public string $date_debut = '';

    public ?string $date_fin = '';

    public string $salaire_base = '';

    public function render()
    {
        return view('livewire.contrat.index', [
            'contrats' => $this->appliquerTri(Contrat::with('employe'))->get(),
            'employes' => Employe::orderBy('nom')->get(),
            'employesTrouves' => $this->employesTrouves(),
            'employeChoisi' => $this->employeChoisi(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function colonnesTriables(): array
    {
        return [
            'date_debut' => 'date_debut',
            'employe' => Employe::select('nom')->whereColumn('employes.id', 'contrats.employe_id'),
            'type' => 'type',
            'date_fin' => 'date_fin',
            'salaire_base' => 'salaire_base',
        ];
    }

    /**
     * @return string[]
     */
    protected function colonnesDecroissantesDAbord(): array
    {
        return ['date_debut', 'date_fin', 'salaire_base'];
    }

    /**
     * Un contrat se rattache à n'importe quelle fiche du personnel.
     */
    protected function employesRecherchables(): Builder
    {
        return Employe::query()->with('departement');
    }

    public function nouveau(): void
    {
        $this->authorize('gerer', Contrat::class);
        $this->resetForm();
        $this->formulaireOuvert = true;
    }

    public function editer(int $id): void
    {
        $this->authorize('gerer', Contrat::class);
        $contrat = Contrat::findOrFail($id);
        $this->editId = $contrat->id;
        $this->employe_id = $contrat->employe_id;
        $this->type = $contrat->type;
        $this->date_debut = $contrat->date_debut ? $contrat->date_debut->format('Y-m-d') : '';
        $this->date_fin = $contrat->date_fin ? $contrat->date_fin->format('Y-m-d') : '';
        $this->salaire_base = (string) $contrat->salaire_base;
        $this->formulaireOuvert = true;
    }

    public function enregistrer(): void
    {
        $this->authorize('gerer', Contrat::class);
        $this->date_fin = $this->date_fin ?: null;

        $donnees = $this->validate([
            'employe_id' => 'required|exists:employes,id',
            'type' => 'required|in:CDI,CDD,Stage,Consultant',
            'date_debut' => 'required|date',
            'date_fin' => 'nullable|date|after:date_debut',
            'salaire_base' => 'required|numeric|min:0',
        ]);

        Contrat::updateOrCreate(['id' => $this->editId], $donnees);

        $this->resetForm();
    }

    public function supprimer(int $id): void
    {
        $this->authorize('gerer', Contrat::class);
        Contrat::findOrFail($id)->delete();
    }

    public function annuler(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['formulaireOuvert', 'editId', 'employe_id', 'date_debut', 'date_fin', 'salaire_base', 'rechercheEmploye']);
        $this->type = 'CDI';
    }
}
