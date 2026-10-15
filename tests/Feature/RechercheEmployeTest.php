<?php

namespace Tests\Feature;

use App\Livewire\Conges\Personnel;
use App\Livewire\Presence\Saisie;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\Pointage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Choix d'un employé par recherche libre : nom, matricule ou numéro de pointeuse.
 */
class RechercheEmployeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-21 09:00:00');

        Employe::factory()->create([
            'prenom' => 'Abdirazak', 'nom' => 'Abdi Hassan', 'matricule' => '1067',
            'matricule_pointeuse' => '1067', 'poste' => 'Chargé de programme',
        ]);
        Employe::factory()->create([
            'prenom' => 'Ladane', 'nom' => 'Assoweh', 'matricule' => '1042',
            'matricule_pointeuse' => '1042', 'poste' => 'Coordinatrice',
        ]);
    }

    public function test_la_recherche_trouve_par_nom_par_prenom_et_par_matricule(): void
    {
        $composant = Livewire::actingAs($this->creerCompteRh())->test(Personnel::class)->call('nouveau');

        $composant->set('rechercheEmploye', 'abdirazak')->assertViewHas('employesTrouves', $this->trouve('Abdirazak Abdi Hassan'));
        $composant->set('rechercheEmploye', 'assoweh')->assertViewHas('employesTrouves', $this->trouve('Ladane Assoweh'));
        $composant->set('rechercheEmploye', '1067')->assertViewHas('employesTrouves', $this->trouve('Abdirazak Abdi Hassan'));
        $composant->set('rechercheEmploye', 'coordinatrice')->assertViewHas('employesTrouves', $this->trouve('Ladane Assoweh'));
    }

    public function test_plusieurs_mots_affinent_la_recherche(): void
    {
        Employe::factory()->create(['prenom' => 'Abdirazak', 'nom' => 'Omar', 'matricule' => '1099']);

        Livewire::actingAs($this->creerCompteRh())
            ->test(Personnel::class)
            ->call('nouveau')
            ->set('rechercheEmploye', 'abdirazak hassan')
            ->assertViewHas('employesTrouves', $this->trouve('Abdirazak Abdi Hassan'));
    }

    public function test_choisir_un_employe_le_retient_et_ferme_les_suggestions(): void
    {
        $employe = Employe::where('matricule', '1067')->sole();

        Livewire::actingAs($this->creerCompteRh())
            ->test(Personnel::class)
            ->call('nouveau')
            ->set('rechercheEmploye', '1067')
            ->call('choisirEmploye', $employe->id)
            ->assertSet('employe_id', $employe->id)
            ->assertSet('rechercheEmploye', 'Abdirazak Abdi Hassan')
            ->assertViewHas('employesTrouves', fn ($resultats) => $resultats->isEmpty())
            ->assertSee('Matricule 1067')
            ->call('effacerEmploye')
            ->assertSet('employe_id', null)
            ->assertSet('rechercheEmploye', '');
    }

    public function test_une_recherche_vide_ne_propose_personne(): void
    {
        Livewire::actingAs($this->creerCompteRh())
            ->test(Personnel::class)
            ->call('nouveau')
            ->assertViewHas('employesTrouves', fn ($resultats) => $resultats->isEmpty());
    }

    public function test_la_recherche_respecte_le_perimetre_de_l_utilisateur(): void
    {
        $manager = Employe::factory()->create();
        $departement = Departement::factory()->create(['responsable_id' => $manager->id]);
        $membre = Employe::factory()->create([
            'prenom' => 'Moussa', 'nom' => 'Equipe', 'departement_id' => $departement->id, 'matricule' => '1200',
        ]);
        $compteManager = User::factory()->create(['employe_id' => $manager->id]);

        Livewire::actingAs($compteManager)
            ->test(Saisie::class)
            ->call('nouveau')
            ->set('rechercheEmploye', 'a')
            ->assertViewHas('employesTrouves', $this->trouve('Moussa Equipe'));

        // Un employé hors périmètre ne peut pas être choisi non plus.
        $etranger = Employe::where('matricule', '1067')->sole();

        Livewire::actingAs($compteManager)
            ->test(Saisie::class)
            ->call('nouveau')
            ->call('choisirEmploye', $etranger->id)
            ->assertSet('employe_id', null);

        $this->assertNotNull($membre->id);
    }

    /**
     * Vérifie que la liste de suggestions contient exactement ces employés.
     */
    private function trouve(string ...$noms): callable
    {
        return function ($resultats) use ($noms): bool {
            $trouves = $resultats->map(fn ($employe) => $employe->nomComplet())->sort()->values()->all();
            sort($noms);

            return $trouves === $noms;
        };
    }

    public function test_la_correction_d_un_pointage_fige_l_employe(): void
    {
        $employe = Employe::where('matricule', '1042')->sole();
        $pointage = Pointage::factory()->for($employe)->create(['date' => '2026-09-20']);

        Livewire::actingAs($this->creerCompteRh())
            ->test(Saisie::class)
            ->call('editer', $pointage->id)
            ->assertSet('employe_id', $employe->id)
            ->assertSee('Ladane Assoweh')
            ->assertDontSee('Changer');
    }
}
