<?php

namespace Tests\Feature;

use App\Livewire\Departement\Index as DepartementIndex;
use App\Livewire\Employe\Index as EmployeIndex;
use App\Models\Departement;
use App\Models\Employe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tri des colonnes : un clic trie, un second inverse le sens.
 */
class TriDesListesTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_liste_des_employes_est_triee_par_nom_par_defaut(): void
    {
        Employe::factory()->create(['nom' => 'Zaki', 'prenom' => 'Ali', 'matricule' => '1003']);
        Employe::factory()->create(['nom' => 'Bouh', 'prenom' => 'Ibado', 'matricule' => '1001']);

        Livewire::actingAs($this->creerCompteRh())
            ->test(EmployeIndex::class)
            ->assertSet('tri', 'nom')
            ->assertSet('sens', 'asc')
            ->assertSeeInOrder(['Ibado Bouh', 'Ali Zaki']);
    }

    public function test_un_clic_trie_la_colonne_et_un_second_inverse_le_sens(): void
    {
        Employe::factory()->create(['nom' => 'Bouh', 'prenom' => 'Ibado', 'poste' => 'Agent']);
        Employe::factory()->create(['nom' => 'Zaki', 'prenom' => 'Ali', 'poste' => 'Comptable']);

        Livewire::actingAs($this->creerCompteRh())
            ->test(EmployeIndex::class)
            ->call('trierPar', 'poste')
            ->assertSet('sens', 'asc')
            ->assertSeeInOrder(['Agent', 'Comptable'])
            ->call('trierPar', 'poste')
            ->assertSet('sens', 'desc')
            ->assertSeeInOrder(['Comptable', 'Agent']);
    }

    public function test_une_date_se_trie_du_plus_recent_au_plus_ancien_au_premier_clic(): void
    {
        Employe::factory()->create(['nom' => 'Ancien', 'prenom' => 'Ali', 'date_embauche' => '2010-01-01']);
        Employe::factory()->create(['nom' => 'Recent', 'prenom' => 'Ibado', 'date_embauche' => '2026-01-01']);

        Livewire::actingAs($this->creerCompteRh())
            ->test(EmployeIndex::class)
            ->call('trierPar', 'date_embauche')
            ->assertSet('sens', 'desc')
            ->assertSeeInOrder(['Ibado Recent', 'Ali Ancien']);
    }

    public function test_une_colonne_inconnue_est_ignoree(): void
    {
        Livewire::actingAs($this->creerCompteRh())
            ->test(EmployeIndex::class)
            ->call('trierPar', 'numero_compte_bancaire')
            ->assertSet('tri', 'nom')
            ->assertSet('sens', 'asc');
    }

    public function test_le_tri_suit_une_colonne_d_une_table_liee(): void
    {
        $administration = Departement::factory()->create(['nom' => 'Administration']);
        $emploi = Departement::factory()->create(['nom' => 'Emploi']);
        Employe::factory()->create(['nom' => 'Bouh', 'prenom' => 'Ibado', 'departement_id' => $emploi->id]);
        Employe::factory()->create(['nom' => 'Zaki', 'prenom' => 'Ali', 'departement_id' => $administration->id]);

        Livewire::actingAs($this->creerCompteRh())
            ->test(EmployeIndex::class)
            ->call('trierPar', 'departement')
            ->assertSeeInOrder(['Ali Zaki', 'Ibado Bouh']);
    }

    public function test_les_departements_se_trient_par_effectif(): void
    {
        $petite = Departement::factory()->create(['nom' => 'Service creux']);
        $grande = Departement::factory()->create(['nom' => 'Service fourni']);
        Employe::factory()->count(3)->create(['departement_id' => $grande->id]);
        Employe::factory()->create(['departement_id' => $petite->id]);

        Livewire::actingAs($this->creerCompteAdmin())
            ->test(DepartementIndex::class)
            ->call('trierPar', 'employes')
            ->assertSet('sens', 'desc')
            ->assertSeeInOrder(['Service fourni', 'Service creux']);
    }
}
