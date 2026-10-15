<?php

namespace Tests\Feature;

use App\Livewire\Parametres\Index;
use App\Models\Employe;
use App\Models\FichePaie;
use App\Models\ParametresApplication;
use App\Models\Pointage;
use App\Services\CalculJoursOuvres;
use App\Services\Paie\PaieCalculateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PersonnalisationTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_valeurs_par_defaut_viennent_de_la_configuration(): void
    {
        $parametres = ParametresApplication::courants();

        $this->assertSame([5, 6], $parametres->joursWeekend());
        $this->assertSame('08:00', $parametres->heureDebutTravail());
        $this->assertSame(15, $parametres->toleranceRetardMinutes());
        $this->assertSame(2.5, $parametres->joursCongeParMois());
        $this->assertFalse($parametres->inscription_ouverte);
    }

    public function test_le_temps_de_travail_personnalise_s_applique_aux_calculs(): void
    {
        Livewire::actingAs($this->creerCompteAdmin())
            ->test(Index::class)
            ->set('jours_weekend', ['6', '7'])
            ->set('heure_debut_travail', '07:30')
            ->set('tolerance_retard_minutes', '5')
            ->set('jours_conge_par_mois', '2')
            ->call('enregistrer')
            ->assertHasNoErrors();

        // Du dimanche 13 au samedi 19 septembre 2026 : samedi et dimanche ne sont plus ouvrés, le vendredi l'est.
        $this->assertSame(5, app(CalculJoursOuvres::class)->entre('2026-09-13', '2026-09-19'));

        $this->assertTrue(Pointage::factory()->make(['heure_arrivee' => '07:36:00'])->estEnRetard());
        $this->assertFalse(Pointage::factory()->make(['heure_arrivee' => '07:35:00'])->estEnRetard());

        $employe = Employe::factory()->create();
        $this->travelTo('2026-09-16 10:00:00');
        $this->artisan('conges:accumuler-soldes')->assertSuccessful();
        $this->assertEquals(2, $employe->joursCongeAcquis(2026));
    }

    public function test_il_doit_rester_au_moins_un_jour_ouvre(): void
    {
        Livewire::actingAs($this->creerCompteAdmin())
            ->test(Index::class)
            ->set('jours_weekend', ['1', '2', '3', '4', '5', '6', '7'])
            ->call('enregistrer')
            ->assertHasErrors(['jours_weekend']);

        $this->assertSame([5, 6], ParametresApplication::courants()->joursWeekend());
    }

    public function test_l_ecran_de_connexion_et_les_documents_sont_personnalises(): void
    {
        Livewire::actingAs($this->creerCompteAdmin())
            ->test(Index::class)
            ->set('couleur_accent_secondaire', '#0E7490')
            ->set('titre_connexion', 'Bienvenue sur le portail RH')
            ->set('texte_connexion', 'Réservé au personnel.')
            ->set('signataire_nom', 'Abdi Directeur')
            ->set('signataire_fonction', 'Directeur général')
            ->set('pied_page_documents', 'NIF 000123 · Djibouti')
            ->call('enregistrer')
            ->assertHasNoErrors();

        auth()->logout();

        $this->get('/')
            ->assertOk()
            ->assertSee('Bienvenue sur le portail RH')
            ->assertSee('Réservé au personnel.')
            ->assertSee('--color-accent-2: #0E7490;', false);

        $fiche = FichePaie::factory()->for(Employe::factory()->create(['numero_cnss' => 'CNSS-777', 'matricule' => 'ANF-7']))->validee()->create();
        $calculateur = app(PaieCalculateur::class);

        $bulletin = view('paie.bulletin', [
            'fiche' => $fiche->load(['employe.departement', 'valideePar']),
            'parametres' => ParametresApplication::courants(),
            'detailCnss' => $calculateur->detailCnss((float) $fiche->brut),
            'detailIrpp' => $calculateur->detailIrppPourFiche($fiche),
            'genereLe' => now(),
        ])->render();

        foreach (['Abdi Directeur', 'Directeur général', 'NIF 000123 · Djibouti', 'CNSS-777', 'ANF-7'] as $texte) {
            $this->assertStringContainsString($texte, $bulletin);
        }
    }
}
