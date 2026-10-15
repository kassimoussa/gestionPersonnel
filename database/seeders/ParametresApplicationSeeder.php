<?php

namespace Database\Seeders;

use App\Models\ParametresApplication;
use Illuminate\Database\Seeder;

class ParametresApplicationSeeder extends Seeder
{
    /**
     * Garantit la ligne unique de paramètres (nom de départ : « Gestion RH »).
     */
    public function run(): void
    {
        ParametresApplication::courants();
    }
}
