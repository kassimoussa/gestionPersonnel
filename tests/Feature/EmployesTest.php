<?php

namespace Tests\Feature;

use App\Livewire\Employe\Index;
use App\Models\Conge;
use App\Models\Employe;
use App\Models\FichePaie;
use App\Models\Pointage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployesTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_employe_sans_fiche_de_paie_peut_etre_supprime_avec_ses_conges_et_pointages(): void
    {
        $employe = Employe::factory()->create();
        $conge = Conge::factory()->for($employe)->create();
        $pointage = Pointage::factory()->for($employe)->create();

        Livewire::actingAs($this->creerCompteRh())
            ->test(Index::class)
            ->assertSeeHtml("supprimer({$employe->id})")
            ->call('supprimer', $employe->id)
            ->assertHasNoErrors();

        $this->assertModelMissing($employe);
        $this->assertModelMissing($conge);
        $this->assertModelMissing($pointage);
    }

    public function test_un_employe_avec_une_fiche_de_paie_ne_peut_pas_etre_supprime_depuis_l_interface(): void
    {
        $avecBrouillon = Employe::factory()->create(['prenom' => 'Awa', 'nom' => 'Brouillon']);
        FichePaie::factory()->for($avecBrouillon)->create();

        $avecValidee = Employe::factory()->create(['prenom' => 'Hodan', 'nom' => 'Validee']);
        FichePaie::factory()->for($avecValidee)->validee()->create();

        $composant = Livewire::actingAs($this->creerCompteRh())
            ->test(Index::class)
            ->assertDontSeeHtml("supprimer({$avecBrouillon->id})")
            ->assertDontSeeHtml("supprimer({$avecValidee->id})");

        // Même en appelant l'action directement, la suppression est refusée avec un message clair.
        $composant->call('supprimer', $avecBrouillon->id)
            ->assertHasErrors(['suppression'])
            ->assertSee('Impossible de supprimer Awa Brouillon : cet employé a des fiches de paie. Désactivez-le plutôt');

        $composant->call('supprimer', $avecValidee->id)
            ->assertHasErrors(['suppression']);

        $this->assertModelExists($avecBrouillon);
        $this->assertModelExists($avecValidee);
        $this->assertSame(2, FichePaie::count());
    }

    public function test_la_contrainte_sql_refuse_la_suppression_meme_en_contournant_l_interface(): void
    {
        $employe = Employe::factory()->create();
        $fiche = FichePaie::factory()->for($employe)->create();

        try {
            $employe->delete();
            $this->fail('La suppression aurait dû être refusée par la clé étrangère.');
        } catch (QueryException) {
            // Attendu : ON DELETE RESTRICT.
        }

        $this->assertModelExists($employe);
        $this->assertModelExists($fiche);
    }

    public function test_un_employe_se_desactive_et_se_reactive_depuis_la_liste(): void
    {
        $employe = Employe::factory()->create(['prenom' => 'Awa', 'nom' => 'Active']);
        FichePaie::factory()->for($employe)->validee()->create();

        $composant = Livewire::actingAs($this->creerCompteRh())
            ->test(Index::class)
            ->assertDontSeeHtml("supprimer({$employe->id})")
            ->call('basculerActivite', $employe->id)
            ->assertSee('Awa Active a été désactivé.');

        $this->assertFalse($employe->fresh()->actif);

        $composant->call('basculerActivite', $employe->id)->assertSee('Awa Active a été réactivé.');

        $this->assertTrue($employe->fresh()->actif);
    }

    public function test_la_liste_se_filtre_par_statut(): void
    {
        Employe::factory()->create(['prenom' => 'Awa', 'nom' => 'Active']);
        Employe::factory()->inactif()->create(['prenom' => 'Bilan', 'nom' => 'Parti']);

        Livewire::actingAs($this->creerCompteRh())
            ->test(Index::class)
            ->assertSee('Awa Active')
            ->assertSee('Bilan Parti')
            ->set('filtreStatut', 'actifs')
            ->assertSee('Awa Active')
            ->assertDontSee('Bilan Parti')
            ->set('filtreStatut', 'inactifs')
            ->assertDontSee('Awa Active')
            ->assertSee('Bilan Parti');
    }

    public function test_un_employe_avec_fiches_de_paie_peut_toujours_etre_desactive(): void
    {
        $employe = Employe::factory()->create();
        FichePaie::factory()->for($employe)->validee()->create();

        Livewire::actingAs($this->creerCompteRh())
            ->test(Index::class)
            ->call('editer', $employe->id)
            ->set('actif', false)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertFalse($employe->fresh()->actif);
    }

    public function test_le_formulaire_employe_propose_le_matricule_pointeuse(): void
    {
        $this->actingAs($this->creerCompteRh())->get('/employes')
            ->assertOk()
            ->assertSeeLivewire(Index::class);

        Livewire::actingAs($this->creerCompteRh())
            ->test(Index::class)
            ->call('nouveau')
            ->assertSee('Matricule pointeuse')
            ->set('nom', 'Ali')
            ->set('prenom', 'Fatouma')
            ->set('email', 'fatouma@rh.test')
            ->set('poste', 'Comptable')
            ->set('date_embauche', '2026-01-05')
            ->set('matricule_pointeuse', ' A001 ')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame('A001', Employe::sole()->matricule_pointeuse);
    }

    public function test_le_formulaire_s_ouvre_et_se_ferme_dans_une_fenetre_modale(): void
    {
        $employe = Employe::factory()->create(['prenom' => 'Fatouma', 'nom' => 'Ali']);

        Livewire::actingAs($this->creerCompteRh())
            ->test(Index::class)
            ->assertDontSeeHtml('role="dialog"')
            ->call('nouveau')
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSee('Nouvel employé')
            ->call('annuler')
            ->assertDontSeeHtml('role="dialog"')
            ->call('editer', $employe->id)
            ->assertSeeHtml('role="dialog"')
            ->assertSee('Modifier l\'employé')
            ->assertSet('prenom', 'Fatouma');
    }

    public function test_le_matricule_et_l_identifiant_de_la_pointeuse_se_completent_l_un_l_autre(): void
    {
        $composant = Livewire::actingAs($this->creerCompteRh())
            ->test(Index::class)
            ->call('nouveau')
            ->set('nom', 'Bouh Guessod')
            ->set('prenom', 'Ibado')
            ->set('matricule', '1001')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $employe = Employe::sole();
        $this->assertSame('1001', $employe->matricule_pointeuse);

        // Dans l'autre sens : seul l'identifiant de la pointeuse est saisi.
        $composant->call('nouveau')
            ->set('nom', 'Isse Amin')
            ->set('prenom', 'Mohamed')
            ->set('matricule_pointeuse', '1002')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame('1002', Employe::where('prenom', 'Mohamed')->sole()->matricule);
    }

    public function test_le_matricule_pointeuse_est_unique_et_facultatif(): void
    {
        Employe::factory()->create(['matricule_pointeuse' => 'A001']);
        $existant = Employe::factory()->create(['matricule_pointeuse' => null]);

        $composant = Livewire::actingAs($this->creerCompteRh())
            ->test(Index::class)
            ->call('editer', $existant->id)
            ->assertSet('matricule_pointeuse', '');

        $composant->set('matricule_pointeuse', 'A001')->call('enregistrer')
            ->assertHasErrors(['matricule_pointeuse']);

        // Les deux numéros se recopient l'un dans l'autre : laisser la fiche sans
        // identifiant suppose de vider les deux champs.
        $composant->set('matricule_pointeuse', '')->set('matricule', '')->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertNull($existant->fresh()->matricule_pointeuse);
        $this->assertNull($existant->fresh()->matricule);
    }
}
