<?php

namespace App\Livewire\Paie;

use App\Enums\StatutFichePaie;
use App\Livewire\Concerns\TriDeListe;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\FichePaie;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Historique extends Component
{
    use AuthorizesRequests, TriDeListe, WithPagination;

    public int $annee;

    /** null = tous les mois de l'année. */
    public ?int $mois = null;

    public ?int $departement_id = null;

    /** '' = tous les statuts. */
    public string $statut = '';

    public function mount(): void
    {
        $this->authorize('preparer', FichePaie::class);

        $this->annee = now()->year;
        $this->mois = now()->month;
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['annee', 'mois', 'departement_id', 'statut'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $requete = $this->requeteFiltree();

        $totaux = (clone $requete)
            ->selectRaw('COUNT(*) as nombre')
            ->selectRaw('COALESCE(SUM(brut), 0) as brut')
            ->selectRaw('COALESCE(SUM(cnss_salarie), 0) as cnss_salarie')
            ->selectRaw('COALESCE(SUM(cnss_employeur), 0) as cnss_employeur')
            ->selectRaw('COALESCE(SUM(irpp), 0) as irpp')
            ->selectRaw('COALESCE(SUM(net_a_payer), 0) as net')
            ->first();

        return view('livewire.paie.historique', [
            'fiches' => $requete
                ->with(['employe.departement', 'valideePar'])
                ->tap(fn ($requete) => $this->appliquerTri($requete))
                ->orderBy('id')
                ->paginate(25),
            'totaux' => [
                'nombre' => (int) $totaux->nombre,
                'brut' => (float) $totaux->brut,
                'cnss_salarie' => (float) $totaux->cnss_salarie,
                'cnss_employeur' => (float) $totaux->cnss_employeur,
                'irpp' => (float) $totaux->irpp,
                'net' => (float) $totaux->net,
                'cout_employeur' => (float) $totaux->brut + (float) $totaux->cnss_employeur,
            ],
            'periode' => $this->mois === null ? 'Année '.$this->annee : FichePaie::libellePeriode($this->annee, $this->mois),
            'departements' => Departement::query()->orderBy('nom')->get(),
            'statuts' => StatutFichePaie::cases(),
            'moisDisponibles' => $this->moisDisponibles(),
            'annees' => range(now()->year - 2, now()->year + 1),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function colonnesTriables(): array
    {
        return [
            'periode' => ['annee', 'mois'],
            'employe' => Employe::select('nom')->whereColumn('employes.id', 'fiches_paie.employe_id'),
            'brut' => 'brut',
            'irpp' => 'irpp',
            'net' => 'net_a_payer',
            'statut' => 'statut',
        ];
    }

    /**
     * @return string[]
     */
    protected function colonnesDecroissantesDAbord(): array
    {
        return ['periode', 'brut', 'irpp', 'net'];
    }

    private function requeteFiltree(): Builder
    {
        return FichePaie::query()
            ->where('annee', $this->annee)
            ->when($this->mois !== null, fn (Builder $query) => $query->where('mois', $this->mois))
            ->when($this->departement_id !== null, fn (Builder $query) => $query->whereHas(
                'employe',
                fn (Builder $employe) => $employe->where('departement_id', $this->departement_id),
            ))
            ->when($this->statut !== '', fn (Builder $query) => $query->where('statut', $this->statut));
    }

    /**
     * @return array<int, string>
     */
    private function moisDisponibles(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn (int $mois) => [$mois => ucfirst(CarbonImmutable::create(2000, $mois, 1)->locale('fr')->isoFormat('MMMM'))])
            ->all();
    }
}
