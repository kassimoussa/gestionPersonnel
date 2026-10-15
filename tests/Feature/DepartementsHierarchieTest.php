<?php

namespace Tests\Feature;

use App\Enums\TypeDepartement;
use App\Livewire\Departement\Index;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DepartementsHierarchieTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Departement $direction;

    private Departement $finance;

    private Departement $comptabilite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->creerCompteAdmin();

        // Direction (direction) > Finance (département) > Comptabilité (service)
        $this->direction = Departement::factory()->create(['nom' => 'Direction']);
        $this->finance = Departement::factory()->departement()->create(['nom' => 'Finance', 'parent_departement_id' => $this->direction->id]);
        $this->comptabilite = Departement::factory()->service()->create(['nom' => 'Comptabilité', 'parent_departement_id' => $this->finance->id]);
    }

    public function test_les_quatre_schemas_d_imbrication_sont_acceptes(): void
    {
        $commerciale = $this->creer('Direction commerciale', 'direction', null);

        // Direction → Direction : le directeur général chapeaute les autres directeurs.
        $filiale = $this->creer('Direction des opérations', 'direction', $commerciale->id);

        // Direction → Division → Service
        $ventes = $this->creer('Division ventes', 'division', $commerciale->id);
        $this->creer('Service export', 'service', $ventes->id);

        // Direction → Département → Service
        $marketing = $this->creer('Département marketing', 'departement', $commerciale->id);
        $this->creer('Service événements', 'service', $marketing->id);

        // Direction → Service
        $this->creer('Service accueil', 'service', $commerciale->id);

        $this->assertSame(TypeDepartement::Division, $ventes->type);
        $this->assertSame(TypeDepartement::Direction, $filiale->type);
        $this->assertSame($commerciale->id, $filiale->parent_departement_id);
        $this->assertNull($commerciale->parent_departement_id);
        $this->assertSame($commerciale->id, Departement::where('nom', 'Service accueil')->sole()->parent_departement_id);
        $this->assertSame($marketing->id, Departement::where('nom', 'Service événements')->sole()->parent_departement_id);
    }

    public function test_les_imbrications_invalides_sont_rejetees_une_par_une(): void
    {
        $division = Departement::factory()->division()->create(['nom' => 'Division', 'parent_departement_id' => $this->direction->id]);
        $nombreInitial = Departement::count();

        $cas = [
            'direction sous un service' => ['direction', $this->comptabilite->id],
            'direction sous un département' => ['direction', $this->finance->id],
            'division sans parent' => ['division', null],
            'division sous un service' => ['division', $this->comptabilite->id],
            'division sous une division' => ['division', $division->id],
            'division sous un département' => ['division', $this->finance->id],
            'département sans parent' => ['departement', null],
            'département sous une division' => ['departement', $division->id],
            'département sous un département' => ['departement', $this->finance->id],
            'service sans parent' => ['service', null],
            'service sous un service' => ['service', $this->comptabilite->id],
        ];

        foreach ($cas as $libelle => [$type, $parentId]) {
            Livewire::actingAs($this->admin)
                ->test(Index::class)
                ->call('nouveau')
                ->set('nom', 'Unité '.$libelle)
                ->set('type', $type)
                ->set('parent_departement_id', $parentId)
                ->call('enregistrer')
                ->assertHasErrors(['parent_departement_id']);
        }

        $this->assertSame($nombreInitial, Departement::count());
    }

    public function test_un_service_ne_peut_jamais_avoir_de_sous_unites(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('editer', $this->finance->id)
            ->set('type', 'service')
            ->call('enregistrer')
            ->assertHasErrors(['type'])
            ->assertSee('toujours une feuille');

        $this->assertSame(TypeDepartement::Departement, $this->finance->fresh()->type);
    }

    public function test_changer_le_type_d_une_unite_est_refuse_si_ses_sous_unites_deviennent_invalides(): void
    {
        $autreDirection = Departement::factory()->create(['nom' => 'Autre direction']);

        // Direction → Division : Finance (département) ne peut plus être rattachée à une division.
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('editer', $this->direction->id)
            ->set('type', 'division')
            ->set('parent_departement_id', $autreDirection->id)
            ->call('enregistrer')
            ->assertHasErrors(['type'])
            ->assertSee('Impossible de passer');

        $this->assertSame(TypeDepartement::Direction, $this->direction->fresh()->type);
        $this->assertNull($this->direction->fresh()->parent_departement_id);
    }

    public function test_le_type_est_obligatoire(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('nouveau')
            ->set('nom', 'Sans type')
            ->call('enregistrer')
            ->assertHasErrors(['type']);

        $this->assertSame(0, Departement::where('nom', 'Sans type')->count());
    }

    public function test_les_parents_proposes_dependent_du_type_choisi(): void
    {
        $composant = Livewire::actingAs($this->admin)->test(Index::class)->call('nouveau');

        $composant->set('type', 'division')
            ->assertViewHas('parentsPossibles', fn ($parents) => $parents->pluck('nom')->all() === ['Direction']);

        $composant->set('type', 'service')
            ->assertViewHas('parentsPossibles', fn ($parents) => $parents->pluck('nom')->all() === ['Direction', 'Finance']);

        // Une direction se rattache à une autre direction, ou à rien du tout.
        $composant->set('type', 'direction')
            ->assertViewHas('parentsPossibles', fn ($parents) => $parents->pluck('nom')->all() === ['Direction']);

        // Un parent sélectionné qui devient invalide est désélectionné.
        $composant->set('type', 'service')
            ->set('parent_departement_id', $this->finance->id)
            ->set('type', 'division')
            ->assertSet('parent_departement_id', null);
    }

    public function test_un_departement_ne_peut_pas_etre_son_propre_parent(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('editer', $this->finance->id)
            ->set('parent_departement_id', $this->finance->id)
            ->call('enregistrer')
            ->assertHasErrors(['parent_departement_id'])
            ->assertSee('ne peut pas être son propre parent');

        $this->assertSame($this->direction->id, $this->finance->fresh()->parent_departement_id);
    }

    public function test_rattacher_un_departement_a_l_un_de_ses_descendants_est_refuse(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('editer', $this->direction->id)
            ->set('parent_departement_id', $this->comptabilite->id)
            ->call('enregistrer')
            ->assertHasErrors(['parent_departement_id'])
            ->assertSee('créerait un cycle');

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('editer', $this->finance->id)
            ->set('parent_departement_id', $this->comptabilite->id)
            ->call('enregistrer')
            ->assertHasErrors(['parent_departement_id']);

        $this->assertNull($this->direction->fresh()->parent_departement_id);
        $this->assertSame($this->direction->id, $this->finance->fresh()->parent_departement_id);
    }

    public function test_un_rattachement_valide_est_enregistre(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('editer', $this->comptabilite->id)
            ->set('parent_departement_id', $this->direction->id)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame($this->direction->id, $this->comptabilite->fresh()->parent_departement_id);

        $tresorerie = $this->creer('Trésorerie', 'service', $this->finance->id);
        $this->assertSame($this->finance->id, $tresorerie->parent_departement_id);
    }

    public function test_la_liste_des_parents_possibles_exclut_le_departement_et_ses_descendants(): void
    {
        $composant = Livewire::actingAs($this->admin)->test(Index::class);

        $composant->call('editer', $this->direction->id)
            ->assertViewHas('parentsPossibles', fn ($parents) => $parents->isEmpty());

        $composant->call('editer', $this->comptabilite->id)
            ->assertViewHas('parentsPossibles', fn ($parents) => $parents->pluck('nom')->all() === ['Direction', 'Finance']);
    }

    public function test_seul_l_administrateur_modifie_la_structure(): void
    {
        $manager = Employe::factory()->create();
        $this->finance->update(['responsable_id' => $manager->id]);

        // Un simple employé, un manager et le directeur général consultent sans modifier :
        // changer un responsable change les droits de manager ou de DG.
        foreach ([User::factory()->create(), User::factory()->create(['employe_id' => $manager->id]), $this->creerCompteDg()] as $utilisateur) {
            Livewire::actingAs($utilisateur)
                ->test(Index::class)
                ->assertSee('Comptabilité')
                ->assertDontSee('Ajouter un département')
                ->assertSee("Seul l'administrateur peut modifier la structure des départements.", false)
                ->assertDontSeeHtml("editer({$this->comptabilite->id})")
                ->call('editer', $this->comptabilite->id)
                ->assertForbidden();

            Livewire::actingAs($utilisateur)->test(Index::class)->call('nouveau')->assertForbidden();

            Livewire::actingAs($utilisateur)
                ->test(Index::class)
                ->set('editId', $this->comptabilite->id)
                ->set('nom', 'Compta')
                ->set('type', 'service')
                ->set('responsable_id', $utilisateur->employe_id)
                ->set('parent_departement_id', $this->finance->id)
                ->call('enregistrer')
                ->assertForbidden();

            Livewire::actingAs($utilisateur)->test(Index::class)->call('supprimer', $this->comptabilite->id)->assertForbidden();
        }

        $comptabilite = $this->comptabilite->fresh();
        $this->assertSame('Comptabilité', $comptabilite->nom);
        $this->assertNull($comptabilite->responsable_id);
        $this->assertSame($this->finance->id, $comptabilite->parent_departement_id);
    }

    public function test_valider_imbrication_leve_une_exception_de_validation(): void
    {
        $this->expectException(ValidationException::class);

        (new Departement)->validerImbrication($this->comptabilite, 'direction');
    }

    public function test_le_modele_detecte_les_ancetres_et_les_descendants(): void
    {
        $this->assertTrue($this->direction->estAncetreDe($this->comptabilite));
        $this->assertTrue($this->finance->estAncetreDe($this->comptabilite));
        $this->assertFalse($this->comptabilite->estAncetreDe($this->direction));
        $this->assertFalse($this->direction->estAncetreDe($this->direction));

        $descendants = $this->direction->idsDescendants();
        sort($descendants);
        $this->assertSame([$this->finance->id, $this->comptabilite->id], $descendants);
        $this->assertSame([], $this->comptabilite->idsDescendants());

        // Cycle forcé en base (hors validation) : la détection doit se terminer.
        Departement::whereKey($this->direction->id)->update(['parent_departement_id' => $this->comptabilite->id]);
        $this->assertTrue($this->finance->fresh()->estAncetreDe($this->direction->fresh()));
        $this->assertIsArray($this->finance->fresh()->idsDescendants());
    }

    public function test_supprimer_un_parent_rend_ses_sous_departements_racines(): void
    {
        $this->direction->delete();

        $this->assertNull($this->finance->fresh()->parent_departement_id);
        $this->assertSame($this->finance->id, $this->comptabilite->fresh()->parent_departement_id);
    }

    private function creer(string $nom, string $type, ?int $parentId): Departement
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('nouveau')
            ->set('nom', $nom)
            ->set('type', $type)
            ->set('parent_departement_id', $parentId)
            ->call('enregistrer')
            ->assertHasNoErrors();

        return Departement::where('nom', $nom)->sole();
    }
}
