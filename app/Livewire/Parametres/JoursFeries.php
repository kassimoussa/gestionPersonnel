<?php

namespace App\Livewire\Parametres;

use App\Enums\Droit;
use App\Models\JourFerie;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Jours fériés : ils ne sont décomptés ni des congés ni de la présence.
 * Les fêtes à date fixe sont marquées récurrentes une fois pour toutes ;
 * les fêtes mobiles se saisissent année par année.
 */
#[Layout('layouts.app')]
class JoursFeries extends Component
{
    public int $annee = 0;

    public bool $formulaireOuvert = false;

    public ?int $editId = null;

    public string $date = '';

    public string $libelle = '';

    public bool $recurrent = false;

    public ?string $information = null;

    public function mount(): void
    {
        $this->autoriser();

        $this->annee = now()->year;
    }

    public function render()
    {
        return view('livewire.parametres.jours-feries', [
            'feries' => JourFerie::query()
                ->where(fn ($q) => $q->where('recurrent', true)->orWhereYear('date', $this->annee))
                ->orderBy('date')
                ->get()
                ->sortBy(fn (JourFerie $ferie) => ($ferie->recurrent ? '0' : '1').$ferie->date->format('m-d'))
                ->values(),
            'annees' => range(now()->year - 1, now()->year + 2),
        ]);
    }

    public function nouveau(): void
    {
        $this->autoriser();
        $this->reinitialiser();
        $this->date = $this->annee.'-01-01';
        $this->formulaireOuvert = true;
    }

    public function editer(int $id): void
    {
        $this->autoriser();
        $this->reinitialiser();

        $ferie = JourFerie::findOrFail($id);
        $this->editId = $ferie->id;
        $this->date = $ferie->date->toDateString();
        $this->libelle = $ferie->libelle;
        $this->recurrent = $ferie->recurrent;
        $this->formulaireOuvert = true;
    }

    public function enregistrer(): void
    {
        $this->autoriser();

        $donnees = $this->validate([
            'date' => ['required', 'date'],
            'libelle' => ['required', 'string', 'max:255'],
            'recurrent' => ['boolean'],
        ]);

        // whereDate() plutôt qu'une règle unique : SQLite stocke la colonne DATE
        // avec une heure, la comparaison directe laisserait passer le doublon.
        $dejaPris = JourFerie::query()
            ->whereDate('date', $donnees['date'])
            ->when($this->editId, fn ($q) => $q->whereKeyNot($this->editId))
            ->exists();

        if ($dejaPris) {
            throw ValidationException::withMessages([
                'date' => 'Ce jour est déjà enregistré comme férié.',
            ]);
        }

        // Une fête à date fixe ne doit pas exister deux fois sous deux années différentes.
        if ($donnees['recurrent']) {
            $jourEtMois = substr($donnees['date'], 5);

            $doublon = JourFerie::query()
                ->where('recurrent', true)
                ->when($this->editId, fn ($q) => $q->whereKeyNot($this->editId))
                ->get()
                ->contains(fn (JourFerie $ferie) => $ferie->date->format('m-d') === $jourEtMois);

            if ($doublon) {
                throw ValidationException::withMessages([
                    'date' => 'Cette fête à date fixe est déjà enregistrée pour toutes les années.',
                ]);
            }
        }

        JourFerie::updateOrCreate(['id' => $this->editId], $donnees);

        $this->reinitialiser();
        $this->information = 'Calendrier des jours fériés mis à jour.';
    }

    public function supprimer(int $id): void
    {
        $this->autoriser();

        JourFerie::findOrFail($id)->delete();

        $this->information = 'Jour férié retiré du calendrier.';
    }

    public function annuler(): void
    {
        $this->reinitialiser();
    }

    private function autoriser(): void
    {
        abort_unless(auth()->user()->aLeDroit(Droit::ParametresGerer), 403);
    }

    private function reinitialiser(): void
    {
        $this->reset(['formulaireOuvert', 'editId', 'date', 'libelle', 'recurrent', 'information']);
        $this->resetValidation();
    }
}
