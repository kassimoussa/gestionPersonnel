<?php

namespace Tests\Unit;

use App\Enums\TypeEvenementPointeuse;
use App\Services\Pointages\LecteurCsvPointeuse;
use PHPUnit\Framework\TestCase;

class LecteurCsvPointeuseTest extends TestCase
{
    /** @var string[] */
    private array $fichiersTemporaires = [];

    protected function tearDown(): void
    {
        foreach ($this->fichiersTemporaires as $fichier) {
            @unlink($fichier);
        }

        parent::tearDown();
    }

    public function test_lit_un_fichier_valide_et_normalise_les_valeurs(): void
    {
        $resultat = (new LecteurCsvPointeuse)->lire($this->fichier(
            "matricule_pointeuse;date;heure;type\n"
            ."A001;14/09/2026;07:58;Entrée\n"
            ."A001;2026-09-14;17:31:09;sortie\n"
        ));

        $this->assertSame([], $resultat->erreurs);
        $this->assertCount(2, $resultat->lignes);
        $this->assertSame(2, $resultat->nombreLignes());

        $premiere = $resultat->lignes[0];
        $this->assertSame(2, $premiere->numeroLigne);
        $this->assertSame('A001', $premiere->matricule);
        $this->assertSame('2026-09-14', $premiere->date);
        $this->assertSame('07:58:00', $premiere->heure);
        $this->assertSame(TypeEvenementPointeuse::Entree, $premiere->type);

        $this->assertSame('17:31:09', $resultat->lignes[1]->heure);
        $this->assertSame(TypeEvenementPointeuse::Sortie, $resultat->lignes[1]->type);
    }

    public function test_detecte_la_virgule_ignore_le_bom_et_accepte_un_ordre_de_colonnes_different(): void
    {
        $resultat = (new LecteurCsvPointeuse)->lire($this->fichier(
            "\xEF\xBB\xBFtype,heure,date,matricule_pointeuse\n"
            ."in,08:00,2026-09-14,B014\n"
            ."\n"
            ."out,17:00,2026-09-14,B014\n"
        ));

        $this->assertSame([], $resultat->erreurs);
        $this->assertCount(2, $resultat->lignes);
        $this->assertSame('B014', $resultat->lignes[0]->matricule);
        $this->assertSame(TypeEvenementPointeuse::Entree, $resultat->lignes[0]->type);
        $this->assertSame(4, $resultat->lignes[1]->numeroLigne);
    }

    public function test_journalise_les_lignes_invalides_sans_bloquer_les_autres(): void
    {
        $resultat = (new LecteurCsvPointeuse)->lire($this->fichier(
            "matricule_pointeuse;date;heure;type\n"
            ."A001;2026-09-14;08:00;entree\n"
            ."A001;31/02/2026;08:00;entree\n"
            ."A001;2026-09-14;25:00;entree\n"
            ."A001;2026-09-14;08:00;pause\n"
            .";2026-09-14;08:00;entree\n"
        ));

        $this->assertCount(1, $resultat->lignes);
        $this->assertCount(4, $resultat->erreurs);
        $this->assertSame(5, $resultat->nombreLignes());
        $this->assertSame([3, 4, 5, 6], array_column($resultat->erreurs, 'ligne'));
        $this->assertStringContainsString('Date invalide', $resultat->erreurs[0]['message']);
        $this->assertStringContainsString('Heure invalide', $resultat->erreurs[1]['message']);
        $this->assertStringContainsString('Type invalide', $resultat->erreurs[2]['message']);
        $this->assertStringContainsString('Matricule pointeuse vide', $resultat->erreurs[3]['message']);
        $this->assertSame('A001;2026-09-14;25:00;entree', $resultat->erreurs[1]['contenu']);
    }

    public function test_rejette_un_fichier_sans_les_colonnes_attendues(): void
    {
        $resultat = (new LecteurCsvPointeuse)->lire($this->fichier(
            "id;jour;badge\n"
            ."1;2026-09-14;A001\n"
        ));

        $this->assertSame([], $resultat->lignes);
        $this->assertCount(1, $resultat->erreurs);
        $this->assertSame(1, $resultat->erreurs[0]['ligne']);
        $this->assertStringContainsString('En-tête invalide', $resultat->erreurs[0]['message']);
    }

    private function fichier(string $contenu): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'pointages');
        file_put_contents($chemin, $contenu);
        $this->fichiersTemporaires[] = $chemin;

        return $chemin;
    }
}
