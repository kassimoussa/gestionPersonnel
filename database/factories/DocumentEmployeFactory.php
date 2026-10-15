<?php

namespace Database\Factories;

use App\Enums\TypeDocumentEmploye;
use App\Models\DocumentEmploye;
use App\Models\Employe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentEmploye>
 */
class DocumentEmployeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employe_id' => Employe::factory(),
            'type' => TypeDocumentEmploye::Autre,
            'libelle' => 'Document du dossier',
            'date_document' => now()->subMonth()->toDateString(),
            'fichier_path' => DocumentEmploye::DOSSIER.'/exemple.pdf',
            'fichier_nom' => 'exemple.pdf',
            'taille_octets' => 120000,
        ];
    }

    public function type(TypeDocumentEmploye $type): static
    {
        return $this->state(fn (array $attributes) => ['type' => $type]);
    }
}
