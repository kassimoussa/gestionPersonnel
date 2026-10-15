<?php

use App\Http\Controllers\AttestationCongePdfController;
use App\Http\Controllers\BulletinPaiePdfController;
use App\Http\Controllers\DocumentEmployeController;
use App\Http\Controllers\FormulaireCongePdfController;
use App\Http\Controllers\PieceCongeController;
use App\Http\Controllers\RecapitulatifCongesPdfController;
use App\Http\Controllers\OrganigrammePdfController;
use App\Http\Controllers\PieceEvenementEmployeController;
use App\Http\Controllers\PieceJustificativeController;
use App\Livewire\Comptes\Index as ComptesIndex;
use App\Livewire\Conges\Personnel as CongesPersonnel;
use App\Livewire\Contrat\Index as ContratIndex;
use App\Livewire\Departement\Index as DepartementIndex;
use App\Livewire\Employe\Fiche as EmployeFiche;
use App\Livewire\Employe\Index as EmployeIndex;
use App\Livewire\Organigramme\Index as OrganigrammeIndex;
use App\Livewire\Paie\Generation as PaieGeneration;
use App\Livewire\Paie\Historique as PaieHistorique;
use App\Livewire\Parametres\Index as ParametresIndex;
use App\Livewire\Parametres\JoursFeries as ParametresJoursFeries;
use App\Livewire\Presence\Import as PresenceImport;
use App\Livewire\Presence\Justificatifs as PresenceJustificatifs;
use App\Livewire\Presence\Rapport as PresenceRapport;
use App\Livewire\Presence\Saisie as PresenceSaisie;
use App\Livewire\TableauDeBord\Index as TableauDeBordIndex;
use Illuminate\Support\Facades\Route;

// La page d'accueil "/" est le formulaire de connexion, déclaré dans routes/auth.php (route nommée login).

Route::get('dashboard', TableauDeBordIndex::class)
    ->middleware(['auth', 'verified', 'compte.actif'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified', 'compte.actif'])->group(function () {
    Route::get('/departements', DepartementIndex::class)->name('departements.index');
    Route::get('/employes', EmployeIndex::class)->name('employes.index');
    Route::get('/employes/documents/{document}', DocumentEmployeController::class)->name('employes.document');
    Route::get('/employes/evenements/{evenement}/piece', PieceEvenementEmployeController::class)->name('employes.evenement.piece');
    Route::get('/employes/{employe}', EmployeFiche::class)->name('employes.fiche');
    Route::get('/contrats', ContratIndex::class)->name('contrats.index');
    Route::get('/conges/personnel', CongesPersonnel::class)->name('conges.personnel');
    Route::get('/conges/{conge}/attestation', AttestationCongePdfController::class)->name('conges.attestation');
    Route::get('/conges/{conge}/formulaire', FormulaireCongePdfController::class)->name('conges.formulaire');
    Route::get('/conges/{conge}/recapitulatif', RecapitulatifCongesPdfController::class)->name('conges.recapitulatif');
    Route::get('/conges/{conge}/piece/{piece}', PieceCongeController::class)->name('conges.piece');
    Route::get('/presence/import', PresenceImport::class)->name('presence.import');
    Route::get('/presence/saisie', PresenceSaisie::class)->name('presence.saisie');
    Route::get('/presence/rapport', PresenceRapport::class)->name('presence.rapport');
    Route::get('/presence/justificatifs', PresenceJustificatifs::class)->name('presence.justificatifs');
    Route::get('/presence/justificatifs/{justificatif}/piece', PieceJustificativeController::class)->name('presence.justificatif.piece');
    Route::get('/organigramme', OrganigrammeIndex::class)->name('organigramme.index');
    Route::get('/organigramme/pdf', OrganigrammePdfController::class)->name('organigramme.pdf');
    Route::get('/paie/generation', PaieGeneration::class)->name('paie.generation');
    Route::get('/paie/historique', PaieHistorique::class)->name('paie.historique');
    Route::get('/paie/bulletins/{fiche}/pdf', BulletinPaiePdfController::class)->name('paie.bulletin');
    Route::get('/parametres', ParametresIndex::class)->name('parametres.index');
    Route::get('/parametres/jours-feries', ParametresJoursFeries::class)->name('parametres.jours-feries');
    Route::get('/comptes', ComptesIndex::class)->name('comptes.index');
});

require __DIR__.'/auth.php';
