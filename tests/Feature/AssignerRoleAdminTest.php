<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignerRoleAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_attribue_le_role_admin_au_compte_indique(): void
    {
        $user = User::factory()->create(['email' => 'informatique@rh.test']);

        $this->artisan('roles:assigner-admin', ['email' => 'informatique@rh.test'])->assertSuccessful();

        $this->assertTrue($user->fresh()->estAdmin());
        $this->assertFalse($user->fresh()->estDg());
        $this->assertFalse(User::factory()->create()->estAdmin());
    }

    public function test_echoue_si_le_compte_n_existe_pas(): void
    {
        $existant = User::factory()->create();

        $this->artisan('roles:assigner-admin', ['email' => 'inconnu@rh.test'])->assertFailed();

        $this->assertDatabaseCount('model_has_roles', 0);
        $this->assertFalse($existant->fresh()->estAdmin());
    }
}
