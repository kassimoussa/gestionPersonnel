<?php

namespace Tests\Feature;

use App\Livewire\TableauDeBord\Index;
use App\Models\Conge;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\FichePaie;
use App\Models\Pointage;
use App\Models\SoldeCongeAnnuel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TableauDeBordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Lundi 14 septembre 2026, jour ouvré.
        $this->travelTo('2026-09-14 10:00:00');
    }

    public function test_le_tableau_de_bord_exige_une_connexion(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_un_compte_sans_droit_ne_voit_aucun_indicateur_de_service(): void
    {
        $employe = Employe::factory()->create(['prenom' => 'Awa']);
        $user = User::factory()->create(['employe_id' => $employe->id]);
        SoldeCongeAnnuel::factory()->for($employe)->create(['annee' => 2026, 'jours_acquis' => 12.5]);
        Conge::factory()->for($employe)->create(['motif' => null]);

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSeeLivewire(Index::class);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertSee('Bonjour, Awa')
            // Plus de rubrique personnelle : tout passe par le service du personnel.
            ->assertDontSee('Mon solde de congé annuel')
            ->assertDontSee('Mes demandes')
            ->assertViewHas('nombreATraiter', 0)
            ->assertDontSee("Présents aujourd'hui", false)
            ->assertDontSee('Masse salariale')
            ->assertDontSee('Génération de la paie');
    }

    public function test_un_manager_voit_la_presence_et_les_demandes_de_son_equipe(): void
    {
        $manager = Employe::factory()->create();
        $departement = Departement::factory()->create(['responsable_id' => $manager->id]);
        $membre = Employe::factory()->create(['prenom' => 'Moussa', 'nom' => 'Equipe', 'departement_id' => $departement->id]);
        Employe::factory()->create(['departement_id' => $departement->id]);
        Pointage::factory()->for($membre)->create(['date' => '2026-09-14']);
        Conge::factory()->for($membre)->create();
        $compte = User::factory()->create(['employe_id' => $manager->id]);

        Livewire::actingAs($compte)
            ->test(Index::class)
            ->assertSee("Présents aujourd'hui", false)
            ->assertViewHas('presence', fn ($presence) => $presence['presents'] === 1 && $presence['effectif'] === 2 && $presence['jour_ouvre'])
            // Le circuit des congés est tenu par le service du personnel : un manager n'y figure pas.
            ->assertViewHas('nombreATraiter', 0)
            ->assertDontSee('Masse salariale');

        // Le service du personnel, lui, voit les dossiers en cours.
        Livewire::actingAs($this->creerCompteRh())
            ->test(Index::class)
            ->assertViewHas('nombreATraiter', 1)
            ->assertSee('Moussa Equipe');
    }

    public function test_la_dg_voit_la_masse_salariale_du_mois(): void
    {
        $dg = $this->creerCompteDg();
        FichePaie::factory()->for(Employe::factory()->create())->validee()->create(['annee' => 2026, 'mois' => 9]);
        FichePaie::factory()->for(Employe::factory()->create())->create(['annee' => 2026, 'mois' => 9]);
        FichePaie::factory()->for(Employe::factory()->create())->validee()->create(['annee' => 2026, 'mois' => 8]);

        Livewire::actingAs($dg)
            ->test(Index::class)
            ->assertSee('Masse salariale · Septembre 2026')
            ->assertSee('200 000 FD')
            ->assertSee('2 fiche(s), dont 1 brouillon(s)');
    }
}
