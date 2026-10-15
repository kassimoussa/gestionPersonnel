<?php

namespace App\Models;

use App\Enums\SourcePointage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pointage extends Model
{
    use HasFactory;

    protected $fillable = [
        'employe_id', 'date', 'heure_arrivee', 'heure_depart',
        'source', 'import_id', 'saisi_par_id', 'commentaire',
    ];

    protected $casts = [
        'date' => 'date',
        'source' => SourcePointage::class,
    ];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(ImportPointages::class, 'import_id');
    }

    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par_id');
    }

    /**
     * En retard si l'arrivée dépasse heure_debut_travail + tolerance_retard_minutes
     * (config/personnel.php).
     */
    public function estEnRetard(): bool
    {
        if ($this->heure_arrivee === null) {
            return false;
        }

        $parametres = ParametresApplication::courants();
        $limite = self::enMinutes($parametres->heureDebutTravail()) + $parametres->toleranceRetardMinutes();

        return self::enMinutes($this->heure_arrivee) > $limite;
    }

    public function heureArriveeCourte(): ?string
    {
        return $this->heure_arrivee === null ? null : substr($this->heure_arrivee, 0, 5);
    }

    public function heureDepartCourte(): ?string
    {
        return $this->heure_depart === null ? null : substr($this->heure_depart, 0, 5);
    }

    /**
     * Convertit "HH:MM" ou "HH:MM:SS" en minutes depuis minuit.
     */
    public static function enMinutes(string $heure): int
    {
        [$heures, $minutes] = array_pad(explode(':', $heure), 2, '0');

        return (int) $heures * 60 + (int) $minutes;
    }
}
