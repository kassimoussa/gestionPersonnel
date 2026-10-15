<?php

namespace App\Models;

use App\Enums\StatutJustificatif;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Justification d'une journée d'absence, avec la pièce fournie par l'employé.
 * Une absence justifiée acceptée change le statut de la journée dans le rapport
 * de présence.
 */
class JustificatifAbsence extends Model
{
    use HasFactory;

    protected $table = 'justificatifs_absence';

    /** Les pièces jointes ne sont pas publiques : elles passent par une route protégée. */
    public const DISQUE = 'local';

    public const DOSSIER = 'justificatifs-absence';

    protected $fillable = [
        'employe_id', 'date', 'motif', 'fichier_path', 'fichier_nom', 'statut',
        'saisi_par_id', 'decide_par_id', 'date_decision', 'commentaire_decision',
    ];

    protected $casts = [
        'date' => 'date',
        'statut' => StatutJustificatif::class,
        'date_decision' => 'datetime',
    ];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par_id');
    }

    public function decidePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decide_par_id');
    }

    /**
     * Justifications acceptées : ce sont les seules qui changent le rapport de présence.
     */
    #[Scope]
    protected function acceptes(Builder $query): Builder
    {
        return $query->where('statut', StatutJustificatif::Accepte);
    }

    public function estEnAttente(): bool
    {
        return $this->statut === StatutJustificatif::EnAttente;
    }

    public function estAccepte(): bool
    {
        return $this->statut === StatutJustificatif::Accepte;
    }

    public function aUnePiece(): bool
    {
        return $this->fichier_path !== null;
    }

    public function nomDeLaPiece(): string
    {
        return $this->fichier_nom ?: basename((string) $this->fichier_path);
    }

    /**
     * Accepte la justification : la journée devient une absence justifiée.
     */
    public function accepterPar(User $decideur, ?string $commentaire = null): void
    {
        $this->fill([
            'statut' => StatutJustificatif::Accepte,
            'decide_par_id' => $decideur->id,
            'date_decision' => now(),
            'commentaire_decision' => $commentaire,
        ])->save();
    }

    /**
     * Refuse la justification : la journée reste une absence non justifiée.
     */
    public function refuserPar(User $decideur, string $motif): void
    {
        $this->fill([
            'statut' => StatutJustificatif::Refuse,
            'decide_par_id' => $decideur->id,
            'date_decision' => now(),
            'commentaire_decision' => $motif,
        ])->save();
    }

    /**
     * Supprime la pièce jointe du disque en même temps que la justification.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $justificatif): void {
            if ($justificatif->fichier_path !== null) {
                Storage::disk(self::DISQUE)->delete($justificatif->fichier_path);
            }
        });
    }
}
