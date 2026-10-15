<?php

namespace App\Livewire\Paie;

use App\Enums\StatutFichePaie;
use App\Models\Employe;
use App\Models\FichePaie;
use App\Services\Paie\AucunContratActif;
use App\Services\Paie\PaieCalculateur;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Generation extends Component
{
    use AuthorizesRequests;

    public int $annee;

    public int $mois;

    /** Primes saisies par fiche brouillon (clé : id de la fiche). */
    public array $primes = [];

    /** Indemnités saisies par fiche brouillon (clé : id de la fiche). */
    public array $indemnites = [];

    public ?string $information = null;

    protected PaieCalculateur $calculateur;

    public function boot(PaieCalculateur $calculateur): void
    {
        $this->calculateur = $calculateur;
    }

    public function mount(): void
    {
        $this->authorize('preparer', FichePaie::class);

        $this->annee = now()->year;
        $this->mois = now()->month;
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['annee', 'mois'], true)) {
            $this->information = null;
        }
    }

    public function render()
    {
        $fiches = $this->fichesPeriode();

        foreach ($fiches as $fiche) {
            $this->primes[$fiche->id] ??= (string) $fiche->primes;
            $this->indemnites[$fiche->id] ??= (string) $fiche->indemnites;
        }

        return view('livewire.paie.generation', [
            'fiches' => $fiches,
            'periode' => FichePaie::libellePeriode($this->annee, $this->mois),
            'aGenerer' => $this->employesAGenerer($fiches)->count(),
            'nombreBrouillons' => $fiches->filter(fn (FichePaie $fiche) => ! $fiche->estValidee())->count(),
            'totaux' => [
                'brut' => $fiches->sum(fn (FichePaie $fiche) => (float) $fiche->brut),
                'cnss_employeur' => $fiches->sum(fn (FichePaie $fiche) => (float) $fiche->cnss_employeur),
                'net' => $fiches->sum(fn (FichePaie $fiche) => (float) $fiche->net_a_payer),
                'cout_employeur' => $fiches->sum(fn (FichePaie $fiche) => $fiche->coutEmployeur()),
            ],
            'moisDisponibles' => $this->moisDisponibles(),
            'annees' => range(now()->year - 2, now()->year + 1),
            // La validation est réservée au directeur général : le RH prépare seulement.
            'peutValider' => auth()->user()->peutValiderPaie(),
        ]);
    }

    /**
     * Crée un brouillon pour chaque employé actif sous contrat sur la période et sans fiche.
     */
    public function generer(): void
    {
        $this->authorize('preparer', FichePaie::class);

        $crees = 0;
        $ignores = 0;

        foreach ($this->employesAGenerer($this->fichesPeriode(), inclureSansContrat: true) as $employe) {
            try {
                $montants = $this->calculateur->calculer($employe, $this->annee, $this->mois, 0.0, 0.0);
            } catch (AucunContratActif) {
                $ignores++;

                continue;
            }

            FichePaie::create([
                'employe_id' => $employe->id,
                'annee' => $this->annee,
                'mois' => $this->mois,
                'statut' => StatutFichePaie::Brouillon,
                ...$montants->attributsFiche(),
            ]);
            $crees++;
        }

        $this->information = sprintf(
            '%d brouillon(s) généré(s) pour %s, %d employé(s) actif(s) ignoré(s) faute de contrat en vigueur sur la période.',
            $crees,
            FichePaie::libellePeriode($this->annee, $this->mois),
            $ignores,
        );
    }

    /**
     * Enregistre les primes/indemnités saisies et recalcule la fiche (brouillon uniquement).
     */
    public function mettreAJour(int $ficheId): void
    {
        $this->authorize('preparer', FichePaie::class);

        $fiche = FichePaie::with('employe.contrats')->findOrFail($ficheId);

        abort_if($fiche->estValidee(), 403, 'Une fiche validée ne peut plus être modifiée.');

        $this->validate(
            ["primes.{$ficheId}" => ['required', 'numeric', 'min:0'], "indemnites.{$ficheId}" => ['required', 'numeric', 'min:0']],
            [],
            ["primes.{$ficheId}" => 'primes', "indemnites.{$ficheId}" => 'indemnités'],
        );

        $montants = $this->calculateur->recalculer($fiche, (float) $this->primes[$ficheId], (float) $this->indemnites[$ficheId]);

        $fiche->update($montants->attributsFiche());
    }

    public function valider(int $ficheId): void
    {
        $this->authorize('valider', FichePaie::class);

        $fiche = FichePaie::findOrFail($ficheId);

        if ($fiche->estValidee()) {
            return;
        }

        $fiche->update($this->attributsValidation());
    }

    public function validerTout(): void
    {
        $this->authorize('valider', FichePaie::class);

        FichePaie::query()
            ->where('annee', $this->annee)
            ->where('mois', $this->mois)
            ->where('statut', StatutFichePaie::Brouillon->value)
            ->update($this->attributsValidation());
    }

    public function supprimer(int $ficheId): void
    {
        $this->authorize('preparer', FichePaie::class);

        $fiche = FichePaie::findOrFail($ficheId);

        abort_if($fiche->estValidee(), 403, 'Une fiche validée ne peut pas être supprimée.');

        $fiche->delete();

        unset($this->primes[$ficheId], $this->indemnites[$ficheId]);
    }

    /**
     * @return array{statut: string, validee_par_id: int, validee_le: Carbon}
     */
    private function attributsValidation(): array
    {
        return [
            'statut' => StatutFichePaie::Validee->value,
            'validee_par_id' => auth()->id(),
            'validee_le' => now(),
        ];
    }

    /**
     * @return Collection<int, FichePaie>
     */
    private function fichesPeriode(): Collection
    {
        return FichePaie::query()
            ->with(['employe.departement', 'valideePar'])
            ->where('annee', $this->annee)
            ->where('mois', $this->mois)
            ->get()
            ->sortBy(fn (FichePaie $fiche) => $fiche->employe?->nomComplet() ?? '')
            ->values();
    }

    /**
     * Employés actifs sans fiche sur la période. Par défaut, seuls ceux ayant un contrat
     * en vigueur sont retenus.
     *
     * @param  Collection<int, FichePaie>  $fiches
     * @return Collection<int, Employe>
     */
    private function employesAGenerer(Collection $fiches, bool $inclureSansContrat = false): Collection
    {
        $employes = Employe::query()
            ->where('actif', true)
            ->whereNotIn('id', $fiches->pluck('employe_id'))
            ->with('contrats')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        if ($inclureSansContrat) {
            return $employes;
        }

        return $employes->filter(fn (Employe $employe) => $this->calculateur->contratPour($employe, $this->annee, $this->mois) !== null);
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
