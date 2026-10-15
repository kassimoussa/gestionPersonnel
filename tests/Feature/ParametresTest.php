<?php

namespace Tests\Feature;

use App\Livewire\Parametres\Index;
use App\Models\Employe;
use App\Models\FichePaie;
use App\Models\ParametresApplication;
use App\Models\User;
use App\Services\Organigramme\OrganigrammeBuilder;
use App\Services\Paie\PaieCalculateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ParametresTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->creerCompteAdmin();
    }

    public function test_les_valeurs_de_depart_sont_celles_de_la_charte(): void
    {
        $parametres = ParametresApplication::courants();

        $this->assertSame('ANEFIP', $parametres->nom_application);
        $this->assertSame('#1C3C8F', $parametres->couleurAccent());
        $this->assertSame('#3255B1', $parametres->couleurAccentClaire());
        $this->assertSame('#E8ECF4', $parametres->couleurAccentDouce());
        $this->assertNull($parametres->logoUrl());
        $this->assertSame(1, ParametresApplication::count());
        $this->assertSame($parametres->id, ParametresApplication::courants()->id);
    }

    public function test_l_ecran_est_reserve_a_la_dg(): void
    {
        $this->get('/parametres')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/parametres')->assertForbidden();
        $this->actingAs($this->admin)->get('/parametres')->assertOk()->assertSeeLivewire(Index::class);
    }

    public function test_la_mise_a_jour_est_refletee_dans_le_layout_la_connexion_et_les_pdf(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->assertSet('nom_application', 'ANEFIP')
            ->set('nom_application', 'Société Exemple')
            ->set('couleur_accent', '#7A2E2E')
            ->set('nom_legal_entreprise', 'Société Exemple SARL')
            ->set('adresse_entreprise', "Rue de Venise\nDjibouti")
            ->set('telephone_entreprise', '+253 21 00 00 00')
            ->set('email_entreprise', 'contact@exemple.dj')
            ->set('logo', UploadedFile::fake()->image('logo.png', 200, 80))
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSee('Paramètres enregistrés.');

        $parametres = ParametresApplication::courants();
        $this->assertSame('Société Exemple', $parametres->nom_application);
        $this->assertSame('#7A2E2E', $parametres->couleur_accent);
        $this->assertSame('Société Exemple SARL', $parametres->nom_legal_entreprise);
        $this->assertNotNull($parametres->logo_path);
        Storage::disk('public')->assertExists($parametres->logo_path);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $parametres->logoDataUri());

        // Layout de l'application : nom, variables CSS de l'accent et logo.
        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('<title>Société Exemple</title>', false)
            ->assertSee('--color-accent: #7A2E2E;', false)
            ->assertSee('--color-accent-light: '.$parametres->couleurAccentClaire().';', false)
            ->assertSee($parametres->logoUrl(), false);

        // Écran de connexion.
        auth()->logout();
        $this->get('/')->assertSee('Société Exemple')->assertSee($parametres->logoUrl(), false);

        // PDF de l'organigramme (vue rendue) et bulletin de paie (téléchargement réel).
        $html = view('organigramme.pdf', [
            'organigramme' => app(OrganigrammeBuilder::class)->construire(),
            'parametres' => $parametres,
            'genereLe' => now(),
        ])->render();

        foreach (['Société Exemple SARL', 'Rue de Venise', '+253 21 00 00 00', 'contact@exemple.dj', 'data:image/png;base64,'] as $texte) {
            $this->assertStringContainsString($texte, $html);
        }

        $fiche = FichePaie::factory()->for(Employe::factory()->create())->validee()->create();
        $reponse = $this->actingAs($this->creerCompteDg())->get(route('paie.bulletin', $fiche));
        $reponse->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $reponse->getContent());

        $bulletin = view('paie.bulletin', [
            'fiche' => $fiche->load(['employe.departement', 'valideePar']),
            'parametres' => $parametres,
            'detailCnss' => app(PaieCalculateur::class)->detailCnss((float) $fiche->brut),
            'detailIrpp' => app(PaieCalculateur::class)->detailIrppPourFiche($fiche),
            'genereLe' => now(),
        ])->render();
        $this->assertStringContainsString('Société Exemple SARL', $bulletin);
        $this->assertStringContainsString('contact@exemple.dj', $bulletin);
    }

    public function test_une_couleur_invalide_est_refusee_et_le_logo_peut_etre_retire(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->set('couleur_accent', '#GGGGGG')
            ->call('enregistrer')
            ->assertHasErrors(['couleur_accent']);

        $this->assertSame('#1C3C8F', ParametresApplication::courants()->couleurAccent());

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->set('logo', UploadedFile::fake()->image('logo.png', 120, 120))
            ->call('enregistrer')
            ->assertHasNoErrors();

        $chemin = ParametresApplication::courants()->logo_path;
        Storage::disk('public')->assertExists($chemin);

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->set('retirerLogo', true)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertNull(ParametresApplication::courants()->logo_path);
        Storage::disk('public')->assertMissing($chemin);
    }

    public function test_un_utilisateur_sans_role_ne_peut_pas_enregistrer_les_parametres(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->assertForbidden();
    }
}
