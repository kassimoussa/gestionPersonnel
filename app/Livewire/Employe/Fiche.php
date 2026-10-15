<?php

namespace App\Livewire\Employe;

use App\Enums\StatutPresence;
use App\Enums\TypeDocumentEmploye;
use App\Enums\TypeEvenementEmploye;
use App\Models\DocumentEmploye;
use App\Models\Employe;
use App\Models\EvenementEmploye;
use App\Services\Pointages\RapportPresence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Fiche d'un employé : tout son dossier sur une page — identité, poste, contrat,
 * congés, présence du mois et pièces jointes. Chacun peut ouvrir la sienne.
 */
#[Layout('layouts.app')]
class Fiche extends Component
{
    use AuthorizesRequests, WithFileUploads;

    public Employe $employe;

    public bool $formulaireDocument = false;

    public bool $formulaireEvenement = false;

    public string $evenementType = 'avertissement';

    public string $evenementDebut = '';

    public ?string $evenementFin = '';

    public ?string $evenementAvecSolde = '';

    public string $evenementMotif = '';

    public ?string $evenementReference = '';

    public $evenementPiece = null;

    public string $type = 'autre';

    public string $libelle = '';

    public ?string $date_document = '';

    public $fichier = null;

    public ?string $information = null;

    public function mount(Employe $employe): void
    {
        $this->authorize('voirLaFiche', $employe);

        $this->employe = $employe;
    }

    public function render(RapportPresence $rapport)
    {
        $utilisateur = auth()->user();
        $annee = now()->year;
        $debutMois = CarbonImmutable::today()->startOfMonth();
        $finMois = min(CarbonImmutable::today(), $debutMois->endOfMonth()->startOfDay());

        $presence = ['present' => 0, 'retard' => 0, 'conge' => 0, 'absence' => 0, 'absence_justifiee' => 0];

        if ($this->employe->actif) {
            $lignes = $rapport->generer(collect([$this->employe]), $debutMois, $finMois);
            $presence = $lignes[0]['totaux'] ?? $presence;
        }

        $peutVoirLesDocuments = $utilisateur->can('consulterCeuxDe', [DocumentEmploye::class, $this->employe]);
        $peutVoirLesEvenements = $utilisateur->can('consulterCeuxDe', [EvenementEmploye::class, $this->employe]);

        return view('livewire.employe.fiche', [
            'employe' => $this->employe->load(['departement.responsable', 'user']),
            'contrat' => $this->employe->contratActuel(),
            'annee' => $annee,
            'soldeConge' => [
                'reporte' => $this->employe->joursCongeReportes($annee),
                'acquis' => $this->employe->joursCongeAcquis($annee),
                'approuves' => $this->employe->joursCongeAnnuelApprouves($annee),
                'restant' => $this->employe->soldeCongeRestant($annee),
                'disponible' => $this->employe->soldeCongeDisponible($annee),
            ],
            'presence' => $presence,
            'libelleMois' => ucfirst($debutMois->locale('fr')->isoFormat('MMMM YYYY')),
            'statutsPresence' => StatutPresence::cases(),
            'bulletins' => $utilisateur->peutPreparerPaie()
                ? $this->employe->fichesPaie()->orderByDesc('annee')->orderByDesc('mois')->limit(6)->get()
                : collect(),
            'peutVoirLaPaie' => $utilisateur->peutPreparerPaie(),
            'congesRecents' => $this->employe->conges()->orderByDesc('date_debut')->orderByDesc('id')->limit(5)->get(),
            'documents' => $peutVoirLesDocuments
                ? $this->employe->documents()->with('ajoutePar')->orderByDesc('date_document')->orderByDesc('id')->get()
                : collect(),
            'peutVoirLesDocuments' => $peutVoirLesDocuments,
            'peutAjouterUnDocument' => $utilisateur->can('ajouterPour', [DocumentEmploye::class, $this->employe]),
            'peutModifier' => $utilisateur->can('gerer', Employe::class),
            'evenements' => $peutVoirLesEvenements
                ? $this->employe->evenements()->with('enregistrePar')->orderByDesc('date_debut')->orderByDesc('id')->get()
                : collect(),
            'peutVoirLesEvenements' => $peutVoirLesEvenements,
            'peutEnregistrerUnEvenement' => $utilisateur->can('enregistrerPour', [EvenementEmploye::class, $this->employe]),
            'bilanMisesAPied' => $peutVoirLesEvenements ? $this->employe->bilanMisesAPied() : ['nombre' => 0, 'jours' => 0],
            'nombreSanctions' => $peutVoirLesEvenements ? $this->employe->nombreDeSanctions() : 0,
            'typesSanction' => TypeEvenementEmploye::sanctions(),
            'typesCarriere' => TypeEvenementEmploye::faitsDeCarriere(),
            'voitDonneesSensibles' => $utilisateur->peutVoirDonneesSensibles(),
            'typesDocument' => TypeDocumentEmploye::cases(),
        ]);
    }

    public function ajouterUnDocument(): void
    {
        $this->authorize('ajouterPour', [DocumentEmploye::class, $this->employe]);

        $this->reinitialiserDocument();
        $this->formulaireDocument = true;
    }

