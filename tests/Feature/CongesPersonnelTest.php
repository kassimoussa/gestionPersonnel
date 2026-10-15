<?php

namespace Tests\Feature;

use App\Enums\StatutConge;
use App\Livewire\Conges\Personnel;
use App\Models\Conge;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\JourFerie;
use App\Models\ParametresApplication;
use App\Models\SoldeCongeAnnuel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Circuit complet du congé, conduit par le service du personnel : formulaire remis,
 * visa du chef, attestation établie, signature du directeur général, congé acté.
 */
class CongesPersonnelTest extends TestCase
{
    use RefreshDatabase;

    private Employe $employe;

    private Employe $chef;

    protected function setUp(): void
    {
        parent::setUp();

        // 2026-09-13 est un dimanche ; le weekend configuré est vendredi-samedi.
        $this->travelTo('2026-09-13 09:00:00');

        $this->chef = Employe::factory()->create(['prenom' => 'Awa', 'nom' => 'Responsable']);
        $departement = Departement::factory()->create(['nom' => 'Direction Emploi', 'responsable_id' => $this->chef->id]);
        $this->employe = Employe::factory()->create([
            'prenom' => 'Ladane', 'nom' => 'Assoweh', 'sexe' => 'F', 'poste' => 'Coordinatrice',
            'departement_id' => $departement->id,
        ]);
        SoldeCongeAnnuel::factory()->for($this->employe)->create(['annee' => 2026, 'jours_acquis' => 30]);
    }

    public function test_un_employe_sans_responsabilite_n_accede_pas_a_la_page(): void
    {
        $this->get('/conges/personnel')->assertRedirect(route('login'));

        $compte = User::factory()->create(['employe_id' => Employe::factory()->create()->id]);
        $this->actingAs($compte)->get('/conges/personnel')->assertForbidden();
    }

