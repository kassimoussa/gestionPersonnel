<?php

namespace App\Livewire\Conges;

use App\Enums\StatutConge;
use App\Enums\TypeConge;
use App\Livewire\Concerns\RechercheEmploye;
use App\Livewire\Concerns\TriDeListe;
use App\Models\Conge;
use App\Models\Employe;
use App\Services\Conges\CalculPeriodeConge;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

/**
 * Congés du personnel : le service du personnel conduit tout le circuit. Il ouvre
 * la demande et remet le formulaire, classe le formulaire visé par le chef, établit
 * l'attestation et le récapitulatif pour la direction, puis enregistre la signature
 * du directeur général qui acte le congé.
 */
#[Layout('layouts.app')]
class Personnel extends Component
{
    use AuthorizesRequests, RechercheEmploye, TriDeListe, WithFileUploads, WithPagination;

    public bool $formulaireOuvert = false;

    public ?int $employe_id = null;

    public string $type = 'conge_annuel';

    public string $date_debut = '';

    public string $date_fin = '';

    public ?string $motif = '';

    /** Congé déjà accordé hors application : enregistré comme acté, sans circuit. */
    public bool $dejaAccorde = false;

    public int $nombreJours = 0;

    public ?string $dateReprise = null;

    /** Demande dont une étape est en cours de saisie. */
    public ?int $etapeId = null;

    public ?int $chefVisaId = null;

    public string $dateVisa = '';

    public $formulaireSigne = null;

    public string $dateSignature = '';

    public $attestationSignee = null;

    public ?int $refusId = null;

    public string $motifRefus = '';

    public ?int $filtreEmployeId = null;

    public string $filtreStatut = 'en_cours';

    public ?string $information = null;

    protected CalculPeriodeConge $calculPeriode;

