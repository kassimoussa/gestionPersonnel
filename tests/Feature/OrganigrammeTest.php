<?php

namespace Tests\Feature;

use App\Enums\TypeDepartement;
use App\Livewire\Organigramme\Index;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\User;
use App\Services\Organigramme\NoeudDepartement;
use App\Services\Organigramme\OrganigrammeBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganigrammeTest extends TestCase
{
    use RefreshDatabase;

    private Departement $direction;

    private Departement $finance;

    private Departement $comptabilite;

    private Employe $hodan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-14 10:00:00');

        // Direction générale [direction, resp. Abdi, désignée dans les paramètres]
        $this->creerCompteDg(['prenom' => 'Abdi', 'nom' => 'Directeur', 'poste' => 'Directeur général']);

        // Pôle Direction [direction, resp. Awa]
        //   > Pôle Finance [division, poste vacant] > Comptabilité générale [service, resp. Hodan]
        //   > Pôle RH [département, poste vacant]
        $this->direction = Departement::factory()->create(['nom' => 'Pôle Direction']);
        $awa = Employe::factory()->create(['prenom' => 'Awa', 'nom' => 'Responsable', 'departement_id' => $this->direction->id]);
        $this->direction->update(['responsable_id' => $awa->id]);

        $this->finance = Departement::factory()->division()->create(['nom' => 'Pôle Finance', 'parent_departement_id' => $this->direction->id]);

        $this->comptabilite = Departement::factory()->service()->create(['nom' => 'Comptabilité générale', 'parent_departement_id' => $this->finance->id]);

        Departement::factory()->departement()->create(['nom' => 'Pôle RH', 'parent_departement_id' => $this->direction->id]);
        $this->hodan = Employe::factory()->create(['prenom' => 'Hodan', 'nom' => 'Chef', 'departement_id' => $this->comptabilite->id]);
        $this->comptabilite->update(['responsable_id' => $this->hodan->id]);

        Employe::factory()->create(['prenom' => 'Moussa', 'nom' => 'Employe', 'departement_id' => $this->direction->id]);
        Employe::factory()->inactif()->create(['prenom' => 'Ancien', 'nom' => 'Parti', 'departement_id' => $this->direction->id]);
        Employe::factory()->create(['prenom' => 'Fatouma', 'nom' => 'Comptable', 'departement_id' => $this->comptabilite->id]);
        Employe::factory()->create(['prenom' => 'Samira', 'nom' => 'Sansdept', 'departement_id' => null]);
    }

    public function test_l_arbre_est_construit_sur_plusieurs_niveaux(): void
    {
        $organigramme = app(OrganigrammeBuilder::class)->construire();

        // La Direction générale est une Direction comme les autres, affichée en premier.
        $this->assertCount(2, $organigramme->racines);
        $directionGenerale = $organigramme->racines[0];
        $this->assertSame('Direction générale', $directionGenerale->departement->nom);
        $this->assertTrue($directionGenerale->estDirectionGenerale);
        $this->assertSame(TypeDepartement::Direction, $directionGenerale->type);
        $this->assertSame('Abdi Directeur', $directionGenerale->responsable?->nomComplet());
        $this->assertTrue($directionGenerale->employes->isEmpty());
        $this->assertSame(1, $directionGenerale->effectifTotal());

        $direction = $organigramme->racines[1];
        $this->assertFalse($direction->estDirectionGenerale);
        $this->assertSame('Pôle Direction', $direction->departement->nom);
        $this->assertSame('Awa Responsable', $direction->responsable?->nomComplet());
        $this->assertSame(['Moussa Employe'], $direction->employes->map(fn (Employe $e) => $e->nomComplet())->all());

        $this->assertSame(TypeDepartement::Direction, $direction->type);

        $this->assertCount(2, $direction->enfants);
        $finance = $direction->enfants[0];
        $this->assertSame('Pôle Finance', $finance->departement->nom);
        $this->assertSame(TypeDepartement::Division, $finance->type);
        $this->assertTrue($finance->posteVacant());
        $this->assertTrue($finance->employes->isEmpty());

        $rh = $direction->enfants[1];
        $this->assertSame('Pôle RH', $rh->departement->nom);
        $this->assertSame(TypeDepartement::Departement, $rh->type);
        $this->assertTrue($rh->posteVacant());
        $this->assertSame(0, $rh->effectifTotal());

        $this->assertCount(1, $finance->enfants);
        $comptabilite = $finance->enfants[0];
        $this->assertSame(TypeDepartement::Service, $comptabilite->type);
        $this->assertSame('Hodan Chef', $comptabilite->responsable?->nomComplet());
        $this->assertSame(['Fatouma Comptable'], $comptabilite->employes->map(fn (Employe $e) => $e->nomComplet())->all());
        $this->assertSame([], $comptabilite->enfants);

        $this->assertSame(4, $direction->effectifTotal());
        $this->assertSame(['Samira Sansdept'], $organigramme->employesSansDepartement->map(fn (Employe $e) => $e->nomComplet())->all());
        $this->assertSame(6, $organigramme->effectifTotal());
    }

    public function test_un_responsable_inactif_est_affiche_comme_poste_vacant(): void
    {
        $this->hodan->update(['actif' => false]);

        $comptabilite = app(OrganigrammeBuilder::class)->construire()->racines[1]->enfants[0]->enfants[0];

        $this->assertTrue($comptabilite->posteVacant());
        $this->assertSame(['Fatouma Comptable'], $comptabilite->employes->map(fn (Employe $e) => $e->nomComplet())->all());
    }

    public function test_un_cycle_present_en_base_ne_bloque_pas_la_construction(): void
    {
        Departement::whereKey($this->direction->id)->update(['parent_departement_id' => $this->comptabilite->id]);

        $organigramme = app(OrganigrammeBuilder::class)->construire();

        $noms = [];
        $collecter = function (array $noeuds) use (&$noms, &$collecter): void {
            foreach ($noeuds as $noeud) {
                /** @var NoeudDepartement $noeud */
                $noms[] = $noeud->departement->nom;
                $collecter($noeud->enfants);
            }
        };
        $collecter($organigramme->racines);

        sort($noms);
        $this->assertSame(['Comptabilité générale', 'Direction générale', 'Pôle Direction', 'Pôle Finance', 'Pôle RH'], $noms);
    }

    public function test_la_page_est_visible_par_tout_utilisateur_connecte_et_masque_les_inactifs(): void
    {
        $this->get('/organigramme')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get('/organigramme')
            ->assertOk()
            ->assertSeeLivewire(Index::class)
            ->assertSee('Abdi Directeur')
            ->assertSee('Pôle Direction')
            ->assertSee('Awa Responsable')
            ->assertSee('Moussa Employe')
            ->assertSee('Pôle Finance')
            ->assertSee('Poste vacant')
            ->assertSee('Comptabilité générale')
            ->assertSee('Fatouma Comptable')
            ->assertSee('Samira Sansdept')
            ->assertSee('Exporter en PDF')
            ->assertDontSee('Ancien Parti')
            // Badges de type (libellé + teinte propre à chaque type)
            ->assertSee('Pôle RH')
            ->assertSee('Division')
            ->assertSee('Département')
            ->assertSee('Service')
            ->assertSee('badge-direction')
            ->assertSee('badge-division')
            ->assertSee('badge-departement')
            ->assertSee('badge-service');
    }

    public function test_la_vue_pdf_affiche_le_type_de_chaque_unite(): void
    {
        $html = view('organigramme.pdf', [
            'organigramme' => app(OrganigrammeBuilder::class)->construire(),
            'genereLe' => now(),
        ])->render();

        foreach (['DIRECTION', 'DIVISION', 'DÉPARTEMENT', 'SERVICE'] as $badge) {
            $this->assertStringContainsString($badge, $html);
        }

        foreach (TypeDepartement::cases() as $type) {
            $this->assertStringContainsString($type->couleursPdf()['texte'], $html);
        }
    }

    public function test_l_export_pdf_est_telechargeable(): void
    {
        $this->get('/organigramme/pdf')->assertRedirect(route('login'));

        $reponse = $this->actingAs(User::factory()->create())->get('/organigramme/pdf');

        $reponse->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('organigramme-2026-09-14.pdf');

        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }
}