    public function test_le_rh_ouvre_la_demande_et_remet_le_formulaire(): void
    {
        $rh = $this->creerCompteRh();

        $this->actingAs($rh)->get('/conges/personnel')->assertOk()->assertSeeLivewire(Personnel::class);

        Livewire::actingAs($rh)
            ->test(Personnel::class)
            ->call('nouveau')
            ->set('employe_id', $this->employe->id)
            ->set('type', 'conge_annuel')
            ->set('date_debut', '2026-09-13')
            ->set('date_fin', '2026-09-26')
            ->set('motif', 'Congé annuel')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSee('imprimez le formulaire');

        $conge = Conge::sole();
        $this->assertSame(StatutConge::FormulaireRemis, $conge->statut);
        $this->assertSame($rh->id, $conge->saisi_par_id);
        // Deux semaines posées, quatre journées de weekend non décomptées.
        $this->assertEquals(10, $conge->nombre_jours);

        // Le formulaire pré-rempli est téléchargeable dès l'ouverture.
        $this->actingAs($rh)->get(route('conges.formulaire', $conge))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_le_circuit_complet_acte_le_conge_et_decompte_le_solde(): void
    {
        Storage::fake(Conge::DISQUE);
        ParametresApplication::courants()->update(['nom_application' => 'ANEFIP']);
        $rh = $this->creerCompteRh();
        $conge = Conge::factory()->for($this->employe)->create([
            'date_debut' => '2026-09-13', 'date_fin' => '2026-09-17', 'nombre_jours' => 5,
        ]);

        // Le solde est réservé mais pas encore consommé.
        $this->assertEquals(30, $this->employe->soldeCongeRestant(2026));
        $this->assertEquals(25, $this->employe->soldeCongeDisponible(2026));

        $composant = Livewire::actingAs($rh)->test(Personnel::class);

        // Étape 2 : le formulaire visé par le chef revient.
        $composant->call('ouvrirVisa', $conge->id)
            ->assertSet('chefVisaId', $this->chef->id)
            ->set('dateVisa', '2026-09-12')
            ->set('formulaireSigne', UploadedFile::fake()->create('formulaire.pdf', 40, 'application/pdf'))
            ->call('enregistrerVisa')
            ->assertHasNoErrors();

        $conge->refresh();
        $this->assertSame(StatutConge::ViseParLeChef, $conge->statut);
        $this->assertSame($this->chef->id, $conge->manager_validateur_id);
        $this->assertTrue($conge->formulaireSigneRecu());
        Storage::disk(Conge::DISQUE)->assertExists($conge->formulaire_signe_path);

        // Étape 3 : l'attestation est établie et numérotée.
        $composant->call('etablirAttestation', $conge->id)->assertSee('Attestation n° 1/ANEFIP');

        $conge->refresh();
        $this->assertSame(StatutConge::AttestationASigner, $conge->statut);
        $this->assertSame(1, $conge->numero_attestation);

        $this->actingAs($rh)->get(route('conges.attestation', $conge))->assertOk();
        $this->actingAs($rh)->get(route('conges.recapitulatif', $conge))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // Étape 4 : le directeur général a signé.
        $composant->call('ouvrirSignature', $conge->id)
            ->set('dateSignature', '2026-09-12')
            ->set('attestationSignee', UploadedFile::fake()->create('attestation.pdf', 40, 'application/pdf'))
            ->call('enregistrerSignature')
            ->assertHasNoErrors()
            ->assertSee('Congé acté');

        $conge->refresh();
        $this->assertSame(StatutConge::Acte, $conge->statut);
        $this->assertTrue($conge->attestationSigneeRecue());
        $this->assertSame($rh->id, $conge->dg_validateur_id);

        // Le solde est maintenant consommé.
        $this->assertEquals(25, $this->employe->soldeCongeRestant(2026));
        $this->assertEquals(25, $this->employe->soldeCongeDisponible(2026));
    }

    public function test_l_attestation_ne_s_etablit_pas_avant_le_visa_du_chef(): void
    {
        $conge = Conge::factory()->for($this->employe)->create();

        Livewire::actingAs($this->creerCompteRh())
            ->test(Personnel::class)
            ->call('etablirAttestation', $conge->id)
            ->assertForbidden();

        $this->assertFalse($conge->fresh()->attestationEtablie());
        $this->actingAs($this->creerCompteRh())->get(route('conges.attestation', $conge))->assertNotFound();
    }

    public function test_le_visa_exige_le_formulaire_signe(): void
    {
        $conge = Conge::factory()->for($this->employe)->create();

        Livewire::actingAs($this->creerCompteRh())
            ->test(Personnel::class)
            ->call('ouvrirVisa', $conge->id)
            ->call('enregistrerVisa')
            ->assertHasErrors(['formulaireSigne']);

        $this->assertSame(StatutConge::FormulaireRemis, $conge->fresh()->statut);
    }

    public function test_une_demande_peut_etre_refusee_avec_son_motif(): void
    {
        $conge = Conge::factory()->for($this->employe)->vise()->create([
            'date_debut' => '2026-09-13', 'date_fin' => '2026-09-17', 'nombre_jours' => 5,
        ]);

        $composant = Livewire::actingAs($this->creerCompteRh())
            ->test(Personnel::class)
            ->call('refuser', $conge->id)
            ->assertSet('refusId', $conge->id);

        $composant->set('motifRefus', '')->call('confirmerRefus')->assertHasErrors(['motifRefus']);
        $composant->set('motifRefus', 'Effectif insuffisant')->call('confirmerRefus')->assertHasNoErrors();

        $conge->refresh();
        $this->assertSame(StatutConge::Refuse, $conge->statut);
        $this->assertSame('Effectif insuffisant', $conge->commentaire_refus);

        // Un congé refusé ne consomme ni ne réserve le solde.
        $this->assertEquals(30, $this->employe->soldeCongeDisponible(2026));
    }

    public function test_le_solde_de_l_employe_est_controle_a_l_ouverture(): void
    {
        $employe = Employe::factory()->create(['prenom' => 'Warda', 'nom' => 'Salam']);
        SoldeCongeAnnuel::factory()->for($employe)->create(['annee' => 2026, 'jours_acquis' => 5]);

        Livewire::actingAs($this->creerCompteRh())
            ->test(Personnel::class)
            ->call('nouveau')
            ->set('employe_id', $employe->id)
            ->set('type', 'conge_annuel')
            ->set('date_debut', '2026-09-13')
            ->set('date_fin', '2026-09-26')
            ->call('enregistrer')
            ->assertHasErrors(['date_fin']);

        $this->assertSame(0, Conge::count());
    }

    public function test_un_jour_ferie_n_est_pas_decompte(): void
    {
        JourFerie::factory()->create(['date' => '2026-09-16', 'libelle' => 'Fête mobile']);

        Livewire::actingAs($this->creerCompteRh())
            ->test(Personnel::class)
            ->call('nouveau')
            ->set('employe_id', $this->employe->id)
            ->set('type', 'conge_annuel')
            ->set('date_debut', '2026-09-13')
            ->set('date_fin', '2026-09-17')
            ->assertSet('nombreJours', 4)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertEquals(4, Conge::sole()->nombre_jours);
    }

    public function test_conduire_le_circuit_suppose_le_droit_d_etablir_les_attestations(): void
    {
        $compteChef = User::factory()->create(['employe_id' => $this->chef->id]);
        $sienne = Conge::factory()->for($this->employe)->create();
        $etrangere = Conge::factory()->create();

        // Le chef suit son unité mais ne conduit pas le circuit.
        Livewire::actingAs($compteChef)->test(Personnel::class)->call('ouvrirVisa', $sienne->id)->assertForbidden();

        // Le RH ne sort pas de son périmètre non plus.
        Livewire::actingAs($this->creerCompteRh())->test(Personnel::class)->call('ouvrirVisa', $etrangere->id)->assertHasNoErrors();
    }

    public function test_un_conge_deja_accorde_est_enregistre_comme_acte(): void
    {
        $rh = $this->creerCompteRh();

        Livewire::actingAs($rh)
            ->test(Personnel::class)
            ->call('nouveau')
            ->set('employe_id', $this->employe->id)
            ->set('type', 'conge_annuel')
            ->set('date_debut', '2026-03-02')
            ->set('date_fin', '2026-03-05')
            ->set('dejaAccorde', true)
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSee('enregistré comme déjà accordé');

        $conge = Conge::sole();
        $this->assertSame(StatutConge::Acte, $conge->statut);
        $this->assertTrue($conge->estEnregistrementRetroactif());
    }

    public function test_une_demande_ouverte_par_erreur_se_retire_avant_d_etre_actee(): void
    {
        Storage::fake(Conge::DISQUE);
        $rh = $this->creerCompteRh();

        $enCours = Conge::factory()->for($this->employe)->create();
        $actee = Conge::factory()->for($this->employe)->acte()->create(['date_debut' => '2026-02-02', 'date_fin' => '2026-02-05']);

        Livewire::actingAs($rh)->test(Personnel::class)->call('supprimer', $enCours->id)->assertHasNoErrors();
        $this->assertModelMissing($enCours);

        Livewire::actingAs($rh)->test(Personnel::class)->call('supprimer', $actee->id)->assertForbidden();
        $this->assertModelExists($actee);
    }

    public function test_les_pieces_signees_sont_reservees_aux_habilites(): void
    {
        Storage::fake(Conge::DISQUE);

        $chemin = UploadedFile::fake()->create('formulaire.pdf', 20, 'application/pdf')
            ->store(Conge::DOSSIER, Conge::DISQUE);
        $conge = Conge::factory()->for($this->employe)->vise()->create([
            'formulaire_signe_path' => $chemin, 'formulaire_signe_nom' => 'formulaire.pdf',
        ]);

        $this->actingAs($this->creerCompteRh())->get(route('conges.piece', [$conge, 'formulaire']))
            ->assertOk()
            ->assertDownload('formulaire.pdf');

        // Aucun agent n'accède aux pièces, pas même le titulaire : elles lui sont remises.
        $sien = User::factory()->create(['employe_id' => $this->employe->id]);
        $this->actingAs($sien)->get(route('conges.piece', [$conge, 'formulaire']))->assertForbidden();

        $intrus = User::factory()->create(['employe_id' => Employe::factory()->create()->id]);
        $this->actingAs($intrus)->get(route('conges.piece', [$conge, 'formulaire']))->assertForbidden();

        // Une pièce non déposée n'existe pas.
        $this->actingAs($this->creerCompteRh())->get(route('conges.piece', [$conge, 'attestation']))->assertNotFound();
    }
}