    public function annulerDocument(): void
    {
        $this->reinitialiserDocument();
    }

    public function enregistrerDocument(): void
    {
        $this->authorize('ajouterPour', [DocumentEmploye::class, $this->employe]);

        $this->date_document = trim((string) $this->date_document) ?: null;

        $donnees = $this->validate([
            'type' => ['required', Rule::enum(TypeDocumentEmploye::class)],
            'libelle' => ['required', 'string', 'max:255'],
            'date_document' => ['nullable', 'date'],
            'fichier' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:2048'],
        ], [
            'fichier.required' => 'Choisissez le fichier à joindre.',
            'fichier.max' => 'Le document ne doit pas dépasser 2 Mo.',
            'fichier.mimes' => 'Formats acceptés : PDF, image, Word.',
        ]);

        $this->employe->documents()->create([
            'type' => TypeDocumentEmploye::from($donnees['type']),
            'libelle' => $donnees['libelle'],
            'date_document' => $donnees['date_document'],
            'fichier_path' => $this->fichier->store(DocumentEmploye::DOSSIER, DocumentEmploye::DISQUE),
            'fichier_nom' => $this->fichier->getClientOriginalName(),
            'taille_octets' => $this->fichier->getSize(),
            'ajoute_par_id' => auth()->id(),
        ]);

        $this->reinitialiserDocument();
        $this->information = 'Document ajouté au dossier.';
    }

    public function supprimerDocument(int $id): void
    {
        $document = DocumentEmploye::findOrFail($id);

        $this->authorize('supprimer', $document);

        $document->delete();

        $this->information = 'Document retiré du dossier.';
    }

    public function ajouterUnEvenement(): void
    {
        $this->authorize('enregistrerPour', [EvenementEmploye::class, $this->employe]);

        $this->reinitialiserEvenement();
        $this->evenementDebut = now()->toDateString();
        $this->formulaireEvenement = true;
    }

    public function annulerEvenement(): void
    {
        $this->reinitialiserEvenement();
    }

    public function enregistrerEvenement(): void
    {
        $this->authorize('enregistrerPour', [EvenementEmploye::class, $this->employe]);

        $this->evenementFin = trim((string) $this->evenementFin) ?: null;
        $this->evenementReference = trim((string) $this->evenementReference) ?: null;

        $donnees = $this->validate([
            'evenementType' => ['required', Rule::enum(TypeEvenementEmploye::class)],
            'evenementDebut' => ['required', 'date'],
            'evenementFin' => ['nullable', 'date', 'after_or_equal:evenementDebut'],
            'evenementMotif' => ['required', 'string', 'max:1000'],
            'evenementReference' => ['nullable', 'string', 'max:255'],
            'evenementAvecSolde' => ['nullable', Rule::in(['', 'avec', 'sans'])],
            'evenementPiece' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:2048'],
        ], [
            'evenementFin.after_or_equal' => 'La fin ne peut pas précéder le début.',
            'evenementMotif.required' => 'Le motif est obligatoire : il justifie la décision au dossier.',
            'evenementPiece.max' => 'La pièce ne doit pas dépasser 2 Mo.',
        ], [
            'evenementType' => 'type',
            'evenementDebut' => 'date de début',
            'evenementFin' => 'date de fin',
            'evenementMotif' => 'motif',
            'evenementReference' => 'référence',
            'evenementPiece' => 'pièce',
        ]);

        $type = TypeEvenementEmploye::from($donnees['evenementType']);

        $this->employe->evenements()->create([
            'type' => $type,
            'date_debut' => $donnees['evenementDebut'],
            'date_fin' => $type->suspendLeTravail() ? $donnees['evenementFin'] : null,
            'avec_solde' => $type->suspendLeTravail() && $donnees['evenementAvecSolde'] !== ''
                ? $donnees['evenementAvecSolde'] === 'avec'
                : null,
            'motif' => $donnees['evenementMotif'],
            'reference' => $donnees['evenementReference'],
            'fichier_path' => $this->evenementPiece?->store(EvenementEmploye::DOSSIER, EvenementEmploye::DISQUE),
            'fichier_nom' => $this->evenementPiece?->getClientOriginalName(),
            'enregistre_par_id' => auth()->id(),
        ]);

        $this->reinitialiserEvenement();
        $this->information = $type->libelle().' consigné(e) au dossier.';
    }

    public function supprimerEvenement(int $id): void
    {
        $evenement = EvenementEmploye::findOrFail($id);

        $this->authorize('supprimer', $evenement);

        $evenement->delete();

        $this->information = 'Événement retiré du dossier.';
    }

    private function reinitialiserEvenement(): void
    {
        $this->reset([
            'formulaireEvenement', 'evenementType', 'evenementDebut', 'evenementFin',
            'evenementAvecSolde', 'evenementMotif', 'evenementReference', 'evenementPiece', 'information',
        ]);
        $this->resetValidation();
    }

    private function reinitialiserDocument(): void
    {
        $this->reset(['formulaireDocument', 'type', 'libelle', 'date_document', 'fichier', 'information']);
        $this->resetValidation();
    }
}
