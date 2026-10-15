<?php

namespace App\Livewire\Departement;

use App\Enums\Droit;
use App\Enums\TypeDepartement;
use App\Livewire\Concerns\TriDeListe;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\ParametresApplication;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use TriDeListe;

    public bool $formulaireOuvert = false;

    public ?int $editId = null;

    public string $nom = '';

    /** Valeur de TypeDepartement ; vide tant que rien n'est choisi (pas de valeur par défaut). */
    public string $type = '';

    public ?int $responsable_id = null;

    public ?int $parent_departement_id = null;

    public function render()
    {
        return view('livewire.departement.index', [
            'departements' => $this->appliquerTri(Departement::with(['responsable', 'parent'])->withCount('employes'))->get(),
            'employesPossibles' => Employe::orderBy('nom')->get(),
            'parentsPossibles' => $this->parentsPossibles(),
            'types' => TypeDepartement::cases(),
            'typeChoisi' => TypeDepartement::tryFrom($this->type),
            'peutGerer' => auth()->user()->aLeDroit(Droit::StructureGerer),
            'directionGeneraleId' => ParametresApplication::courants()->direction_generale_id,
        ]);
    }

    /**
     * Le type conditionne les parents proposés : un parent devenu invalide est désélectionné.
     */
    public function updatedType(): void
    {
        $this->parent_departement_id = $this->parent_departement_id ?: null;

        if ($this->parent_departement_id !== null && ! $this->parentsPossibles()->contains('id', $this->parent_departement_id)) {
            $this->parent_departement_id = null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function colonnesTriables(): array
    {
        return [
            'nom' => 'nom',
            'type' => 'type',
            'parent' => Departement::select('nom')->from('departements as parents')->whereColumn('parents.id', 'departements.parent_departement_id'),
            'responsable' => Employe::select('nom')->whereColumn('employes.id', 'departements.responsable_id'),
            'employes' => 'employes_count',
        ];
    }

    /**
     * @return string[]
     */
    protected function colonnesDecroissantesDAbord(): array
    {
        return ['employes'];
    }

    public function nouveau(): void
    {
        $this->autoriserGestion();
        $this->resetForm();
        $this->formulaireOuvert = true;
    }

    public function editer(int $id): void
    {
        $this->autoriserGestion();
        $departement = Departement::findOrFail($id);
        $this->editId = $departement->id;
        $this->nom = $departement->nom;
        $this->type = $departement->type->value;
        $this->responsable_id = $departement->responsable_id;
        $this->parent_departement_id = $departement->parent_departement_id;
        $this->formulaireOuvert = true;
    }

    public function enregistrer(): void
    {
        $this->responsable_id = $this->responsable_id ?: null;
        $this->parent_departement_id = $this->parent_departement_id ?: null;

        $donnees = $this->validate([
            'nom' => 'required|string|max:255',
            'type' => ['required', Rule::enum(TypeDepartement::class)],
            'responsable_id' => 'nullable|exists:employes,id',
            'parent_departement_id' => 'nullable|exists:departements,id',
        ]);

        $this->autoriserGestion();

        $existant = $this->editId ? Departement::findOrFail($this->editId) : null;

        $this->verifierAbsenceDeCycle($existant, $donnees['parent_departement_id']);

        $parent = $donnees['parent_departement_id'] !== null
            ? Departement::findOrFail($donnees['parent_departement_id'])
            : null;

        ($existant ?? new Departement)->validerImbrication($parent, $donnees['type']);

        Departement::updateOrCreate(['id' => $this->editId], $donnees);

        $this->resetForm();
    }

    public function supprimer(int $id): void
    {
        $this->autoriserGestion();

        Departement::findOrFail($id)->delete();
    }

    public function annuler(): void
    {
        $this->resetForm();
    }

    /**
     * La structure (création, type, rattachement, responsable, suppression) est gérée par
     * l'administrateur : le responsable d'une unité en devient le manager, et celui de la
     * Direction générale devient DG. Les autres utilisateurs consultent la liste.
     */
    private function autoriserGestion(): void
    {
        abort_unless(auth()->user()->aLeDroit(Droit::StructureGerer), 403, "Vous n'avez pas le droit de modifier la structure des départements.");
    }

    /**
     * Une unité ne peut être ni son propre parent, ni rattachée à l'un de ses descendants.
     */
    private function verifierAbsenceDeCycle(?Departement $departement, ?int $parentId): void
    {
        if ($parentId === null || $departement === null) {
            return;
        }

        if ($parentId === (int) $departement->id) {
            throw ValidationException::withMessages([
                'parent_departement_id' => 'Un département ne peut pas être son propre parent.',
            ]);
        }

        $parent = Departement::findOrFail($parentId);

        if ($departement->estAncetreDe($parent)) {
            throw ValidationException::withMessages([
                'parent_departement_id' => sprintf(
                    'Ce choix créerait un cycle : « %s » est déjà un sous-département de « %s ».',
                    $parent->nom,
                    $departement->nom,
                ),
            ]);
        }
    }

    /**
     * Parents proposés : les unités dont le type est autorisé pour le type choisi,
     * hors unité en cours d'édition et ses descendants. Une direction peut rester
     * au sommet ou être rattachée à une autre direction.
     *
     * @return Collection<int, Departement>
     */
    private function parentsPossibles(): Collection
    {
        $typeChoisi = TypeDepartement::tryFrom($this->type);

        if ($typeChoisi !== null && $typeChoisi->parentsAutorises() === []) {
            return collect();
        }

        $exclus = [];

        if ($this->editId !== null && ($courant = Departement::find($this->editId)) !== null) {
            $exclus = [$courant->id, ...$courant->idsDescendants()];
        }

        return Departement::query()
            ->whereNotIn('id', $exclus)
            ->when($typeChoisi !== null, fn ($query) => $query->whereIn(
                'type',
                array_map(fn (TypeDepartement $type) => $type->value, $typeChoisi->parentsAutorises()),
            ))
            ->orderBy('nom')
            ->get();
    }

    private function resetForm(): void
    {
        $this->reset(['formulaireOuvert', 'editId', 'nom', 'type', 'responsable_id', 'parent_departement_id']);
    }
}
