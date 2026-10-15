<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportPointages extends Model
{
    use HasFactory;

    protected $table = 'imports_pointages';

    protected $fillable = [
        'nom_fichier', 'importe_par_id', 'nombre_lignes_traitees', 'nombre_erreurs', 'erreurs_detail',
    ];

    protected $casts = [
        'nombre_lignes_traitees' => 'integer',
        'nombre_erreurs' => 'integer',
        'erreurs_detail' => 'array',
    ];

    public function importePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'importe_par_id');
    }

    public function pointages(): HasMany
    {
        return $this->hasMany(Pointage::class, 'import_id');
    }
}
