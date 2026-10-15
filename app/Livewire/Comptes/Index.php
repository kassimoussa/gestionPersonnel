<?php

namespace App\Livewire\Comptes;

use App\Enums\Droit;
use App\Enums\ProfilDroits;
use App\Livewire\Concerns\RechercheEmploye;
use App\Livewire\Concerns\TriDeListe;
use App\Models\Employe;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/**
 * Comptes utilisateurs : création des accès, lien avec la fiche employé et
 * attribution des droits. L'administrateur possède tous les droits et il est le
 * seul à pouvoir en donner. Les positions de directeur général et de manager ne
 * se donnent pas ici : elles découlent de l'organigramme.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use RechercheEmploye, TriDeListe, WithPagination;

    public bool $formulaireOuvert = false;

    public ?int $editId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public ?int $employe_id = null;

    public bool $admin = false;

    /** @var string[] Droits cochés dans le formulaire. */
    public array $droits = [];

    public string $recherche = '';

    public function mount(): void
    {
        $this->autoriser();
    }

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $comptes = User::query()
            ->with(['employe.departement', 'roles', 'permissions'])
            ->when($this->recherche, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('name', 'like', "%{$this->recherche}%")
                ->orWhere('email', 'like', "%{$this->recherche}%")
            ))
            ->tap(fn ($requete) => $this->appliquerTri($requete))
            ->paginate(20);

        return view('livewire.comptes.index', [
            'comptes' => $comptes,
            'employesTrouves' => $this->employesTrouves(),
            'employeChoisi' => $this->employeChoisi(),
            'droitsParGroupe' => Droit::parGroupe(),
            'profils' => ProfilDroits::cases(),
            'peutAttribuerDroits' => $this->peutAttribuerDroits(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function colonnesTriables(): array
    {
        return [
            'nom' => 'name',
            'employe' => Employe::select('nom')->whereColumn('employes.id', 'users.employe_id'),
            'created_at' => 'created_at',
        ];
    }

    /**
     * @return string[]
     */
    protected function colonnesDecroissantesDAbord(): array
    {
        return ['created_at'];
    }

    /**
     * Seules les fiches non encore liées à un compte sont proposées.
     */
    protected function employesRecherchables(): Builder
    {
        $liees = User::query()->whereNotNull('employe_id')->where('id', '!=', $this->editId ?? 0)->pluck('employe_id');

        return Employe::query()->whereNotIn('id', $liees)->with('departement');
    }

    /**
     * Le nom et l'adresse du compte reprennent ceux de la fiche choisie.
     */
    protected function apresChoixEmploye(Employe $employe): void
    {
        if ($this->editId === null) {
            $this->name = $this->name ?: $employe->nomComplet();
            $this->email = $this->email ?: (string) $employe->email;
        }
    }

    public function nouveau(): void
    {
        $this->autoriser();
        $this->resetForm();
        $this->formulaireOuvert = true;
    }

    public function editer(int $id): void
    {
        $this->autoriser();
        $this->resetForm();

        $compte = User::findOrFail($id);
        $this->editId = $compte->id;
        $this->name = $compte->name;
        $this->email = $compte->email;
        $this->employe_id = $compte->employe_id;
        $this->admin = $compte->estAdmin();
        $this->droits = $compte->droitsAttribues();
        $this->formulaireOuvert = true;
    }

    /**
     * Coche en une fois les droits d'un profil type, que l'administrateur peut
     * ensuite ajuster case par case.
     */
    public function appliquerProfil(string $profil): void
    {
        $this->autoriserAttribution();

        $this->droits = ProfilDroits::from($profil)->valeursDroits();
    }

    public function retirerTousLesDroits(): void
    {
        $this->autoriserAttribution();

        $this->droits = [];
    }

    public function enregistrer(): void
    {
        $this->autoriser();

        $this->employe_id = $this->employe_id ?: null;

        $compte = $this->editId ? User::findOrFail($this->editId) : new User;

        // Sans le droit de les attribuer, on ne touche ni au rôle administrateur ni aux droits.
        if (! $this->peutAttribuerDroits()) {
            $this->admin = $compte->exists && $compte->estAdmin();
            $this->droits = $compte->exists ? $compte->droitsAttribues() : [];
        }

        $donnees = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editId)],
            'password' => [$this->editId ? 'nullable' : 'required', 'string', Password::defaults()],
            'employe_id' => ['nullable', 'exists:employes,id', Rule::unique('users', 'employe_id')->ignore($this->editId)],
            'admin' => ['boolean'],
            'droits' => ['array'],
            'droits.*' => [Rule::in(Droit::valeurs())],
        ], [
            'employe_id.unique' => 'Cette fiche employé est déjà liée à un autre compte.',
        ]);

        if ($compte->exists && $compte->estAdmin() && ! $donnees['admin'] && $this->estDernierAdmin($compte)) {
            throw ValidationException::withMessages([
                'admin' => 'Il doit rester au moins un administrateur : ce compte est le dernier.',
            ]);
        }

        $compte->fill([
            'name' => $donnees['name'],
            'email' => $donnees['email'],
            'employe_id' => $donnees['employe_id'],
        ]);

        if ($donnees['password']) {
            $compte->password = Hash::make($donnees['password']);
        }

        if (! $compte->exists) {
            $compte->email_verified_at = now();
        }

        $compte->save();

        Role::findOrCreate(User::ROLE_ADMIN, 'web');
        $donnees['admin'] ? $compte->assignRole(User::ROLE_ADMIN) : $compte->removeRole(User::ROLE_ADMIN);

        // Un administrateur a tous les droits : ses cases individuelles sont conservées
        // telles quelles, pour qu'il les retrouve si son rôle lui est retiré.
        if (! $donnees['admin']) {
            $compte->syncPermissions($donnees['droits'] ?? []);
        }

        $this->resetForm();
    }

    public function supprimer(int $id): void
    {
        $this->autoriser();

        $compte = User::findOrFail($id);

        if ($compte->is(auth()->user())) {
            $this->addError('suppression', 'Vous ne pouvez pas supprimer votre propre compte depuis cet écran.');

            return;
        }

        if ($compte->estAdmin() && $this->estDernierAdmin($compte)) {
            $this->addError('suppression', 'Il doit rester au moins un administrateur.');

            return;
        }

        $compte->delete();
    }

    public function annuler(): void
    {
        $this->resetForm();
    }

    private function estDernierAdmin(User $compte): bool
    {
        return ! User::role(User::ROLE_ADMIN)->whereKeyNot($compte->id)->exists();
    }

    private function peutAttribuerDroits(): bool
    {
        return auth()->user()->estAdmin();
    }

    private function autoriser(): void
    {
        abort_unless(auth()->user()->aLeDroit(Droit::ComptesGerer), 403);
    }

    private function autoriserAttribution(): void
    {
        abort_unless($this->peutAttribuerDroits(), 403, 'Seul un administrateur attribue les droits.');
    }

    private function resetForm(): void
    {
        $this->reset(['formulaireOuvert', 'editId', 'name', 'email', 'password', 'employe_id', 'admin', 'droits', 'rechercheEmploye']);
        $this->resetValidation();
    }
}
