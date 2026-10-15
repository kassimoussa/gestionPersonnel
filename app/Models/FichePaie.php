<?php

namespace App\Models;

use App\Enums\StatutFichePaie;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FichePaie extends Model
{
    use HasFactory;

    protected $table = 'fiches_paie';

    protected $fillable = [
        'employe_id', 'annee', 'mois', 'salaire_base', 'primes', 'indemnites', 'brut',
        'cnss_salarie', 'cnss_employeur', 'base_imposable', 'irpp', 'net_a_payer',
        'statut', 'validee_par_id', 'validee_le',
    ];

    protected $casts = [
        'annee' => 'integer',
        'mois' => 'integer',
        'salaire_base' => 'decimal:2',
        'primes' => 'decimal:2',
        'indemnites' => 'decimal:2',
        'brut' => 'decimal:2',
        'cnss_salarie' => 'decimal:2',
        'cnss_employeur' => 'decimal:2',
        'base_imposable' => 'decimal:2',
        'irpp' => 'decimal:2',
        'net_a_payer' => 'decimal:2',
        'statut' => StatutFichePaie::class,
        'validee_le' => 'datetime',
    ];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function valideePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par_id');
    }

    public function estValidee(): bool
    {
        return $this->statut === StatutFichePaie::Validee;
    }

    /**
     * Brut + part patronale CNSS.
     */
    public function coutEmployeur(): float
    {
        return round((float) $this->brut + (float) $this->cnss_employeur, 2);
    }

    public function periodeLibelle(): string
    {
        return self::libellePeriode($this->annee, $this->mois);
    }

    public static function libellePeriode(int $annee, int $mois): string
    {
        return ucfirst(CarbonImmutable::create($annee, $mois, 1)->locale('fr')->isoFormat('MMMM YYYY'));
    }

    /**
     * Formatage d'un montant en francs Djibouti : « 82 480 FD » (décimales seulement si nécessaires).
     */
    public static function montant(float|string|null $valeur): string
    {
        $valeur = (float) $valeur;
        $decimales = abs($valeur - round($valeur)) < 0.005 ? 0 : 2;

        return number_format($valeur, $decimales, ',', ' ').' '.config('paie.devise', 'FD');
    }
}
