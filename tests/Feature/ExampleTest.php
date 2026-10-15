<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La racine sert la page d'accueil de l'application (formulaire de connexion).
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertOk();
    }
}
