<?php

namespace Tests\Feature;

use App\Livewire\Departement\Index as DepartementIndex;
use App\Livewire\Parametres\Index as ParametresIndex;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\ParametresApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DirectionGeneraleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Departement $directionGenerale;

    private User $compteAncien;

    private User $compteNouveau;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->creerCompteAdmin();

        $this->directionGenerale = Departement::factory()->create(['nom' => 'Direction générale']);
        ParametresApplication::courants()->update(['direction_generale_id' => $this->directionGenerale->id]);

        $ancien = Employe::factory()->create(['prenom' => 'Abdi', 'nom' => 'Ancien', 'departement_id' => $this->directionGenerale->id]);
        $nouveau = Employe::factory()->create(['prenom' => 'Hawa', 'nom' => 'Nouvelle', 'departement_id' => $this->directionGenerale->id]);
        $this->directionGenerale->update(['responsable_id' => $ancien->id]);

        $this->compteAncien = User::factory()->create(['employe_id' => $ancien->id]);
        $this->compteNouveau = User::factory()->create(['employe_id' => $nouveau->id]);
    }

    public function test_le_dg_est_le_responsable_de_la_direction_generale(): void
    {
        $this->assertTrue($this->compteAncien->estDg());
        $this->assertFalse($this->compteNouveau->estDg());
        $this->assertFalse($this->admin->estDg());

        $this->actingAs($this->compteAncien)->get('/paie/generation')->assertOk();
        $this->actingAs($this->compteNouveau)->get('/paie/generation')->assertForbidden();
    }

    public function test_changer_le_directeur_dans_departements_transfere_les_droits_du_dg(): void
    {
        Livewire::actingAs($this->admin)
            ->test(DepartementIndex::class)
            ->assertSee('Direction générale')
            ->call('editer', $this->directionGenerale->id)
            ->assertSee('Directeur général')
            ->set('responsable_id', $this->compteNouveau->employe_id)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertFalse($this->compteAncien->fresh()->estDg());
        $this->assertTrue($this->compteNouveau->fresh()->estDg());

        $this->actingAs($this->compteAncien->fresh())->get('/paie/generation')->assertForbidden();
        $this->actingAs($this->compteNouveau->fresh())->get('/paie/generation')->assertOk();
    }

    public function test_l_administrateur_a_tous_les_droits_et_le_dg_n_administre_pas(): void
    {
        // L'administrateur possède tous les droits de l'application, paie comprise.
        $this->actingAs($this->admin)->get('/paie/generation')->assertOk();
        $this->actingAs($this->admin)->get('/paie/historique')->assertOk();
        $this->actingAs($this->admin)->get('/parametres')->assertOk();
        $this->actingAs($this->admin)->get('/comptes')->assertOk();

        // Un ancien directeur général sans droits attribués n'administre rien.
        $this->actingAs($this->compteAncien)->get('/parametres')->assertForbidden();
        $this->actingAs($this->compteAncien)->get('/comptes')->assertForbidden();
    }

    public function test_sans_direction_generale_designee_personne_n_est_dg(): void
    {
        ParametresApplication::courants()->update(['direction_generale_id' => null]);

        $this->assertFalse($this->compteAncien->fresh()->estDg());
    }

    public function test_la_direction_generale_choisie_dans_les_parametres_doit_etre_une_direction(): void
    {
        $service = Departement::factory()->service()->create(['nom' => 'Service courrier', 'parent_departement_id' => $this->directionGenerale->id]);
        $autreDirection = Departement::factory()->create(['nom' => 'Direction financière']);

        Livewire::actingAs($this->admin)
            ->test(ParametresIndex::class)
            ->set('direction_generale_id', $service->id)
            ->call('enregistrer')
            ->assertHasErrors(['direction_generale_id']);

        Livewire::actingAs($this->admin)
            ->test(ParametresIndex::class)
            ->set('direction_generale_id', $autreDirection->id)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame($autreDirection->id, ParametresApplication::courants()->direction_generale_id);
        $this->assertFalse($this->compteAncien->fresh()->estDg());
    }
}