    public function boot(CalculPeriodeConge $calculPeriode): void
    {
        $this->calculPeriode = $calculPeriode;
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->peutSaisirCongePourAutrui(), 403);
    }

    public function updatingFiltreEmployeId(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreStatut(): void
    {
        $this->resetPage();
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['date_debut', 'date_fin'], true)) {
            $this->nombreJours = $this->calculerNombreJours();
            $this->dateReprise = $this->calculerDateReprise();
        }
    }

    public function render()
    {
        $utilisateur = auth()->user();

        $conges = Conge::query()
            ->whereIn('employe_id', $utilisateur->employesSupervises()->select('id'))
            ->when($this->filtreStatut === 'en_cours', fn ($q) => $q->enCours())
            ->when($this->filtreStatut === 'acte', fn ($q) => $q->where('statut', StatutConge::Acte))
            ->when($this->filtreStatut === 'clos', fn ($q) => $q->whereIn('statut', [StatutConge::Refuse, StatutConge::Annule]))
            ->when($this->filtreEmployeId, fn ($q) => $q->where('employe_id', $this->filtreEmployeId))
            ->with(['employe.departement.responsable', 'saisiPar', 'chefVisa', 'acteePar'])
            ->tap(fn ($requete) => $this->appliquerTri($requete))
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.conges.personnel', [
            'conges' => $conges,
            'employes' => $utilisateur->employesSupervises()->where('actif', true)->orderBy('nom')->orderBy('prenom')->get(),
            'employesTrouves' => $this->employesTrouves(),
            'employeChoisi' => $this->employeChoisi(),
            'types' => TypeConge::cases(),
            'soldeDisponible' => $this->soldeDisponibleEmployeChoisi(),
            'peutEnregistrerAccorde' => $utilisateur->can('enregistrerCommeAccorde', Conge::class),
            'etape' => $this->etapeId ? Conge::with('employe.departement.responsable')->find($this->etapeId) : null,
            'congeRefuse' => $this->refusId ? Conge::with('employe')->find($this->refusId) : null,
            'chefsPossibles' => Employe::query()->where('actif', true)->orderBy('nom')->orderBy('prenom')->get(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function colonnesTriables(): array
    {
        return [
            'date_debut' => 'date_debut',
            'employe' => Employe::select('nom')->whereColumn('employes.id', 'conges.employe_id'),
            'type' => 'type',
            'nombre_jours' => 'nombre_jours',
            'statut' => 'statut',
        ];
    }

    /**
     * @return string[]
     */
    protected function colonnesDecroissantesDAbord(): array
    {
        return ['date_debut', 'nombre_jours'];
    }

    public function nouveau(): void
    {
        $this->reinitialiser();
        $this->formulaireOuvert = true;
    }

    public function annuler(): void
    {
        $this->reinitialiser();
    }

    /**
     * Étape 1 : ouvrir la demande et remettre le formulaire à l'employé.
     */
    public function enregistrer(): void
    {
        $utilisateur = auth()->user();
        $this->motif = $this->motif ?: null;
        $this->information = null;

        $donnees = $this->validate([
            'employe_id' => ['required', Rule::exists('employes', 'id')],
            'type' => ['required', Rule::enum(TypeConge::class)],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'motif' => ['nullable', 'string', 'max:1000'],
        ]);

        $employe = Employe::findOrFail($donnees['employe_id']);

        $this->authorize('creerPour', [Conge::class, $employe]);

        if ($this->dejaAccorde) {
            $this->authorize('enregistrerCommeAccorde', Conge::class);
        }

        $type = TypeConge::from($donnees['type']);
        $nombreJours = $this->calculPeriode->nombreJours($donnees['date_debut'], $donnees['date_fin']);

        if (! $this->calculPeriode->contientUnJourTravaille($donnees['date_debut'], $donnees['date_fin'])) {
            throw ValidationException::withMessages([
                'date_fin' => 'La période tombe entièrement sur des jours chômés : aucun jour ne serait décompté.',
            ]);
        }

        if ($type->consommeSolde()) {
            $anneeDemande = (int) substr($donnees['date_debut'], 0, 4);
            $disponible = $employe->soldeCongeDisponible($anneeDemande);

            if ($nombreJours > $disponible) {
                throw ValidationException::withMessages([
                    'date_fin' => sprintf(
                        'Solde de congé annuel insuffisant pour %d : %d jour(s) demandé(s), %s jour(s) disponible(s).',
                        $anneeDemande,
                        $nombreJours,
                        number_format($disponible, 1, ',', ' '),
                    ),
                ]);
            }
        }

        $employe->conges()->create([
            'type' => $type,
            'date_debut' => $donnees['date_debut'],
            'date_fin' => $donnees['date_fin'],
            'nombre_jours' => $nombreJours,
            'motif' => $donnees['motif'],
            'statut' => $this->dejaAccorde ? StatutConge::Acte : StatutConge::FormulaireRemis,
            'saisi_par_id' => $utilisateur->id,
        ]);

        $message = $this->dejaAccorde
            ? sprintf('Congé de %s enregistré comme déjà accordé.', $employe->nomComplet())
            : sprintf('Demande ouverte pour %s : imprimez le formulaire et remettez-le-lui à faire viser.', $employe->nomComplet());

        $this->reinitialiser();
        $this->information = $message;
    }

    /**
     * Étape 2 : le formulaire visé par le chef revient au service du personnel.
     */
    public function ouvrirVisa(int $id): void
    {
        $conge = $this->congeConduit($id);

        $this->reinitialiser();
        $this->etapeId = $conge->id;
        $this->chefVisaId = $conge->employe?->departement?->responsable_id;
        $this->dateVisa = now()->toDateString();
    }

    public function enregistrerVisa(): void
    {
        $conge = $this->congeConduit($this->etapeId);

        $donnees = $this->validate([
            'chefVisaId' => ['required', Rule::exists('employes', 'id')],
            'dateVisa' => ['required', 'date', 'before_or_equal:today'],
            'formulaireSigne' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ], [
            'chefVisaId.required' => 'Indiquez le chef qui a visé le formulaire.',
            'formulaireSigne.required' => 'Joignez le formulaire signé par le chef.',
            'formulaireSigne.max' => 'Le formulaire ne doit pas dépasser 2 Mo.',
        ]);

        $conge->enregistrerLeVisa(
            Employe::findOrFail($donnees['chefVisaId']),
            CarbonImmutable::parse($donnees['dateVisa']),
            $this->formulaireSigne->store(Conge::DOSSIER, Conge::DISQUE),
            $this->formulaireSigne->getClientOriginalName(),
        );

        $this->reinitialiser();
        $this->information = "Formulaire visé enregistré : établissez l'attestation et le récapitulatif.";
    }

    /**
     * Étape 3 : l'attestation est établie et numérotée pour la signature du DG.
     */
    public function etablirAttestation(int $id): void
    {
        $conge = $this->congeConduit($id);

        abort_unless($conge->statut === StatutConge::ViseParLeChef, 403, 'Le formulaire visé doit être enregistré avant.');

        $conge->etablirAttestationPar(auth()->user());

        $this->information = sprintf(
            'Attestation n° %s établie : imprimez-la avec le récapitulatif pour la signature du directeur général.',
            $conge->fresh()->referenceAttestation(),
        );
    }

    /**
     * Étape 4 : la signature du directeur général acte le congé.
     */
    public function ouvrirSignature(int $id): void
    {
        $conge = $this->congeConduit($id);

        $this->reinitialiser();
        $this->etapeId = $conge->id;
        $this->dateSignature = now()->toDateString();
    }

    public function enregistrerSignature(): void
    {
        $conge = $this->congeConduit($this->etapeId);

        $donnees = $this->validate([
            'dateSignature' => ['required', 'date', 'before_or_equal:today'],
            'attestationSignee' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ], [
            'attestationSignee.required' => "Joignez l'attestation signée par le directeur général.",
            'attestationSignee.max' => "L'attestation ne doit pas dépasser 2 Mo.",
        ]);

        $conge->acterPar(
            auth()->user(),
            CarbonImmutable::parse($donnees['dateSignature']),
            $this->attestationSignee->store(Conge::DOSSIER, Conge::DISQUE),
            $this->attestationSignee->getClientOriginalName(),
        );

        $this->reinitialiser();
        $this->information = "Congé acté : le solde de l'employé est décompté.";
    }

    public function refuser(int $id): void
    {
        $this->congeConduit($id);

        $this->reinitialiser();
        $this->refusId = $id;
    }

    public function confirmerRefus(): void
    {
        $conge = $this->congeConduit($this->refusId);

        $this->validate(['motifRefus' => ['required', 'string', 'max:1000']]);

        $conge->refuserPar(auth()->user(), $this->motifRefus);

        $this->reinitialiser();
        $this->information = 'Demande refusée : le motif reste au dossier.';
    }

    public function annulerEtape(): void
    {
        $this->reinitialiser();
    }

    /**
     * Retire une demande ouverte par erreur, tant qu'elle n'est pas actée.
     */
    public function supprimer(int $id): void
    {
        $conge = Conge::findOrFail($id);

        $this->authorize('annuler', $conge);

        $conge->delete();

        $this->information = 'Demande retirée.';
    }

    private function congeConduit(?int $id): Conge
    {
        $conge = Conge::with('employe.departement')->findOrFail($id);

        $this->authorize('conduire', $conge);

        return $conge;
    }

    /**
     * Périmètre de la recherche : les employés actifs que l'utilisateur suit.
     */
    protected function employesRecherchables(): Builder
    {
        return auth()->user()->employesSupervises()->where('actif', true)->with('departement');
    }

    private function soldeDisponibleEmployeChoisi(): ?float
    {
        if ($this->employe_id === null) {
            return null;
        }

        $annee = $this->date_debut !== '' ? (int) substr($this->date_debut, 0, 4) : now()->year;

        return Employe::find($this->employe_id)?->soldeCongeDisponible($annee);
    }

    private function calculerNombreJours(): int
    {
        if ($this->date_debut === '' || $this->date_fin === '') {
            return 0;
        }

        try {
            return $this->calculPeriode->nombreJours($this->date_debut, $this->date_fin);
        } catch (Throwable) {
            return 0;
        }
    }

    private function calculerDateReprise(): ?string
    {
        if ($this->date_fin === '') {
            return null;
        }

        try {
            return $this->calculPeriode->jourReprise($this->date_fin)->format('d/m/Y');
        } catch (Throwable) {
            return null;
        }
    }

    private function reinitialiser(): void
    {
        $this->reset([
            'formulaireOuvert', 'employe_id', 'type', 'date_debut', 'date_fin', 'motif', 'dejaAccorde',
            'nombreJours', 'dateReprise', 'etapeId', 'chefVisaId', 'dateVisa', 'formulaireSigne',
            'dateSignature', 'attestationSignee', 'refusId', 'motifRefus', 'rechercheEmploye',
        ]);
        $this->resetValidation();
    }
}
