<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Jour férié : ni travaillé, ni décompté des congés. Un jour « récurrent » est
 * une fête à date fixe (1er janvier, 27 juin) qui vaut pour toutes les années ;
 * les fêtes mobiles, comme les fêtes musulmanes, se saisissent chaque année.
 */
class JourFerie extends Model
{
    use HasFactory;

    protected $table = 'jours_feries';

    protected $fillable = ['date', 'libelle', 'recurrent'];

    protected $casts = [
        'date' => 'date',
        'recurrent' => 'boolean',
    ];

    /**
     * Dates à exclure des calculs : « AAAA-MM-JJ » pour une date précise,
     * « MM-JJ » pour une fête à date fixe valable chaque année.
     *
     * @return array<string, string> date => libellé
     */
    public static function pourLeCalcul(): array
    {
        if (! Schema::hasTable('jours_feries')) {
            return [];
        }

        $dates = [];

        foreach (self::query()->orderBy('date')->get() as $ferie) {
            $cle = $ferie->recurrent ? $ferie->date->format('m-d') : $ferie->date->format('Y-m-d');
            $dates[$cle] = $ferie->libelle;
        }

        return $dates;
    }

    public function libelleDate(): string
    {
        return $this->recurrent
            ? ucfirst($this->date->locale('fr')->isoFormat('D MMMM')).' (chaque année)'
            : ucfirst($this->date->locale('fr')->isoFormat('dddd D MMMM YYYY'));
    }
}
