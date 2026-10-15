<?php

namespace Tests\Feature;

use App\Enums\SourcePointage;
use App\Livewire\Presence\Saisie;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\Pointage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PresenceSaisieTest extends TestCase
{
    use RefreshDatabase;

    private User $compteManager;

    private Employe $employe;

    private Employe $autreEmploye;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-14 12:00:00');

        $manager = Employe::factory()->create();
        $departement = Departement::factory()->create(['responsable_id' => $manager->id]);
        $this->employe = Employe::factory()->create(['prenom' => 'Moussa', 'nom' => 'Demandeur', 'departement_id' => $departement->id]);
        $this->autreEmploye = Employe::factory()->create(['prenom' => 'Hodan', 'nom' => 'Ailleurs', 'departement_id' => Departement::factory()->create()->id]);
        $this->compteManager = User::factory()->create(['employe_id' => $manager->id]);
    }

    public function test_la_page_est_reservee_aux_managers_et_a_la_dg(): void
    {
        $this->actingAs(User::factory()->create())->get('/presence/saisie')->assertForbidden();
        $this->actingAs($this->compteManager)->get('/presence/saisie')->assertOk()->assertSeeLivewire(Saisie::class);
        $this->actingAs($this->compteDg())->get('/presence/saisie')->assertOk();
    }

    public function test_le_manager_ne_voit_que_son_equipe_et_saisit_un_pointage(): void
    {
        Livewire::actingAs($this->compteManager)
            ->test(Saisie::class)
            ->assertSee('Moussa Demandeur')
            ->assertDontSee('Hodan Ailleurs')
            ->call('nouveau')
            ->set('employe_id', $this->employe->id)
            ->set('date', '2026-09-13')
            ->set('heure_arrivee', '08:20')
            ->set('heure_depart', '17:00')
            ->set('commentaire', 'Oubli de badge')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSet('formulaireOuvert', false);

        $pointage = Pointage::sole();
        $this->assertSame($this->employe->id, $pointage->employe_id);
        $this->assertSame('2026-09-13', $pointage->date->toDateString());
        $this->assertSame('08:20:00', $pointage->heure_arrivee);
        $this->assertSame('17:00:00', $pointage->heure_depart);
        $this->assertSame(SourcePointage::Manuel, $pointage->source);
        $this->assertSame($this->compteManager->id, $pointage->saisi_par_id);
        $this->assertSame('Oubli de badge', $pointage->commentaire);
        $this->assertTrue($pointage->estEnRetard());
    }

    public function test_le_manager_ne_peut_pas_saisir_pour_un_employe_hors_de_son_equipe(): void
    {
        Livewire::actingAs($this->compteManager)
            ->test(Saisie::class)
            ->call('nouveau')
            ->set('employe_id', $this->autreEmploye->id)
            ->set('date', '2026-09-13')
            ->set('heure_arrivee', '08:00')
            ->call('enregistrer')
            ->assertForbidden();

        $this->assertSame(0, Pointage::count());
    }

    public function test_la_dg_saisit_pour_tout_le_monde(): void
    {
        Livewire::actingAs($this->compteDg())
            ->test(Saisie::class)
            ->assertSee('Moussa Demandeur')
            ->assertSee('Hodan Ailleurs')
            ->call('nouveau')
            ->set('employe_id', $this->autreEmploye->id)
            ->set('date', '2026-09-13')
            ->set('heure_arrivee', '08:00')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame($this->autreEmploye->id, Pointage::sole()->employe_id);
    }

    public function test_une_correction_remplace_le_pointage_importe_du_jour(): void
    {
        $importe = Pointage::factory()->for($this->employe)->create(['date' => '2026-09-13', 'heure_arrivee' => '09:00:00', 'heure_depart' => null]);

        Livewire::actingAs($this->compteManager)
            ->test(Saisie::class)
            ->call('editer', $importe->id)
            ->assertSet('employe_id', $this->employe->id)
            ->assertSet('date', '2026-09-13')
            ->assertSet('heure_arrivee', '09:00')
            ->set('heure_arrivee', '08:00')
            ->set('heure_depart', '17:00')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $pointage = Pointage::sole();
        $this->assertSame($importe->id, $pointage->id);
        $this->assertSame('08:00:00', $pointage->heure_arrivee);
        $this->assertSame('17:00:00', $pointage->heure_depart);
        $this->assertSame(SourcePointage::Manuel, $pointage->source);
        $this->assertSame($this->compteManager->id, $pointage->saisi_par_id);
    }

    public function test_les_heures_sont_validees(): void
    {
        $composant = Livewire::actingAs($this->compteManager)
            ->test(Saisie::class)
            ->call('nouveau')
            ->set('employe_id', $this->employe->id)
            ->set('date', '2026-09-13');

        $composant->set('heure_arrivee', '')->set('heure_depart', '')->call('enregistrer')
            ->assertHasErrors(['heure_arrivee']);

        $composant->set('heure_arrivee', '17:00')->set('heure_depart', '08:00')->call('enregistrer')
            ->assertHasErrors(['heure_depart']);

        $composant->set('date', '2026-09-20')->set('heure_arrivee', '08:00')->set('heure_depart', '')->call('enregistrer')
            ->assertHasErrors(['date']);

        $this->assertSame(0, Pointage::count());
    }

    public function test_le_manager_supprime_un_pointage_de_son_equipe_uniquement(): void
    {
        $sien = Pointage::factory()->for($this->employe)->create(['date' => '2026-09-13']);
        $autre = Pointage::factory()->for($this->autreEmploye)->create(['date' => '2026-09-13']);

        Livewire::actingAs($this->compteManager)->test(Saisie::class)->call('supprimer', $sien->id);
        Livewire::actingAs($this->compteManager)->test(Saisie::class)->call('supprimer', $autre->id)->assertForbidden();

        $this->assertModelMissing($sien);
        $this->assertModelExists($autre);
    }

    private function compteDg(): User
    {
        $dg = $this->creerCompteDg();

        return $dg;
    }
}
