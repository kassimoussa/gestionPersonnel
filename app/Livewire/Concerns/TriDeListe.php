<?php

namespace App\Livewire\Concerns;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tri des colonnes d'une liste : un clic sur l'en-tête trie, un second inverse
 * le sens. Le composant déclare les colonnes autorisées et leur expression SQL,
 * de sorte qu'aucune valeur venue du navigateur n'atteigne la requête.
 */
trait TriDeListe
{
    /** Colonne actuellement triée, parmi les clés de colonnesTriables(). */
    public string $tri = '';

    /** Sens du tri : asc ou desc. */
    public string $sens = 'asc';

    /**
     * Colonnes triables : clé utilisée dans la vue, valeur = colonne, liste de
     * colonnes ou sous-requête à passer à orderBy().
     *
     * @return array<string, string|Expression|Builder|array<int, string|Expression|Builder>>
     */
    abstract protected function colonnesTriables(): array;

    /**
     * Livewire appelle ce point d'entrée à chaque requête : la première colonne
     * déclarée sert de tri par défaut tant que l'utilisateur n'a rien choisi.
     */
    public function bootTriDeListe(): void
    {
        if ($this->tri === '') {
            $this->tri = $this->triParDefaut();
            $this->sens = $this->sensParDefaut($this->tri);
        }
    }

    protected function triParDefaut(): string
    {
        return (string) array_key_first($this->colonnesTriables());
    }

    public function trierPar(string $colonne): void
    {
        if (! array_key_exists($colonne, $this->colonnesTriables())) {
            return;
        }

        if ($this->tri === $colonne) {
            $this->sens = $this->sens === 'asc' ? 'desc' : 'asc';
        } else {
            $this->tri = $colonne;
            $this->sens = $this->sensParDefaut($colonne);
        }

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /**
     * Ajoute le tri courant à la requête. Sans colonne connue, la requête garde
     * l'ordre défini par le composant.
     */
    protected function appliquerTri(Builder $requete): Builder
    {
        $definition = $this->colonnesTriables()[$this->tri] ?? null;

        if ($definition === null) {
            return $requete;
        }

        $sens = $this->sens === 'desc' ? 'desc' : 'asc';

        foreach (is_array($definition) ? $definition : [$definition] as $expression) {
            $requete->orderBy($expression, $sens);
        }

        return $requete;
    }

    /**
     * Les dates et les montants se lisent d'abord du plus récent ou du plus grand ;
     * le texte, lui, part de A. Un composant peut redéfinir ce choix.
     */
    protected function sensParDefaut(string $colonne): string
    {
        return in_array($colonne, $this->colonnesDecroissantesDAbord(), true) ? 'desc' : 'asc';
    }

    /**
     * @return string[]
     */
    protected function colonnesDecroissantesDAbord(): array
    {
        return [];
    }
}
