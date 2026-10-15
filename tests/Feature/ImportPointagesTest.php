<?php

namespace Tests\Feature;

use App\Enums\SourcePointage;
use App\Livewire\Presence\Import;
use App\Models\Employe;
use App\Models\ImportPointages;
use App\Models\Pointage;
use App\Models\User;
use App\Services\Pointages\ImportateurPointages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class ImportPointagesTest extends TestCase
{
    use RefreshDatabase;

    private User $dg;

    /** @var string[] */
    private array $fichiersTemporaires = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-14 12:00:00');

        $this->dg = $this->creerCompteDg();
    }

    protected function tearDown(): void
    {
        foreach ($this->fichiersTemporaires as $fichier) {
            @unlink($fichier);
        }

        parent::tearDown();
    }

    public function test_regroupe_les_evenements_d_une_journee_en_un_seul_pointage(): void
    {
        $employe = Employe::factory()->create(['matricule_pointeuse' => 'A001']);

        $resultat = app(ImportateurPointages::class)->importer($this->fichier(
            "matricule_pointeuse;date;heure;type\n"
            ."A001;2026-09-13;08:02;entree\n"
            ."A001;2026-09-13;12:00;sortie\n"
            ."A001;2026-09-13;13:05;entree\n"
            ."A001;2026-09-13;17:31;sortie\n"
        ), 'export.csv', $this->dg);

        $pointage = Pointage::sole();
        $this->assertSame($employe->id, $pointage->employe_id);
        $this->assertSame('2026-09-13', $pointage->date->toDateString());
        $this->assertSame('08:02:00', $pointage->heure_arrivee);
        $this->assertSame('17:31:00', $pointage->heure_depart);
        $this->assertSame(SourcePointage::Import, $pointage->source);
        $this->assertSame($resultat->import->id, $pointage->import_id);

        $this->assertSame(1, $resultat->pointagesCrees);
        $this->assertSame(0, $resultat->pointagesMisAJour);
        $this->assertSame('export.csv', $resultat->import->nom_fichier);
        $this->assertSame($this->dg->id, $resultat->import->importe_par_id);
        $this->assertSame(4, $resultat->import->nombre_lignes_traitees);
        $this->assertSame(0, $resultat->import->nombre_erreurs);
        $this->assertNull($resultat->import->erreurs_detail);
    }

    public function test_journalise_les_matricules_inconnus_et_les_lignes_invalides_sans_bloquer(): void
    {
        Employe::factory()->create(['matricule_pointeuse' => 'A001']);

        $resultat = app(ImportateurPointages::class)->importer($this->fichier(
            "matricule_pointeuse;date;heure;type\n"
            ."ZZZ9;2026-09-13;08:00;entree\n"
            ."A001;2026-09-13;08:00;entree\n"
            ."A001;2026-09-13;xx:00;sortie\n"
        ), 'export.csv', $this->dg);

        $this->assertSame(1, Pointage::count());
        $this->assertSame(3, $resultat->import->nombre_lignes_traitees);
        $this->assertSame(2, $resultat->import->nombre_erreurs);
        $this->assertSame([2, 4], array_column($resultat->import->erreurs_detail, 'ligne'));
        $this->assertStringContainsString('ZZZ9', $resultat->import->erreurs_detail[0]['message']);
    }

    public function test_fusionne_un_pointage_importe_et_preserve_une_saisie_manuelle(): void
    {
        $importe = Employe::factory()->create(['matricule_pointeuse' => 'A001']);
        $saisi = Employe::factory()->create(['matricule_pointeuse' => 'A002']);
        Pointage::factory()->for($importe)->create(['date' => '2026-09-13', 'heure_arrivee' => '09:00:00', 'heure_depart' => null]);
        Pointage::factory()->for($saisi)->manuel()->create(['date' => '2026-09-13', 'heure_arrivee' => '08:00:00', 'heure_depart' => '16:00:00']);

        $resultat = app(ImportateurPointages::class)->importer($this->fichier(
            "matricule_pointeuse;date;heure;type\n"
            ."A001;2026-09-13;08:30;entree\n"
            ."A001;2026-09-13;17:00;sortie\n"
            ."A002;2026-09-13;10:00;entree\n"
            ."A002;2026-09-13;18:00;sortie\n"
        ), 'export.csv', $this->dg);

        $fusionne = Pointage::where('employe_id', $importe->id)->sole();
        $this->assertSame('08:30:00', $fusionne->heure_arrivee);
        $this->assertSame('17:00:00', $fusionne->heure_depart);
        $this->assertSame($resultat->import->id, $fusionne->import_id);

        $manuel = Pointage::where('employe_id', $saisi->id)->sole();
        $this->assertSame('08:00:00', $manuel->heure_arrivee);
        $this->assertSame('16:00:00', $manuel->heure_depart);
        $this->assertSame(SourcePointage::Manuel, $manuel->source);

        $this->assertSame(0, $resultat->pointagesCrees);
        $this->assertSame(1, $resultat->pointagesMisAJour);
        $this->assertSame(1, $resultat->joursIgnores);
        $this->assertSame(1, $resultat->import->nombre_erreurs);
        $this->assertStringContainsString('saisi manuellement', $resultat->import->erreurs_detail[0]['message']);
    }

    public function test_la_suppression_d_un_utilisateur_conserve_l_historique_de_ses_imports(): void
    {
        $import = ImportPointages::factory()->create([
            'importe_par_id' => $this->dg->id,
            'nombre_lignes_traitees' => 5,
            'nombre_erreurs' => 2,
            'erreurs_detail' => [['ligne' => 3, 'message' => 'Date invalide', 'contenu' => 'x']],
        ]);

        $this->dg->delete();

        $conserve = ImportPointages::find($import->id);
        $this->assertNotNull($conserve);
        $this->assertNull($conserve->importe_par_id);
        $this->assertSame(5, $conserve->nombre_lignes_traitees);
        $this->assertSame(2, $conserve->nombre_erreurs);
        $this->assertSame('Date invalide', $conserve->erreurs_detail[0]['message']);
    }

    public function test_l_ecran_d_import_est_reserve_a_la_dg(): void
    {
        $manager = User::factory()->create(['employe_id' => Employe::factory()->create()->id]);

        $this->actingAs($manager)->get('/presence/import')->assertForbidden();
        $this->actingAs($this->dg)->get('/presence/import')->assertOk()->assertSeeLivewire(Import::class);
    }

    public function test_la_dg_importe_un_fichier_et_voit_le_resume(): void
    {
        Employe::factory()->create(['matricule_pointeuse' => 'A001']);

        $fichier = UploadedFile::fake()->createWithContent(
            'pointages-septembre.csv',
            "matricule_pointeuse;date;heure;type\nA001;2026-09-13;08:00;entree\nA001;2026-09-13;17:00;sortie\nINCONNU;2026-09-13;08:00;entree\n",
        );

        Livewire::actingAs($this->dg)
            ->test(Import::class)
            ->set('fichier', $fichier)
            ->call('importer')
            ->assertHasNoErrors()
            ->assertSet('pointagesCrees', 1)
            ->assertSee('pointages-septembre.csv')
            ->assertSee('3 ligne(s) traitée(s), 1 erreur(s)')
            ->assertSee('INCONNU');

        $this->assertSame(1, Pointage::count());
        $this->assertSame(1, ImportPointages::count());
    }

    public function test_un_fichier_qui_n_est_pas_un_csv_est_refuse(): void
    {
        Livewire::actingAs($this->dg)
            ->test(Import::class)
            ->set('fichier', UploadedFile::fake()->create('pointages.pdf', 10, 'application/pdf'))
            ->call('importer')
            ->assertHasErrors(['fichier']);

        $this->assertSame(0, ImportPointages::count());
    }

    private function fichier(string $contenu): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'pointages');
        file_put_contents($chemin, $contenu);
        $this->fichiersTemporaires[] = $chemin;

        return $chemin;
    }
}
