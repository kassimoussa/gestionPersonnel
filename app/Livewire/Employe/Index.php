<?php

namespace App\Livewire\Employe;

use App\Livewire\Concerns\TriDeListe;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\ParametresApplication;
use App\Models\SoldeCongeAnnuel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesRequests, TriDeListe, WithFileUploads, WithPagination;

    public bool $formulaireOuvert = false;

    public ?int $editId = null;

    // Identité
    public ?string $matricule = '';

    public string $prenom = '';

    public string $nom = '';

    public ?string $sexe = '';

    public ?string $date_naissance = '';

    public ?string $lieu_naissance = '';

    public ?string $nationalite = '';

    /** Nouvelle photo en attente d'enregistrement (upload temporaire Livewire). */
    public $photo = null;

    public bool $retirerPhoto = false;

    // Situation et coordonnées
    public ?string $situation_familiale = '';

    public ?string $nombre_enfants = '';

    public ?string $email = '';

    public ?string $telephone = '';

    public ?string $adresse = '';

    public ?string $contact_urgence_nom = '';

    public ?string $contact_urgence_telephone = '';

    // Poste
    public ?string $poste = '';

    public ?string $categorie = '';

    public ?int $departement_id = null;

    public ?string $date_embauche = '';

    public ?string $matricule_pointeuse = '';

    public bool $actif = true;

    /** Jours de congé annuel acquis pour l'année en cours, ajustables à la main. */
    public ?string $jours_conge_acquis = '';

    // Données sensibles (DG et administrateur)
    public ?string $numero_piece_identite = '';

    public ?string $numero_cnss = '';

    public ?string $banque = '';

    public ?string $numero_compte_bancaire = '';

    public string $recherche = '';

    /** Filtre de la liste : tous, actifs ou inactifs. */
    public string $filtreStatut = 'tous';

    public ?string $information = null;

    public function mount(): void
    {
        $this->authorize('consulter', Employe::class);

        // Retour depuis la fiche d'un employé : /employes?editer=12 ouvre son formulaire.
        $editer = (int) request()->query('editer');

        if ($editer > 0 && auth()->user()->can('gerer', Employe::class)) {
            $this->editer($editer);
        }
    }

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreStatut(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $utilisateur = auth()->user();
        $employeEdite = $this->editId ? Employe::find($this->editId) : null;

        return view('livewire.employe.index', [
            // Un manager ne voit que son équipe, en lecture seule.
            'peutGerer' => $utilisateur->peutGererPersonnel(),
            'employes' => $this->appliquerTri(
                $utilisateur->employesSupervises()->with('departement')
                    ->withCount('fichesPaie')
                    ->when($this->recherche, fn ($q) => $q->where(fn ($qq) => $qq
                        ->where('nom', 'like', "%{$this->recherche}%")
                        ->orWhere('prenom', 'like', "%{$this->recherche}%")
                        ->orWhere('poste', 'like', "%{$this->recherche}%")
                        ->orWhere('matricule', 'like', "%{$this->recherche}%")
                    ))
                    ->when($this->filtreStatut !== 'tous', fn ($q) => $q->where('actif', $this->filtreStatut === 'actifs'))
            )->paginate(15),
            'departements' => Departement::orderBy('nom')->get(),
            'voitDonneesSensibles' => $utilisateur->peutVoirDonneesSensibles(),
            'joursCongeParMois' => (float) ParametresApplication::courants()->joursCongeParMois(),
            'joursCongePris' => $employeEdite
                ? $employeEdite->joursCongeAnnuelApprouves(now()->year) + $employeEdite->joursCongeAnnuelEnAttente(now()->year)
                : 0.0,
            'photoActuelle' => $this->retirerPhoto ? null : $employeEdite?->photoUrl(),
            // Aperçu seulement pour une image : un autre type de fichier est refusé à l'enregistrement.
            'apercuPhoto' => $this->photo?->isPreviewable() ? $this->photo->temporaryUrl() : null,
        ]);
    }

    public function nouveau(): void
    {
        $this->authorize('gerer', Employe::class);
        $this->reinitialiserMessages();
        $this->resetForm();
        $this->formulaireOuvert = true;
    }

    public function editer(int $id): void
    {
        $this->authorize('gerer', Employe::class);
        $this->reinitialiserMessages();
        $this->resetForm();

        $employe = Employe::findOrFail($id);
        $this->editId = $employe->id;

        foreach ($this->champsTexte() as $champ) {
            $this->{$champ} = (string) ($employe->{$champ} ?? '');
        }

        $this->prenom = $employe->prenom;
        $this->nom = $employe->nom;
        $this->email = $employe->email ?? '';
        $this->poste = $employe->poste ?? '';
        $this->date_naissance = $employe->date_naissance?->format('Y-m-d') ?? '';
        $this->date_embauche = $employe->date_embauche?->format('Y-m-d') ?? '';
        $this->nombre_enfants = $employe->nombre_enfants === null ? '' : (string) $employe->nombre_enfants;
        $this->departement_id = $employe->departement_id;
        $this->actif = $employe->actif;
        $this->jours_conge_acquis = $this->formaterJours($employe->joursCongeAcquis(now()->year));

        if (auth()->user()->peutVoirDonneesSensibles()) {
            foreach (Employe::CHAMPS_SENSIBLES as $champ) {
                $this->{$champ} = (string) ($employe->{$champ} ?? '');
            }
        }

        $this->formulaireOuvert = true;
    }

    public function enregistrer(): void
    {
        $this->authorize('gerer', Employe::class);

        foreach ([...$this->champsTexte(), ...Employe::CHAMPS_SENSIBLES, 'date_naissance', 'nombre_enfants', 'email', 'poste', 'date_embauche'] as $champ) {
            $this->{$champ} = trim((string) $this->{$champ}) ?: null;
        }
        $this->departement_id = $this->departement_id ?: null;
        // Matricule et identifiant de la pointeuse sont le même numéro : l'un remplit l'autre.
        $this->matricule = $this->matricule ?: $this->matricule_pointeuse;
        $this->matricule_pointeuse = $this->matricule_pointeuse ?: $this->matricule;
        // Le solde peut être saisi avec une virgule : « 22,5 » vaut 22.5.
        $this->jours_conge_acquis = str_replace(',', '.', trim((string) $this->jours_conge_acquis)) ?: null;

        $donnees = $this->validate([
            'matricule' => ['nullable', 'string', 'max:50', Rule::unique('employes', 'matricule')->ignore($this->editId)],
            'prenom' => ['required', 'string', 'max:255'],
            'nom' => ['required', 'string', 'max:255'],
            'sexe' => ['nullable', Rule::in(array_keys(Employe::SEXES))],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'lieu_naissance' => ['nullable', 'string', 'max:255'],
            'nationalite' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'situation_familiale' => ['nullable', Rule::in(array_keys(Employe::SITUATIONS_FAMILIALES))],
            'nombre_enfants' => ['nullable', 'integer', 'min:0', 'max:30'],
            'email' => ['nullable', 'email', Rule::unique('employes', 'email')->ignore($this->editId)],
            'telephone' => ['nullable', 'string', 'max:50'],
            'adresse' => ['nullable', 'string', 'max:500'],
            'contact_urgence_nom' => ['nullable', 'string', 'max:255'],
            'contact_urgence_telephone' => ['nullable', 'string', 'max:50'],
            'poste' => ['nullable', 'string', 'max:255'],
            'categorie' => ['nullable', 'string', 'max:100'],
            'departement_id' => ['nullable', 'exists:departements,id'],
            'date_embauche' => ['nullable', 'date'],
            'matricule_pointeuse' => ['nullable', 'string', 'max:50', Rule::unique('employes', 'matricule_pointeuse')->ignore($this->editId)],
            'actif' => ['boolean'],
            'numero_piece_identite' => ['nullable', 'string', 'max:100'],
            'numero_cnss' => ['nullable', 'string', 'max:50'],
            'banque' => ['nullable', 'string', 'max:150'],
            'numero_compte_bancaire' => ['nullable', 'string', 'max:100'],
            'jours_conge_acquis' => ['nullable', 'numeric', 'min:0', 'max:365'],
        ]);

        unset($donnees['photo'], $donnees['jours_conge_acquis']);

        // Sans habilitation, les données sensibles ne sont ni affichées ni modifiables.
        if (! auth()->user()->peutVoirDonneesSensibles()) {
            foreach (Employe::CHAMPS_SENSIBLES as $champ) {
                unset($donnees[$champ]);
            }
        }

        $employe = $this->editId ? Employe::findOrFail($this->editId) : new Employe;

        if ($this->photo !== null || $this->retirerPhoto) {
            if ($employe->photo_path !== null) {
                Storage::disk('public')->delete($employe->photo_path);
            }

            $donnees['photo_path'] = $this->photo?->store('photos-employes', 'public');
        }

        $employe->fill($donnees)->save();

        $this->enregistrerSoldeConge($employe);

        $this->resetForm();
    }

    public function annulerPhoto(): void
    {
        $this->reset('photo');
    }

    /**
     * Désactive ou réactive un employé depuis la liste. Un employé inactif garde sa fiche,
     * ses contrats et ses bulletins, mais sort de l'organigramme, de la présence et des
     * générations de paie à venir : c'est l'alternative à la suppression.
     */
    public function basculerActivite(int $id): void
    {
        $this->authorize('gerer', Employe::class);
        $this->reinitialiserMessages();

        $employe = Employe::findOrFail($id);
        $employe->update(['actif' => ! $employe->actif]);

        $this->information = sprintf(
            '%s a été %s.',
            $employe->nomComplet(),
            $employe->actif ? 'réactivé' : 'désactivé',
        );
    }

    /**
     * Un employé ayant des fiches de paie (brouillon ou validée) ne peut pas être supprimé :
     * ce sont des documents comptables. La base le refuse aussi (clé étrangère RESTRICT),
     * mais le cas est intercepté ici pour afficher un message clair.
     */
    public function supprimer(int $id): void
    {
        $this->authorize('gerer', Employe::class);
        $this->reinitialiserMessages();

        $employe = Employe::withCount('fichesPaie')->findOrFail($id);

        if ($employe->fiches_paie_count > 0) {
            $this->addError('suppression', $this->messageSuppressionImpossible($employe));

            return;
        }

        try {
            $photo = $employe->photo_path;
            $employe->delete();

            if ($photo !== null) {
                Storage::disk('public')->delete($photo);
            }
        } catch (QueryException) {
            $this->addError('suppression', $this->messageSuppressionImpossible($employe));
        }
    }

    public function annuler(): void
    {
        $this->resetForm();
    }

    /**
     * @return array<string, mixed>
     */
    protected function colonnesTriables(): array
    {
        return [
            'nom' => ['nom', 'prenom'],
            'matricule' => 'matricule',
            'poste' => 'poste',
            'departement' => Departement::select('nom')->whereColumn('departements.id', 'employes.departement_id'),
            'date_embauche' => 'date_embauche',
            'actif' => 'actif',
        ];
    }

    /**
     * @return string[]
     */
    protected function colonnesDecroissantesDAbord(): array
    {
        return ['date_embauche', 'actif'];
    }

    private function reinitialiserMessages(): void
    {
        $this->resetErrorBag('suppression');
        $this->information = null;
    }

    private function messageSuppressionImpossible(Employe $employe): string
    {
        return sprintf(
            'Impossible de supprimer %s : cet employé a des fiches de paie. Désactivez-le plutôt (champ « Employé actif » du formulaire de modification).',
            $employe->nomComplet(),
        );
    }

    /**
     * Enregistre le total de jours de congé annuel saisi à la main pour l'année en
     * cours. Le champ laissé vide ne touche à rien. Le mois en cours est marqué comme
     * déjà pris en compte : le crédit automatique reprend au mois suivant.
     */
    private function enregistrerSoldeConge(Employe $employe): void
    {
        $saisie = trim((string) $this->jours_conge_acquis);

        if ($saisie === '') {
            return;
        }

        $jours = round((float) str_replace(',', '.', $saisie), 2);
        $annee = now()->year;

        if ($jours === round($employe->joursCongeAcquis($annee), 2)) {
            return;
        }

        $solde = SoldeCongeAnnuel::query()->firstOrCreate(
            ['employe_id' => $employe->id, 'annee' => $annee],
            ['jours_acquis' => 0],
        );

        $solde->jours_acquis = $jours;
        $solde->dernier_mois_accumule = max((string) $solde->dernier_mois_accumule, now()->format('Y-m'));
        $solde->save();
    }

    private function formaterJours(float $jours): string
    {
        return rtrim(rtrim(number_format($jours, 2, ',', ''), '0'), ',');
    }

    /**
     * Champs texte facultatifs du dossier (hors données sensibles).
     *
     * @return string[]
     */
    private function champsTexte(): array
    {
        return [
            'matricule', 'sexe', 'lieu_naissance', 'nationalite', 'situation_familiale', 'telephone',
            'adresse', 'contact_urgence_nom', 'contact_urgence_telephone', 'categorie', 'matricule_pointeuse',
        ];
    }

    private function resetForm(): void
    {
        $this->reset([
            'formulaireOuvert', 'editId', 'matricule', 'prenom', 'nom', 'sexe', 'date_naissance', 'lieu_naissance',
            'nationalite', 'photo', 'retirerPhoto', 'situation_familiale', 'nombre_enfants', 'email', 'telephone',
            'adresse', 'contact_urgence_nom', 'contact_urgence_telephone', 'poste', 'categorie', 'departement_id',
            'date_embauche', 'matricule_pointeuse', 'actif', 'numero_piece_identite', 'numero_cnss', 'banque',
            'numero_compte_bancaire', 'jours_conge_acquis',
        ]);
        $this->resetValidation();
    }
}
