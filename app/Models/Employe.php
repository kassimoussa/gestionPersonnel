<?php

namespace App\Models;

use App\Enums\StatutConge;
use App\Enums\TypeConge;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Employe extends Model
{
    use HasFactory;

    public const SEXES = ['F' => 'Femme', 'M' => 'Homme'];

    public const SITUATIONS_FAMILIALES = [
        'celibataire' => 'Célibataire',
        'marie' => 'Marié(e)',
        'divorce' => 'Divorcé(e)',
        'veuf' => 'Veuf ou veuve',
    ];

    /** Champs réservés à la DG et à l'administrateur (voir User::peutVoirDonneesSensibles()). */
    public const CHAMPS_SENSIBLES = ['numero_piece_identite', 'numero_cnss', 'banque', 'numero_compte_bancaire'];

    protected $fillable = [
        'matricule', 'nom', 'prenom', 'photo_path', 'sexe', 'date_naissance', 'lieu_naissance', 'nationalite',
        'situation_familiale', 'nombre_enfants', 'email', 'telephone', 'adresse',
        'contact_urgence_nom', 'contact_urgence_telephone', 'matricule_pointeuse',
        'poste', 'categorie', 'departement_id', 'date_embauche', 'actif',
        'numero_piece_identite', 'numero_cnss', 'banque', 'numero_compte_bancaire',
    ];

    protected $casts = [
        'date_embauche' => 'date',
        'date_naissance' => 'date',
        'nombre_enfants' => 'integer',
        'actif' => 'boolean',
    ];

    public function photoUrl(): ?string
    {
        if ($this->photo_path === null || ! Storage::disk('public')->exists($this->photo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->photo_path);
    }

    public function age(): ?int
    {
        return $this->date_naissance?->age;
    }

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    /**
     * Départements dont cet employé est le responsable (= dont il est le manager).
     */
    public function departementsDiriges(): HasMany
    {
        return $this->hasMany(Departement::class, 'responsable_id');
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }

    /**
     * Compte Breeze lié à cette fiche employé, s'il existe.
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * Pièces du dossier : contrat signé, diplôme, pièce d'identité...
     */
    public function documents(): HasMany
    {
        return $this->hasMany(DocumentEmploye::class);
    }

    /**
     * Sanctions et faits de carrière consignés au dossier.
     */
    public function evenements(): HasMany
    {
        return $this->hasMany(EvenementEmploye::class);
    }

    public function conges(): HasMany
    {
        return $this->hasMany(Conge::class);
    }

    public function soldesCongesAnnuels(): HasMany
    {
        return $this->hasMany(SoldeCongeAnnuel::class);
    }

    public function pointages(): HasMany
    {
        return $this->hasMany(Pointage::class);
    }

    /**
     * Fiches de paie (documents comptables) : leur présence interdit la suppression de l'employé.
     */
    public function fichesPaie(): HasMany
    {
        return $this->hasMany(FichePaie::class);
    }

    public function contratActuel(): ?Contrat
    {
        return $this->contrats()
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>=', now()))
            ->latest('date_debut')
            ->first();
    }

    /**
     * Civilité employée dans les documents officiels, d'après le sexe renseigné.
     */
    public function civilite(): ?string
    {
        return match ($this->sexe) {
            'F' => 'Madame',
            'M' => 'Monsieur',
            default => null,
        };
    }

    public function nomComplet(): string
    {
        return "{$this->prenom} {$this->nom}";
    }

    /**
     * Un employé est manager d'un autre s'il est le responsable du département
     * de celui-ci (departements.responsable_id). Rien n'est stocké en double.
     */
    public function estManagerDe(Employe $autre): bool
    {
        $responsableId = $autre->departement?->responsable_id;

        return $responsableId !== null && (int) $responsableId === (int) $this->id;
    }

    /**
     * Nombre de mises à pied prononcées, et total des jours suspendus.
     *
     * @return array{nombre: int, jours: int}
     */
    public function bilanMisesAPied(): array
    {
        $misesAPied = $this->evenements()->misesAPied()->get();

        return [
            'nombre' => $misesAPied->count(),
            'jours' => (int) $misesAPied->sum(fn (EvenementEmploye $evenement) => $evenement->nombreJours()),
        ];
    }

    /**
     * Nombre de sanctions disciplinaires, toutes natures confondues.
     */
    public function nombreDeSanctions(): int
    {
        return $this->evenements()->sanctions()->count();
    }

    /**
     * Jours de congé annuel crédités au titre d'une année précise.
     */
    public function joursCongeAcquis(?int $annee = null): float
    {
        $annee ??= now()->year;

        return (float) ($this->soldesCongesAnnuels()->where('annee', $annee)->value('jours_acquis') ?? 0);
    }

    /**
     * Droits cumulés depuis le début : les congés non pris se reportent d'une
     * année sur l'autre, sans être perdus au 31 décembre.
     */
    public function joursCongeAcquisCumules(?int $annee = null): float
    {
        $annee ??= now()->year;

        return (float) $this->soldesCongesAnnuels()->where('annee', '<=', $annee)->sum('jours_acquis');
    }

    /**
     * Reliquat des années antérieures, reporté sur l'année demandée.
     */
    public function joursCongeReportes(?int $annee = null): float
    {
        $annee ??= now()->year;

        return round($this->joursCongeAcquisCumules($annee - 1) - $this->joursCongeAnnuelApprouves($annee - 1, true), 2);
    }

    /**
     * Congés annuels approuvés : sur l'année seule, ou depuis toujours.
     */
    /**
     * Congés actés : ce sont eux qui consomment le solde.
     */
    public function joursCongeAnnuelApprouves(?int $annee = null, bool $cumule = false): float
    {
        return $this->sommeJoursCongeAnnuel([StatutConge::Acte], $annee, $cumule);
    }

    /**
     * Demandes encore dans le circuit : elles réservent le solde sans le consommer.
     */
    public function joursCongeAnnuelEnAttente(?int $annee = null, bool $cumule = false): float
    {
        return $this->sommeJoursCongeAnnuel(StatutConge::enCours(), $annee, $cumule);
    }

    /**
     * Solde restant, report compris : tous les droits acquis jusqu'à cette année,
     * moins tous les congés annuels approuvés.
     */
    public function soldeCongeRestant(?int $annee = null): float
    {
        $annee ??= now()->year;

        return round($this->joursCongeAcquisCumules($annee) - $this->joursCongeAnnuelApprouves($annee, true), 2);
    }

    /**
     * Solde réellement disponible pour une nouvelle demande : le solde restant
     * moins les demandes de congé annuel encore en attente de validation.
     */
    public function soldeCongeDisponible(?int $annee = null): float
    {
        $annee ??= now()->year;

        return round($this->soldeCongeRestant($annee) - $this->joursCongeAnnuelEnAttente($annee, true), 2);
    }

    /**
     * @param  StatutConge[]  $statuts
     */
    private function sommeJoursCongeAnnuel(array $statuts, ?int $annee, bool $cumule = false): float
    {
        $annee ??= now()->year;

        return (float) $this->conges()
            ->where('type', TypeConge::CongeAnnuel)
            ->whereIn('statut', $statuts)
            ->when(
                $cumule,
                fn ($q) => $q->whereYear('date_debut', '<=', $annee),
                fn ($q) => $q->whereYear('date_debut', $annee),
            )
            ->sum('nombre_jours');
    }
}
