<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contrat extends Model
{
    use HasFactory;

    protected $fillable = [
        'employe_id', 'type', 'date_debut', 'date_fin', 'salaire_base',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'salaire_base' => 'decimal:2',
    ];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }
}
