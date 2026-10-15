<?php

namespace Tests\Feature;

use App\Livewire\Comptes\Index;
use App\Models\Employe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ComptesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->creerCompteAdmin();
    }

    public function test_l_ecran_des_comptes_est_reserve_a_l_administrateur(): void
    {
        $this->get('/comptes')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/comptes')->assertForbidden();
        $this->actingAs($this->creerCompteDg())->get('/comptes')->assertForbidden();
        $this->actingAs($this->admin)->get('/comptes')->assertOk()->assertSeeLivewire(Index::class);
    }

    public function test_l_administrateur_cree_un_compte_lie_a_une_fiche_employe(): void
    {
        $employe = Employe::factory()->create(['prenom' => 'Fatouma', 'nom' => 'Ali', 'email' => 'fatouma@anefip.dj']);

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('nouveau')
            ->assertSeeHtml('role="dialog"')
            // La fiche se choisit par recherche : nom, matricule ou numéro.
            ->set('rechercheEmploye', 'Fatouma')
            ->assertSee('Fatouma Ali')
            ->call('choisirEmploye', $employe->id)
            ->assertSet('employe_id', $employe->id)
            ->assertSet('name', 'Fatouma Ali')
            ->assertSet('email', 'fatouma@anefip.dj')
            ->set('password', 'MotDePasse-2026')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSee('Fatouma Ali');

        $compte = User::where('email', 'fatouma@anefip.dj')->sole();
        $this->assertSame($employe->id, $compte->employe_id);
        $this->assertTrue(Hash::check('MotDePasse-2026', $compte->password));
        $this->assertNotNull($compte->email_verified_at);
        $this->assertFalse($compte->estAdmin());
    }

    public function test_une_fiche_employe_ne_peut_etre_liee_qu_a_un_seul_compte(): void
    {
        $employe = Employe::factory()->create();
        User::factory()->create(['employe_id' => $employe->id]);

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('nouveau')
            ->set('name', 'Doublon')
            ->set('email', 'doublon@anefip.dj')
            ->set('password', 'MotDePasse-2026')
            ->set('employe_id', $employe->id)
            ->call('enregistrer')
            ->assertHasErrors(['employe_id']);
    }

    public function test_le_role_administrateur_se_donne_et_se_retire_sauf_pour_le_dernier(): void
    {
        $compte = User::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('editer', $compte->id)
            ->set('admin', true)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertTrue($compte->fresh()->estAdmin());

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('editer', $compte->id)
            ->set('admin', false)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertFalse($compte->fresh()->estAdmin());

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('editer', $this->admin->id)
            ->set('admin', false)
            ->call('enregistrer')
            ->assertHasErrors(['admin']);

        $this->assertTrue($this->admin->fresh()->estAdmin());
    }

    public function test_le_mot_de_passe_est_conserve_s_il_est_laisse_vide(): void
    {
        $compte = User::factory()->create(['password' => Hash::make('Ancien-2026')]);

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('editer', $compte->id)
            ->set('name', 'Nouveau nom')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame('Nouveau nom', $compte->fresh()->name);
        $this->assertTrue(Hash::check('Ancien-2026', $compte->fresh()->password));
    }

    public function test_suppression_d_un_compte_sans_toucher_a_la_fiche_et_protection_de_son_propre_compte(): void
    {
        $employe = Employe::factory()->create();
        $compte = User::factory()->create(['employe_id' => $employe->id]);

        Livewire::actingAs($this->admin)->test(Index::class)->call('supprimer', $compte->id)->assertHasNoErrors();

        $this->assertModelMissing($compte);
        $this->assertModelExists($employe);

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('supprimer', $this->admin->id)
            ->assertHasErrors(['suppression']);

        $this->assertModelExists($this->admin);
    }

    public function test_les_roles_deduits_de_l_organigramme_sont_affiches(): void
    {
        $dg = $this->creerCompteDg(['prenom' => 'Abdi', 'nom' => 'Directeur']);

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->assertSee('Administrateur')
            ->assertSee('Directeur général')
            ->assertSee('Abdi Directeur');

        $this->assertTrue($dg->estDg());
    }
}
