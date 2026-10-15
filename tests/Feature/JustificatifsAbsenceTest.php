<?php

namespace Tests\Feature;

use App\Enums\StatutJustificatif;
use App\Enums\StatutPresence;
use App\Livewire\Presence\Justificatifs;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\JustificatifAbsence;
use App\Models\User;
use App\Services\Pointages\RapportPresence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Un employé justifie une absence en joignant sa pièce ; son responsable
 * accepte ou refuse, et le rapport de présence suit.
 */
class JustificatifsAbsenceTest extends TestCase
{
    use RefreshDatabase;

    private Employe $employe;

    private User $compteEmploye;

    private User $compteManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Mardi 15 septembre 2026 ; le weekend local est vendredi-samedi.
        $this->travelTo('2026-09-15 09:00:00');

        $manager = Employe::factory()->create(['prenom' => 'Awa', 'nom' => 'Responsable']);
        $departement = Departement::factory()->create(['responsable_id' => $manager->id]);
        $this->employe = Employe::factory()->create([
            'prenom' => 'Moussa', 'nom' => 'Absent', 'departement_id' => $departement->id, 'date_embauche' => '2020-01-01',
        ]);
        $this->compteEmploye = User::factory()->create(['employe_id' => $this->employe->id]);
        $this->compteManager = User::factory()->create(['employe_id' => $manager->id]);
    }

    public function test_le_service_du_personnel_justifie_une_absence_avec_sa_piece(): void
    {
        Storage::fake(JustificatifAbsence::DISQUE);
        $rh = $this->creerCompteRh();

        $this->actingAs($rh)->get('/presence/justificatifs')
            ->assertOk()
            ->assertSeeLivewire(Justificatifs::class);

        Livewire::actingAs($rh)
            ->test(Justificatifs::class)
            ->call('nouveau')
            ->assertSeeHtml('role="dialog"')
            ->set('employe_id', $this->employe->id)
            ->set('date', '2026-09-14')
            ->set('motif', 'Consultation à l\'hôpital Peltier')
            ->set('piece', UploadedFile::fake()->create('certificat.pdf', 40, 'application/pdf'))
            ->call('enregistrer')
            ->assertHasNoErrors();

        $justificatif = JustificatifAbsence::sole();
        $this->assertSame($this->employe->id, $justificatif->employe_id);
        $this->assertSame('2026-09-14', $justificatif->date->toDateString());
        $this->assertSame(StatutJustificatif::EnAttente, $justificatif->statut);
        $this->assertSame($rh->id, $justificatif->saisi_par_id);
        $this->assertSame('certificat.pdf', $justificatif->nomDeLaPiece());
        Storage::disk(JustificatifAbsence::DISQUE)->assertExists($justificatif->fichier_path);
    }

    public function test_le_motif_est_obligatoire_et_la_piece_limitee_aux_documents(): void
    {
        $composant = Livewire::actingAs($this->creerCompteRh())
            ->test(Justificatifs::class)
            ->call('nouveau')
            ->set('employe_id', $this->employe->id)
            ->set('date', '2026-09-14');

        $composant->set('motif', '')->call('enregistrer')->assertHasErrors(['motif']);

        $composant->set('motif', 'Motif valable')
            ->set('piece', UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'))
            ->call('enregistrer')
            ->assertHasErrors(['piece']);

        $this->assertSame(0, JustificatifAbsence::count());
    }

    public function test_une_justification_acceptee_change_le_statut_du_rapport(): void
    {
        $justificatif = JustificatifAbsence::factory()->for($this->employe)->create(['date' => '2026-09-14']);

        $rapport = app(RapportPresence::class);
        $jour = CarbonImmutable::parse('2026-09-14');

        // En attente, la journée reste une absence non justifiée.
        $lignes = $rapport->generer(collect([$this->employe]), $jour, $jour);
        $this->assertSame(StatutPresence::Absence, $lignes[0]['jours']['2026-09-14']['statut']);
        $this->assertSame(1, $lignes[0]['totaux']['absence']);

        Livewire::actingAs($this->compteManager)
            ->test(Justificatifs::class)
            ->assertSee('Moussa Absent')
            ->call('accepter', $justificatif->id)
            ->assertSee('Absence du 14/09/2026 justifiée');

        $lignes = $rapport->generer(collect([$this->employe->fresh()]), $jour, $jour);
        $this->assertSame(StatutPresence::AbsenceJustifiee, $lignes[0]['jours']['2026-09-14']['statut']);
        $this->assertSame(0, $lignes[0]['totaux']['absence']);
        $this->assertSame(1, $lignes[0]['totaux']['absence_justifiee']);

        $justificatif->refresh();
        $this->assertTrue($justificatif->estAccepte());
        $this->assertSame($this->compteManager->id, $justificatif->decide_par_id);
    }

    public function test_le_refus_exige_un_motif_et_laisse_l_absence_non_justifiee(): void
    {
        $justificatif = JustificatifAbsence::factory()->for($this->employe)->create(['date' => '2026-09-14']);

        $composant = Livewire::actingAs($this->compteManager)
            ->test(Justificatifs::class)
            ->call('refuser', $justificatif->id)
            ->assertSet('refusId', $justificatif->id);

        $composant->set('motifRefus', '')->call('confirmerRefus')->assertHasErrors(['motifRefus']);
        $this->assertSame(StatutJustificatif::EnAttente, $justificatif->fresh()->statut);

        $composant->set('motifRefus', 'Aucune pièce fournie')->call('confirmerRefus')->assertHasNoErrors();

        $justificatif->refresh();
        $this->assertSame(StatutJustificatif::Refuse, $justificatif->statut);
        $this->assertSame('Aucune pièce fournie', $justificatif->commentaire_decision);

        $jour = CarbonImmutable::parse('2026-09-14');
        $lignes = app(RapportPresence::class)->generer(collect([$this->employe]), $jour, $jour);
        $this->assertSame(StatutPresence::Absence, $lignes[0]['jours']['2026-09-14']['statut']);
    }

    public function test_personne_ne_tranche_sa_propre_justification(): void
    {
        $sienne = JustificatifAbsence::factory()->for($this->compteManager->employe)->create(['date' => '2026-09-14']);

        Livewire::actingAs($this->compteManager)
            ->test(Justificatifs::class)
            ->call('accepter', $sienne->id)
            ->assertForbidden();

        $this->assertSame(StatutJustificatif::EnAttente, $sienne->fresh()->statut);
    }

    public function test_un_agent_ne_justifie_rien_lui_meme(): void
    {
        $this->actingAs($this->compteEmploye)->get('/presence/justificatifs')->assertForbidden();

        // Ni pour un collègue, ni pour lui-même : tout passe par le service.
        $this->assertFalse($this->compteEmploye->can('creerPour', [JustificatifAbsence::class, Employe::factory()->create()]));
        $this->assertFalse($this->compteEmploye->can('creerPour', [JustificatifAbsence::class, $this->employe]));
    }

    public function test_la_piece_n_est_accessible_qu_a_ceux_qui_suivent_la_presence(): void
    {
        Storage::fake(JustificatifAbsence::DISQUE);

        $chemin = UploadedFile::fake()->create('certificat.pdf', 20, 'application/pdf')
            ->store(JustificatifAbsence::DOSSIER, JustificatifAbsence::DISQUE);
        $justificatif = JustificatifAbsence::factory()->for($this->employe)->create([
            'date' => '2026-09-14', 'fichier_path' => $chemin, 'fichier_nom' => 'certificat.pdf',
        ]);

        $this->actingAs($this->compteManager)->get(route('presence.justificatif.piece', $justificatif))
            ->assertOk()
            ->assertDownload('certificat.pdf');

        // L'agent concerné n'y accède plus : sa pièce est remise au service.
        $this->actingAs($this->compteEmploye)->get(route('presence.justificatif.piece', $justificatif))->assertForbidden();

        $intrus = User::factory()->create(['employe_id' => Employe::factory()->create()->id]);
        $this->actingAs($intrus)->get(route('presence.justificatif.piece', $justificatif))->assertForbidden();
    }

    public function test_le_service_du_personnel_saisit_une_justification_recue_sur_papier(): void
    {
        $rh = $this->creerCompteRh();

        Livewire::actingAs($rh)
            ->test(Justificatifs::class)
            ->call('nouveau')
            ->set('employe_id', $this->employe->id)
            ->set('date', '2026-09-14')
            ->set('motif', 'Certificat médical remis à l\'accueil')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSee('Justification enregistrée pour Moussa Absent');

        $justificatif = JustificatifAbsence::sole();
        $this->assertSame($rh->id, $justificatif->saisi_par_id);
        $this->assertSame(StatutJustificatif::EnAttente, $justificatif->statut);
    }

    public function test_une_justification_acceptee_ne_se_retire_plus(): void
    {
        $acceptee = JustificatifAbsence::factory()->for($this->employe)->accepte()->create(['date' => '2026-09-14']);
        $enAttente = JustificatifAbsence::factory()->for($this->employe)->create(['date' => '2026-09-10']);

        $this->assertFalse($this->compteManager->can('retirer', $acceptee));
        $this->assertTrue($this->compteManager->can('retirer', $enAttente));
    }
}
