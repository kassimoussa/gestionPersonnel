<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoldeCongeAnnuel extends Model
{
    use HasFactory;

    protected $table = 'soldes_conges_annuels';

    protected $fillable = ['employe_id', 'annee', 'jours_acquis', 'dernier_mois_accumule'];

    protected $casts = [
        'annee' => 'integer',
        'jours_acquis' => 'decimal:2',
    ];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }
}
