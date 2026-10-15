<?php

namespace App\Livewire\Presence;

use App\Enums\StatutPresence;
use App\Services\Pointages\RapportPresence;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
class Rapport extends Component
{
    /** Longueur maximale de la période affichée, en jours. */
    private const JOURS_MAXIMUM = 92;

    public string $date_debut = '';

    public string $date_fin = '';

    public ?int $departement_id = null;

    protected RapportPresence $rapportPresence;

    public function boot(RapportPresence $rapportPresence): void
    {
        $this->rapportPresence = $rapportPresence;
    }

    public function mount(): void
    {
        $user = auth()->user();

        abort_unless($user->peutConsulterPresence(), 403);

        $this->date_debut = now()->startOfMonth()->toDateString();
        $this->date_fin = now()->toDateString();

        // Un manager d'un seul département est directement positionné dessus.
        if (! $user->estDg()) {
            $departements = $user->departementsSupervises()->pluck('id');
            $this->departement_id = $departements->count() === 1 ? $departements->first() : null;
        }
    }

    public function render()
    {
        $user = auth()->user();
        $departements = $user->departementsSupervises()->orderBy('nom')->get();

        // Le filtre est borné au périmètre de l'utilisateur, même si la valeur est forcée côté client.
        if ($this->departement_id !== null && ! $departements->contains('id', $this->departement_id)) {
            $this->departement_id = null;
        }

        [$debut, $fin, $erreurPeriode] = $this->periode();

        $employes = $user->employesSupervises()
            ->where('actif', true)
            ->when($this->departement_id, fn ($q) => $q->where('departement_id', $this->departement_id))
            ->with('departement')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        $lignes = $erreurPeriode === null ? $this->rapportPresence->generer($employes, $debut, $fin) : [];

        $jours = [];

        if ($erreurPeriode === null) {
            for ($jour = $debut; $jour->lessThanOrEqualTo($fin); $jour = $jour->addDay()) {
                $jours[] = $jour;
            }
        }

        return view('livewire.presence.rapport', [
            'departements' => $departements,
            'estDg' => $user->estDg(),
            'lignes' => $lignes,
            'jours' => $jours,
            'erreurPeriode' => $erreurPeriode,
            'statuts' => StatutPresence::cases(),
        ]);
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable, 2: ?string}
     */
    private function periode(): array
    {
        try {
            $debut = CarbonImmutable::createFromFormat('!Y-m-d', $this->date_debut);
            $fin = CarbonImmutable::createFromFormat('!Y-m-d', $this->date_fin);
        } catch (Throwable) {
            return [null, null, 'Les deux dates sont obligatoires.'];
        }

        if ($fin->lessThan($debut)) {
            return [null, null, 'La date de fin doit être postérieure ou égale à la date de début.'];
        }

        if ($debut->diffInDays($fin) + 1 > self::JOURS_MAXIMUM) {
            return [null, null, sprintf('La période ne peut pas dépasser %d jours.', self::JOURS_MAXIMUM)];
        }

        return [$debut, $fin, null];
    }
}
