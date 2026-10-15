<?php

namespace App\Models;

use App\Enums\TypeDepartement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * Unité organisationnelle (direction, division, département ou service).
 * La table s'appelle `departements` au sens générique.
 */
class Departement extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'type', 'responsable_id', 'parent_departement_id'];

    protected $casts = [
        'type' => TypeDepartement::class,
    ];

    public function employes(): HasMany
    {
        return $this->hasMany(Employe::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'responsable_id');
    }

    /**
     * Unité parente (null pour une unité racine).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Departement::class, 'parent_departement_id');
    }

    /**
     * Sous-unités directes.
     */
    public function enfants(): HasMany
    {
        return $this->hasMany(Departement::class, 'parent_departement_id')->orderBy('nom');
    }

    /**
     * Employés actifs rattachés directement à cette unité, hors responsable
     * (celui-ci est affiché comme nœud manager dans l'organigramme).
     */
    public function employesDirects(): HasMany
    {
        return $this->hasMany(Employe::class)
            ->where('actif', true)
            ->when($this->responsable_id !== null, fn ($query) => $query->where('id', '!=', $this->responsable_id))
            ->orderBy('nom')
            ->orderBy('prenom');
    }

    /**
     * Vrai si cette unité est un ancêtre (parent, grand-parent, ...) de $autre.
     * Sert à empêcher les cycles : une unité ne peut pas être rattachée à l'un
     * de ses propres descendants. S'arrête proprement si un cycle existe déjà en base.
     */
    public function estAncetreDe(Departement $autre): bool
    {
        if ($this->id === null) {
            return false;
        }

        $visites = [];
        $courant = $autre->parent;

        while ($courant !== null) {
            if ((int) $courant->id === (int) $this->id) {
                return true;
            }

            if (isset($visites[$courant->id])) {
                return false;
            }

            $visites[$courant->id] = true;
            $courant = $courant->parent;
        }

        return false;
    }

    /**
     * Identifiants de toutes les sous-unités, à tous les niveaux.
     *
     * @return int[]
     */
    public function idsDescendants(): array
    {
        $parParent = Departement::query()
            ->get(['id', 'parent_departement_id'])
            ->groupBy(fn (Departement $departement) => $departement->parent_departement_id ?? 0);

        $resultat = [];
        $file = [$this->id];
        $visites = [$this->id => true];

        while ($file !== []) {
            $courant = array_shift($file);

            foreach ($parParent->get($courant, collect()) as $enfant) {
                if (isset($visites[$enfant->id])) {
                    continue;
                }

                $visites[$enfant->id] = true;
                $resultat[] = $enfant->id;
                $file[] = $enfant->id;
            }
        }

        return $resultat;
    }

    /**
     * Vérifie que le couple (parent, type) respecte les schémas d'imbrication :
     * Direction → Division → Service, Direction → Département → Service, Direction → Service,
     * Direction → Direction, et qu'un service reste une feuille. Seule une direction
     * peut rester au sommet. Lève une ValidationException portant
     * un message clair sur le champ fautif (parent_departement_id ou type).
     *
     * @throws ValidationException
     */
    public function validerImbrication(?Departement $parent, string $type): void
    {
        $typeUnite = TypeDepartement::tryFrom($type);

        if ($typeUnite === null) {
            throw ValidationException::withMessages(['type' => "Type d'unité inconnu."]);
        }

        $parentsAutorises = $typeUnite->parentsAutorises();

        if ($parentsAutorises === [] && $parent !== null) {
            throw ValidationException::withMessages([
                'parent_departement_id' => sprintf(
                    "Une unité de type %s est toujours au sommet de l'organigramme : elle ne peut pas avoir d'unité parente.",
                    $typeUnite->libelle(),
                ),
            ]);
        }

        if ($parent === null && ! $typeUnite->peutEtreRacine()) {
            throw ValidationException::withMessages([
                'parent_departement_id' => sprintf(
                    'Une unité de type %s doit être rattachée à une unité de type %s.',
                    $typeUnite->libelle(),
                    $typeUnite->libelleParentsAutorises(),
                ),
            ]);
        }

        if ($parent !== null && ! in_array($parent->type, $parentsAutorises, true)) {
            throw ValidationException::withMessages([
                'parent_departement_id' => sprintf(
                    "Une unité de type %s ne peut être rattachée qu'à une unité de type %s : « %s » est de type %s.",
                    $typeUnite->libelle(),
                    $typeUnite->libelleParentsAutorises(),
                    $parent->nom,
                    $parent->type->libelle(),
                ),
            ]);
        }

        // Changement de type d'une unité déjà enregistrée : ses sous-unités doivent rester valides.
        if (! $this->exists) {
            return;
        }

        $enfants = $this->enfants()->get();

        if ($enfants->isEmpty()) {
            return;
        }

        if (! $typeUnite->peutAvoirDesEnfants()) {
            throw ValidationException::withMessages([
                'type' => sprintf(
                    "Une unité de type Service est toujours une feuille de l'organigramme : « %s » a %d sous-unité(s) à rattacher ailleurs d'abord.",
                    $this->nom,
                    $enfants->count(),
                ),
            ]);
        }

        foreach ($enfants as $enfant) {
            if (! in_array($typeUnite, $enfant->type->parentsAutorises(), true)) {
                throw ValidationException::withMessages([
                    'type' => sprintf(
                        'Impossible de passer « %s » en %s : sa sous-unité « %s » (%s) doit être rattachée à une unité de type %s.',
                        $this->nom,
                        $typeUnite->libelle(),
                        $enfant->nom,
                        $enfant->type->libelle(),
                        $enfant->type->libelleParentsAutorises(),
                    ),
                ]);
            }
        }
    }
}
