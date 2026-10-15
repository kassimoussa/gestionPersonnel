<?php

namespace App\Models;

use App\Enums\StatutConge;
use App\Enums\TypeConge;
use App\Services\Conges\CalculPeriodeConge;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Demande de congé et son circuit : formulaire remis par le service du personnel,
 * visé par le chef, attestation établie puis signée par le directeur général.
 * Les pièces signées sont conservées avec la demande, sur le disque privé.
 */
class Conge extends Model
{
    use HasFactory;

    protected $table = 'conges';

    public const DISQUE = 'local';

    public const DOSSIER = 'conges';

    protected $fillable = [
        'employe_id', 'type', 'date_debut', 'date_fin', 'nombre_jours', 'motif', 'statut',
        'saisi_par_id',
        'manager_validateur_id', 'date_decision_manager',
        'dg_validateur_id', 'date_decision_dg',
        'commentaire_refus',
        'formulaire_signe_path', 'formulaire_signe_nom',
        'attestation_signee_path', 'attestation_signee_nom',
        'annee_attestation', 'numero_attestation', 'date_attestation', 'attestation_par_id',
    ];

    protected $casts = [
        'type' => TypeConge::class,
        'statut' => StatutConge::class,
        'date_debut' => 'date',
        'date_fin' => 'date',
        'nombre_jours' => 'decimal:2',
        'date_decision_manager' => 'datetime',
        'date_decision_dg' => 'datetime',
        'annee_attestation' => 'integer',
        'numero_attestation' => 'integer',
        'date_attestation' => 'datetime',
    ];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    /**
     * Compte du service du personnel ayant ouvert la demande.
     */
    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par_id');
    }

    /**
     * Chef ayant visé le formulaire.
     */
    public function chefVisa(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'manager_validateur_id');
    }

    /**
     * Compte ayant enregistré la signature du directeur général.
     */
    public function acteePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dg_validateur_id');
    }

    public function attestationPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attestation_par_id');
    }

    /**
     * Demandes qui attendent une action du service du personnel.
     */
    #[Scope]
    protected function enCours(Builder $query): Builder
    {
        return $query->whereIn('statut', array_map(
            fn (StatutConge $statut) => $statut->value,
            StatutConge::enCours(),
        ));
    }

    /**
     * Étape 2 : le chef a visé le formulaire, que le service du personnel classe.
     */
    public function enregistrerLeVisa(Employe $chef, CarbonImmutable $date, ?string $chemin, ?string $nom): void
    {
        $this->exigerEtape(StatutConge::FormulaireRemis);

        if ($chemin !== null) {
            $this->remplacerLaPiece('formulaire_signe_path', $chemin, $nom);
        }

        $this->fill([
            'statut' => StatutConge::ViseParLeChef,
            'manager_validateur_id' => $chef->id,
            'date_decision_manager' => $date,
        ])->save();
    }

    /**
     * Étape 3 : l'attestation est établie et numérotée, prête pour la signature.
     */
    public function etablirAttestationPar(User $utilisateur): void
    {
        if ($this->statut === StatutConge::ViseParLeChef) {
            $this->statut = StatutConge::AttestationASigner;
        }

        if ($this->attestationEtablie()) {
            $this->save();

            return;
        }

        DB::transaction(function () use ($utilisateur): void {
            $annee = (int) now()->year;

            $dernier = static::query()
                ->where('annee_attestation', $annee)
                ->lockForUpdate()
                ->max('numero_attestation');

            $this->fill([
                'annee_attestation' => $annee,
                'numero_attestation' => (int) $dernier + 1,
                'date_attestation' => now(),
                'attestation_par_id' => $utilisateur->id,
            ])->save();
        });
    }

    /**
     * Étape 4 : le directeur général a signé, le congé est acté.
     */
    public function acterPar(User $utilisateur, CarbonImmutable $dateSignature, ?string $chemin, ?string $nom): void
    {
        $this->exigerEtape(StatutConge::AttestationASigner);

        if ($chemin !== null) {
            $this->remplacerLaPiece('attestation_signee_path', $chemin, $nom);
        }

        $this->fill([
            'statut' => StatutConge::Acte,
            'dg_validateur_id' => $utilisateur->id,
            'date_decision_dg' => $dateSignature,
        ])->save();
    }

    /**
     * Refus à n'importe quelle étape : le motif reste au dossier.
     */
    public function refuserPar(User $utilisateur, string $motif): void
    {
        if (! $this->statut->estEnCours()) {
            throw new LogicException("Cette demande n'est plus en cours de traitement.");
        }

        $this->fill([
            'statut' => StatutConge::Refuse,
            'dg_validateur_id' => $utilisateur->id,
            'date_decision_dg' => now(),
            'commentaire_refus' => $motif,
        ])->save();
    }

    public function estActe(): bool
    {
        return $this->statut->estActe();
    }

    public function estEnCours(): bool
    {
        return $this->statut->estEnCours();
    }

    /**
     * Congé déjà accordé hors application (circuit papier ancien), repris au système.
     */
    public function estEnregistrementRetroactif(): bool
    {
        return $this->estActe() && $this->date_decision_dg === null && $this->saisi_par_id !== null;
    }

    /**
     * Premier jour travaillé qui suit le congé, annoncé sur l'attestation.
     */
    public function jourReprise(): CarbonImmutable
    {
        return app(CalculPeriodeConge::class)->jourReprise($this->date_fin);
    }

    public function attestationEtablie(): bool
    {
        return $this->numero_attestation !== null;
    }

    public function formulaireSigneRecu(): bool
    {
        return $this->formulaire_signe_path !== null;
    }

    public function attestationSigneeRecue(): bool
    {
        return $this->attestation_signee_path !== null;
    }

    /**
     * Référence du document, numérotée par année civile comme le registre papier.
     */
    public function referenceAttestation(): ?string
    {
        if (! $this->attestationEtablie()) {
            return null;
        }

        return sprintf('%d/%s', $this->numero_attestation, ParametresApplication::courants()->nom_application);
    }

    /**
     * Les pièces suivent la demande : elles disparaissent avec elle.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $conge): void {
            foreach (['formulaire_signe_path', 'attestation_signee_path'] as $colonne) {
                if ($conge->{$colonne} !== null) {
                    Storage::disk(self::DISQUE)->delete($conge->{$colonne});
                }
            }
        });
    }

    private function remplacerLaPiece(string $colonne, string $chemin, ?string $nom): void
    {
        if ($this->{$colonne} !== null) {
            Storage::disk(self::DISQUE)->delete($this->{$colonne});
        }

        $this->{$colonne} = $chemin;
        $this->{str_replace('_path', '_nom', $colonne)} = $nom;
    }

    private function exigerEtape(StatutConge $attendu): void
    {
        if ($this->statut !== $attendu) {
            throw new LogicException(sprintf(
                'Cette demande est à l\'étape « %s » : l\'action attendue n\'est pas celle-ci.',
                $this->statut->libelle(),
            ));
        }
    }
}
