<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccueilTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_d_accueil_affiche_directement_le_formulaire_de_connexion(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeVolt('pages.auth.login')
            ->assertSee('ANEFIP')
            ->assertSee('Connexion')
            ->assertSee('images/logo-anefip.svg')
            ->assertDontSee('Laravel');

        $this->assertSame(url('/'), route('login'));
    }

    public function test_l_ancienne_adresse_login_renvoie_vers_l_accueil(): void
    {
        $this->get('/login')->assertRedirect('/');
    }

    public function test_un_utilisateur_connecte_est_renvoye_vers_le_tableau_de_bord(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_une_page_protegee_renvoie_vers_l_accueil_sans_session(): void
    {
        $this->get('/dashboard')->assertRedirect('/');
    }
}
