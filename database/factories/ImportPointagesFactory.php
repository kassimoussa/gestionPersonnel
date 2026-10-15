<?php

namespace Database\Factories;

use App\Models\ImportPointages;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportPointages>
 */
class ImportPointagesFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom_fichier' => 'pointages.csv',
            'importe_par_id' => User::factory(),
            'nombre_lignes_traitees' => 0,
            'nombre_erreurs' => 0,
            'erreurs_detail' => null,
        ];
    }
}
