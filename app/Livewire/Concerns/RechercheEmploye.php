<?php

namespace App\Livewire\Concerns;

use App\Models\Employe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Choix d'un employé par recherche libre : nom, prénom, matricule, identifiant
 * de la pointeuse, poste ou téléphone. Le composant qui l'utilise porte la
 * propriété $employe_id ; le périmètre proposé reste le sien.
 */
trait RechercheEmploye
{
    /** Texte saisi dans le champ de recherche. */
    public string $rechercheEmploye = '';

    /**
     * Employés dans lesquels chercher : à définir par le composant, qui décide
     * de son périmètre (son équipe, tout l'effectif, les fiches sans compte...).
     */
    abstract protected function employesRecherchables(): Builder;

    public function choisirEmploye(int $id): void
    {
        $employe = $this->employesRecherchables()->whereKey($id)->first();

        if ($employe === null) {
            return;
        }

        $this->employe_id = $employe->id;
        $this->rechercheEmploye = $employe->nomComplet();
        $this->resetValidation('employe_id');

        $this->apresChoixEmploye($employe);
    }

    public function effacerEmploye(): void
    {
        $this->employe_id = null;
        $this->rechercheEmploye = '';
    }

    /**
     * Point d'accroche pour les composants qui pré-remplissent d'autres champs.
     */
    protected function apresChoixEmploye(Employe $employe): void {}

    /**
     * Employé retenu, ou null tant que rien n'est choisi.
     */
    protected function employeChoisi(): ?Employe
    {
        return $this->employe_id ? $this->employesRecherchables()->whereKey($this->employe_id)->first() : null;
    }

    /**
     * Suggestions correspondant à la saisie : chaque mot doit se retrouver dans
     * l'un des champs, ce qui permet de taper « abdirazak hassan » ou « 1067 ».
     *
     * @return Collection<int, Employe>
     */
    protected function employesTrouves(int $limite = 8): Collection
    {
        if ($this->employe_id !== null) {
            return new Collection;
        }

        $saisie = trim($this->rechercheEmploye);

        if ($saisie === '') {
            return new Collection;
        }

        $requete = $this->employesRecherchables();

        foreach (preg_split('/\s+/', $saisie) ?: [] as $mot) {
            $requete->where(function (Builder $q) use ($mot): void {
                foreach (['nom', 'prenom', 'matricule', 'matricule_pointeuse', 'poste', 'telephone'] as $colonne) {
                    $q->orWhere($colonne, 'like', "%{$mot}%");
                }
            });
        }

        return $requete->orderBy('nom')->orderBy('prenom')->limit($limite)->get();
    }
}
