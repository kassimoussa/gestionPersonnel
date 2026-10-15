<?php

namespace Tests\Feature;

use App\Enums\TypeDepartement;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\ParametresApplication;
use App\Services\LecteurClasseurXlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * Import de l'annuaire exporté par la pointeuse : une fiche par personne
 * enregistrée dans la machine, avec son identifiant et son service.
 */
class ImporterAnnuairePointeuseTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] */
    private array $fichiers = [];

    private const ENTETES = ['Employee ID', 'First Name', 'Department', 'Device Privilege'];

    protected function tearDown(): void
    {
        foreach ($this->fichiers as $fichier) {
            @unlink($fichier);
        }

        parent::tearDown();
    }

    public function test_l_annuaire_cree_les_fiches_avec_leur_matricule_pointeuse(): void
    {
        $fichier = $this->classeur([
            ['Employee'],
            self::ENTETES,
            ['1001', 'IBADO BOUH GUESSOD', 'Direction Générale', 'Employee'],
            ['1002', 'MOHAMED ISSE AMIN', 'Department', 'Employee'],
        ]);

        $this->artisan('pointeuse:importer-annuaire', ['fichier' => $fichier])
            ->expectsOutputToContain('2 fiche(s) créée(s)')
            ->assertSuccessful();

        $ibado = Employe::where('matricule_pointeuse', '1001')->sole();
        // Le matricule de l'employé et son identifiant dans la pointeuse sont le même numéro.
        $this->assertSame('1001', $ibado->matricule);
        $this->assertSame('Ibado', $ibado->prenom);
        $this->assertSame('Bouh Guessod', $ibado->nom);
        $this->assertSame('Direction Générale', $ibado->departement?->nom);
        $this->assertTrue($ibado->actif);
        $this->assertNull($ibado->email);
        $this->assertNull($ibado->poste);

        // « Department » est la valeur par défaut de la machine : aucune unité.
        $this->assertNull(Employe::where('matricule_pointeuse', '1002')->sole()->departement_id);
    }

    public function test_une_unite_deja_enregistree_est_reutilisee_malgre_le_prefixe(): void
    {
        $informatique = Departement::factory()->create(['nom' => 'Informatique', 'type' => TypeDepartement::Direction]);

        $fichier = $this->classeur([
            self::ENTETES,
            ['1019', 'ISMAEL HASSAN IBRAHIM', 'Service Informatique', 'Employee'],
        ]);

        $this->artisan('pointeuse:importer-annuaire', ['fichier' => $fichier])->assertSuccessful();

        $this->assertSame($informatique->id, Employe::sole()->departement_id);
        $this->assertSame(1, Departement::count());
    }

    public function test_un_service_inconnu_est_cree_sous_la_direction_generale(): void
    {
        $direction = Departement::factory()->create(['nom' => 'Direction générale', 'type' => TypeDepartement::Direction]);
        ParametresApplication::courants()->update(['direction_generale_id' => $direction->id]);

        $fichier = $this->classeur([
            self::ENTETES,
            ['1016', 'KALTOUM ALI ARALEH', 'Service Comptablite', 'Employee'],
            ['1012', 'MOUSTAPHA AHMED MOUSSA', 'Direction ONEQ', 'Employee'],
        ]);

        $this->artisan('pointeuse:importer-annuaire', ['fichier' => $fichier])->assertSuccessful();

        $comptabilite = Departement::where('nom', 'Service Comptablite')->sole();
        $this->assertSame(TypeDepartement::Service, $comptabilite->type);
        $this->assertSame($direction->id, $comptabilite->parent_departement_id);

        // Une unité annoncée comme direction reste au sommet de l'organigramme.
        $oneq = Departement::where('nom', 'Direction ONEQ')->sole();
        $this->assertSame(TypeDepartement::Direction, $oneq->type);
        $this->assertNull($oneq->parent_departement_id);
    }

    public function test_une_fiche_existante_du_meme_nom_recoit_le_matricule_sans_doublon(): void
    {
        $existant = Employe::factory()->create(['prenom' => 'Kassim', 'nom' => 'Moussa', 'matricule_pointeuse' => null]);

        $fichier = $this->classeur([
            self::ENTETES,
            ['1040', 'KASSIM MOUSSA', 'Direction Emploi', 'Employee'],
        ]);

        $this->artisan('pointeuse:importer-annuaire', ['fichier' => $fichier])
            ->expectsOutputToContain('1 rattachée(s) à une fiche existante')
            ->assertSuccessful();

        $this->assertSame(1, Employe::count());
        $this->assertSame('1040', $existant->fresh()->matricule_pointeuse);
        $this->assertSame('1040', $existant->fresh()->matricule);
    }

    public function test_un_second_import_ne_cree_pas_de_doublon(): void
    {
        $fichier = $this->classeur([
            self::ENTETES,
            ['1001', 'IBADO BOUH GUESSOD', 'Direction Emploi', 'Employee'],
        ]);

        $this->artisan('pointeuse:importer-annuaire', ['fichier' => $fichier])->assertSuccessful();
        $this->artisan('pointeuse:importer-annuaire', ['fichier' => $fichier])
            ->expectsOutputToContain('0 fiche(s) créée(s)')
            ->expectsOutputToContain('1 déjà liée(s) à la pointeuse')
            ->assertSuccessful();

        $this->assertSame(1, Employe::count());
    }

    public function test_la_simulation_n_enregistre_rien(): void
    {
        $fichier = $this->classeur([
            self::ENTETES,
            ['1001', 'IBADO BOUH GUESSOD', 'Direction Emploi', 'Employee'],
        ]);

        $this->artisan('pointeuse:importer-annuaire', ['fichier' => $fichier, '--simulation' => true])
            ->expectsOutputToContain('1 fiche(s) à créer (simulation)')
            ->assertSuccessful();

        $this->assertSame(0, Employe::count());
        $this->assertSame(0, Departement::count());
    }

    public function test_un_classeur_sans_les_bonnes_colonnes_est_refuse(): void
    {
        $fichier = $this->classeur([
            ['Identifiant', 'Nom', 'Service'],
            ['1001', 'IBADO BOUH GUESSOD', 'Direction Emploi'],
        ]);

        $this->artisan('pointeuse:importer-annuaire', ['fichier' => $fichier])
            ->expectsOutputToContain('En-têtes introuvables')
            ->assertFailed();

        $this->assertSame(0, Employe::count());
    }

    public function test_le_lecteur_rend_les_cellules_dans_l_ordre_des_colonnes(): void
    {
        $fichier = $this->classeur([
            ['Employee ID', 'First Name'],
            ['1001', 'IBADO BOUH GUESSOD'],
        ]);

        $lignes = app(LecteurClasseurXlsx::class)->lignes($fichier);

        $this->assertSame([['Employee ID', 'First Name'], ['1001', 'IBADO BOUH GUESSOD']], $lignes);
    }

    /**
     * Fabrique un classeur .xlsx minimal : le format est une archive zip contenant
     * les chaînes partagées et la feuille.
     *
     * @param  array<int, array<int, string>>  $lignes
     */
    private function classeur(array $lignes): string
    {
        $chaines = [];

        foreach ($lignes as $ligne) {
            foreach ($ligne as $valeur) {
                if (! in_array($valeur, $chaines, true)) {
                    $chaines[] = $valeur;
                }
            }
        }

        $feuille = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($lignes as $numero => $ligne) {
            $feuille .= '<row r="'.($numero + 1).'">';

            foreach ($ligne as $colonne => $valeur) {
                $reference = chr(65 + $colonne).($numero + 1);
                $feuille .= '<c r="'.$reference.'" t="s"><v>'.array_search($valeur, $chaines, true).'</v></c>';
            }

            $feuille .= '</row>';
        }

        $feuille .= '</sheetData></worksheet>';

        $partagees = '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        foreach ($chaines as $chaine) {
            $partagees .= '<si><t>'.htmlspecialchars($chaine, ENT_XML1).'</t></si>';
        }

        $partagees .= '</sst>';

        $chemin = tempnam(sys_get_temp_dir(), 'pointeuse').'.xlsx';
        $this->fichiers[] = $chemin;

        $archive = new ZipArchive;
        $archive->open($chemin, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $archive->addFromString('xl/sharedStrings.xml', $partagees);
        $archive->addFromString('xl/worksheets/sheet1.xml', $feuille);
        $archive->close();

        return $chemin;
    }
}
