<?php

namespace Tests\Feature;

use App\Enums\Droit;
use App\Enums\ProfilDroits;
use App\Livewire\Comptes\Index as ComptesIndex;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'administrateur a tous les droits et il est le seul à les attribuer,
 * compte par compte, depuis « Comptes utilisateurs ».
 */
class DroitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_administrateur_a_tous_les_droits(): void
    {
        $admin = $this->creerCompteAdmin();

        foreach (Droit::cases() as $droit) {
            $this->assertTrue($admin->aLeDroit($droit), "L'administrateur devrait avoir le droit {$droit->value}.");
        }

        foreach (['/employes', '/contrats', '/conges/personnel', '/presence/rapport', '/presence/saisie', '/presence/import', '/paie/generation', '/paie/historique', '/comptes', '/parametres'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_un_compte_sans_droit_n_accede_a_rien(): void
    {
        $compte = User::factory()->create(['employe_id' => Employe::factory()->create()->id]);

        foreach (Droit::cases() as $droit) {
            $this->assertFalse($compte->aLeDroit($droit));
        }

        foreach (['/employes', '/contrats', '/conges/personnel', '/presence/rapport', '/presence/saisie', '/presence/import', '/paie/generation', '/paie/historique', '/comptes', '/parametres'] as $url) {
            $this->actingAs($compte)->get($url)->assertForbidden();
        }

        // Il ne reste que les écrans communs : tableau de bord, organigramme, annuaire.
        $this->actingAs($compte)->get('/dashboard')->assertOk();
        $this->actingAs($compte)->get('/organigramme')->assertOk();
        $this->actingAs($compte)->get('/departements')->assertOk();
    }

    public function test_l_administrateur_attribue_des_droits_a_un_compte(): void
    {
        $compte = User::factory()->create();

        Livewire::actingAs($this->creerCompteAdmin())
            ->test(ComptesIndex::class)
            ->call('editer', $compte->id)
            ->assertSee('Consulter les dossiers du personnel')
            ->set('droits', [Droit::PersonnelConsulter->value, Droit::PresenceSaisir->value])
            ->call('enregistrer')
            ->assertHasNoErrors();

        $compte->refresh();
        $this->assertTrue($compte->aLeDroit(Droit::PersonnelConsulter));
        $this->assertTrue($compte->aLeDroit(Droit::PresenceSaisir));
        $this->assertFalse($compte->aLeDroit(Droit::ContratsGerer));

        $this->actingAs($compte)->get('/employes')->assertOk();
        $this->actingAs($compte)->get('/presence/saisie')->assertOk();
        $this->actingAs($compte)->get('/contrats')->assertForbidden();
    }

    public function test_un_profil_coche_les_droits_correspondants(): void
    {
        $compte = User::factory()->create();

        Livewire::actingAs($this->creerCompteAdmin())
            ->test(ComptesIndex::class)
            ->call('editer', $compte->id)
            ->call('appliquerProfil', ProfilDroits::Rh->value)
            ->assertSet('droits', ProfilDroits::Rh->valeursDroits())
            ->call('enregistrer')
            ->assertHasNoErrors();

        $compte->refresh();
        $this->assertTrue($compte->aLeDroit(Droit::PaiePreparer));
        // Le service du personnel prépare la paie mais ne la valide pas.
        $this->assertFalse($compte->aLeDroit(Droit::PaieValider));
    }

    public function test_retirer_un_droit_ferme_l_acces_correspondant(): void
    {
        $compte = $this->creerCompteAvecDroits(Droit::PersonnelConsulter, Droit::ContratsGerer);

        $this->actingAs($compte)->get('/contrats')->assertOk();

        Livewire::actingAs($this->creerCompteAdmin())
            ->test(ComptesIndex::class)
            ->call('editer', $compte->id)
            ->assertSet('droits', [Droit::PersonnelConsulter->value, Droit::ContratsGerer->value])
            ->set('droits', [Droit::PersonnelConsulter->value])
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->actingAs($compte->fresh())->get('/contrats')->assertForbidden();
        $this->actingAs($compte->fresh())->get('/employes')->assertOk();
    }

    public function test_seul_un_administrateur_attribue_les_droits(): void
    {
        $gestionnaire = $this->creerCompteAvecDroits(Droit::ComptesGerer);
        $compte = $this->creerCompteAvecDroits(Droit::PersonnelConsulter);

        // Il gère bien les comptes...
        $this->actingAs($gestionnaire)->get('/comptes')->assertOk();

        Livewire::actingAs($gestionnaire)
            ->test(ComptesIndex::class)
            ->call('nouveau')
            ->assertSee('Seul un administrateur attribue les droits')
            ->assertDontSee('Consulter les dossiers du personnel')
            ->call('appliquerProfil', ProfilDroits::Rh->value)
            ->assertForbidden();

        // ... mais une tentative de modifier les droits d'un compte reste sans effet.
        Livewire::actingAs($gestionnaire)
            ->test(ComptesIndex::class)
            ->call('editer', $compte->id)
            ->set('admin', true)
            ->set('droits', Droit::valeurs())
            ->call('enregistrer')
            ->assertHasNoErrors();

        $compte->refresh();
        $this->assertFalse($compte->estAdmin());
        $this->assertSame([Droit::PersonnelConsulter->value], $compte->droitsAttribues());
    }

    public function test_les_droits_s_ajoutent_aux_positions_de_l_organigramme(): void
    {
        // Le directeur général tient ses droits de l'organigramme, sans aucune case cochée.
        $dg = $this->creerCompteDg();

        $this->assertSame([], $dg->droitsAttribues());
        $this->actingAs($dg)->get('/paie/generation')->assertOk();
        $this->actingAs($dg)->get('/employes')->assertOk();

        // Il n'administre pas l'application pour autant.
        $this->actingAs($dg)->get('/comptes')->assertForbidden();
        $this->actingAs($dg)->get('/parametres')->assertForbidden();

        // Un manager voit son équipe sans droit attribué, et rien de plus.
        $manager = Employe::factory()->create();
        Departement::factory()->create(['responsable_id' => $manager->id]);
        $compteManager = User::factory()->create(['employe_id' => $manager->id]);

        $this->actingAs($compteManager)->get('/employes')->assertOk();
        $this->actingAs($compteManager)->get('/contrats')->assertForbidden();
        $this->actingAs($compteManager)->get('/paie/generation')->assertForbidden();
    }

    public function test_le_dernier_administrateur_ne_peut_pas_perdre_son_role(): void
    {
        $admin = $this->creerCompteAdmin();

        Livewire::actingAs($admin)
            ->test(ComptesIndex::class)
            ->call('editer', $admin->id)
            ->set('admin', false)
            ->call('enregistrer')
            ->assertHasErrors(['admin']);

        $this->assertTrue($admin->fresh()->estAdmin());
    }
}
