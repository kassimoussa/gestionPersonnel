<?php

namespace App\Models;

use App\Enums\TypeEvenementEmploye;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Événement du dossier d'un agent : sanction disciplinaire ou fait de carrière.
 * La pièce jointe (décision, note de service) vit sur le disque privé.
 */
class EvenementEmploye extends Model
{
    use HasFactory;

    protected $table = 'evenements_employe';

    public const DISQUE = 'local';

    public const DOSSIER = 'evenements-employes';

    protected $fillable = [
        'employe_id', 'type', 'date_debut', 'date_fin', 'avec_solde', 'motif',
        'reference', 'fichier_path', 'fichier_nom', 'enregistre_par_id',
    ];

    protected $casts = [
        'type' => TypeEvenementEmploye::class,
        'date_debut' => 'date',
        'date_fin' => 'date',
        'avec_solde' => 'boolean',
    ];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function enregistrePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enregistre_par_id');
    }

    /**
     * Sanctions disciplinaires seulement : ce sont elles que l'on compte.
     */
    #[Scope]
    protected function sanctions(Builder $query): Builder
    {
        return $query->whereIn('type', array_map(
            fn (TypeEvenementEmploye $type) => $type->value,
            TypeEvenementEmploye::sanctions(),
        ));
    }

    #[Scope]
    protected function misesAPied(Builder $query): Builder
    {
        return $query->where('type', TypeEvenementEmploye::MiseAPied);
    }

    public function estSanction(): bool
    {
        return $this->type->estSanction();
    }

    /**
     * Nombre de jours calendaires couverts par l'événement, bornes incluses.
     */
    public function nombreJours(): int
    {
        if ($this->date_fin === null) {
            return $this->type->suspendLeTravail() ? 1 : 0;
        }

        return (int) $this->date_debut->diffInDays($this->date_fin) + 1;
    }

    public function couvre(\DateTimeInterface $jour): bool
    {
        $debut = $this->date_debut->copy()->startOfDay();
        $fin = ($this->date_fin ?? $this->date_debut)->copy()->endOfDay();

        return $jour >= $debut && $jour <= $fin;
    }

    /**
     * Période affichée : « du 12/03/2026 au 14/03/2026 » ou une date seule.
     */
    public function periode(): string
    {
        if ($this->date_fin === null || $this->date_fin->isSameDay($this->date_debut)) {
            return $this->date_debut->format('d/m/Y');
        }

        return sprintf('du %s au %s', $this->date_debut->format('d/m/Y'), $this->date_fin->format('d/m/Y'));
    }

    public function aUnePiece(): bool
    {
        return $this->fichier_path !== null;
    }

    protected static function booted(): void
    {
        static::deleting(function (self $evenement): void {
            if ($evenement->fichier_path !== null) {
                Storage::disk(self::DISQUE)->delete($evenement->fichier_path);
            }
        });
    }
}
