<?php

namespace App\Services\Organigramme;

use App\Models\Departement;
use App\Models\Employe;
use App\Models\ParametresApplication;
use Illuminate\Support\Collection;

/**
 * Construit l'organigramme :
 * 1. les unités racines (la Direction générale en premier), puis leurs sous-unités en cascade,
 *    chacune avec son responsable ;
 * 2. sous chaque unité, ses employés actifs (hors responsable, déjà affiché sur l'unité).
 *
 * La Direction générale est une Direction comme les autres : son responsable est le DG.
 * Les employés inactifs sont exclus. Un responsable inactif est traité comme un poste vacant.
 * Tout est chargé en deux requêtes, puis assemblé en mémoire.
 */
class OrganigrammeBuilder
{
    public function construire(): Organigramme
    {
        $directionGeneraleId = ParametresApplication::courants()->direction_generale_id;

        $departements = Departement::query()
            ->with('responsable')
            ->orderBy('nom')
            ->get()
            ->sortBy(fn (Departement $departement) => $departement->id === $directionGeneraleId ? 0 : 1)
            ->values();

        $parDepartement = Employe::query()
            ->where('actif', true)
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get()
            ->groupBy(fn (Employe $employe) => $employe->departement_id ?? 0);

        $parParent = $departements->groupBy(fn (Departement $departement) => $departement->parent_departement_id ?? 0);

        $visites = [];
        $racines = $this->construireNoeuds($parParent->get(0, collect()), $parParent, $parDepartement, $visites, $directionGeneraleId);

        // Unités jamais atteintes depuis une racine (cycle présent en base) :
        // rattachées au premier niveau pour rester visibles plutôt que disparaître.
        $orphelines = $departements->reject(fn (Departement $departement) => isset($visites[$departement->id]));

        if ($orphelines->isNotEmpty()) {
            $racines = [...$racines, ...$this->construireNoeuds($orphelines, $parParent, $parDepartement, $visites, $directionGeneraleId)];
        }

        return new Organigramme(
            racines: $racines,
            employesSansDepartement: $parDepartement->get(0, collect())->values(),
        );
    }

    /**
     * @param  Collection<int, Departement>  $departements
     * @param  Collection<int|string, Collection<int, Departement>>  $parParent
     * @param  Collection<int|string, Collection<int, Employe>>  $parDepartement
     * @param  array<int, bool>  $visites
     * @return NoeudDepartement[]
     */
    private function construireNoeuds(Collection $departements, Collection $parParent, Collection $parDepartement, array &$visites, ?int $directionGeneraleId): array
    {
        $noeuds = [];

        foreach ($departements as $departement) {
            if (isset($visites[$departement->id])) {
                continue;
            }

            $visites[$departement->id] = true;

            $responsable = $departement->responsable;

            if ($responsable !== null && ! $responsable->actif) {
                $responsable = null;
            }

            $employes = $parDepartement
                ->get($departement->id, collect())
                ->reject(fn (Employe $employe) => $employe->id === $departement->responsable_id)
                ->values();

            $enfants = $this->construireNoeuds($parParent->get($departement->id, collect()), $parParent, $parDepartement, $visites, $directionGeneraleId);

            $noeuds[] = new NoeudDepartement(
                departement: $departement,
                type: $departement->type,
                responsable: $responsable,
                enfants: $enfants,
                employes: $employes,
                estDirectionGenerale: $departement->id === $directionGeneraleId,
            );
        }

        return $noeuds;
    }
}
