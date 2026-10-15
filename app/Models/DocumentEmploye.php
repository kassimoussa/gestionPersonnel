<?php

namespace App\Models;

use App\Enums\TypeDocumentEmploye;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Pièce du dossier d'un employé. Le fichier vit sur le disque privé :
 * il n'est accessible que par la route protégée qui vérifie les droits.
 */
class DocumentEmploye extends Model
{
    use HasFactory;

    protected $table = 'documents_employe';

    public const DISQUE = 'local';

    public const DOSSIER = 'documents-employes';

    protected $fillable = [
        'employe_id', 'type', 'libelle', 'date_document',
        'fichier_path', 'fichier_nom', 'taille_octets', 'ajoute_par_id',
    ];

    protected $casts = [
        'type' => TypeDocumentEmploye::class,
        'date_document' => 'date',
        'taille_octets' => 'integer',
    ];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function ajoutePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ajoute_par_id');
    }

    /**
     * Poids du fichier en unité lisible : « 240 Ko », « 1,4 Mo ».
     */
    public function taille(): string
    {
        $octets = (int) $this->taille_octets;

        if ($octets <= 0) {
            return '—';
        }

        if ($octets < 1024) {
            return $octets.' o';
        }

        if ($octets < 1024 * 1024) {
            return round($octets / 1024).' Ko';
        }

        return number_format($octets / 1048576, 1, ',', ' ').' Mo';
    }

    public function existeSurLeDisque(): bool
    {
        return Storage::disk(self::DISQUE)->exists($this->fichier_path);
    }

    /**
     * Le fichier disparaît avec la ligne.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $document): void {
            Storage::disk(self::DISQUE)->delete($document->fichier_path);
        });
    }
}
