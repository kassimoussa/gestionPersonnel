<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Droit;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'employe_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Seul rôle stocké : l'administrateur de l'application, qui possède tous les
     * droits et les attribue aux autres comptes (voir App\Enums\Droit).
     * "DG" et "manager" ne s'attribuent pas : ils découlent de l'organigramme
     * (responsable de la Direction générale, responsable d'une unité).
     */
    public const ROLE_ADMIN = 'admin';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Fiche employé (RH) correspondant à ce compte, si elle est renseignée.
     */
    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function estAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    /**
     * L'administrateur a tous les droits ; les autres comptes ont ceux que
     * l'administrateur leur a attribués dans « Comptes utilisateurs ».
     */
    public function aLeDroit(Droit $droit): bool
    {
        return $this->estAdmin() || $this->checkPermissionTo($droit->value, 'web');
    }

    /**
     * Droits attribués individuellement, pour l'écran des comptes.
     *
     * @return string[]
     */
    public function droitsAttribues(): array
    {
        return $this->permissions->pluck('name')->all();
    }

    /**
     * Manager : responsable d'au moins une unité organisationnelle.
     */
    public function estManager(): bool
    {
        return $this->employe_id !== null
            && Departement::query()->where('responsable_id', $this->employe_id)->exists();
    }

    /**
     * Directeur général : le compte est lié à la fiche du responsable de l'unité désignée
     * comme Direction générale dans les paramètres. Changer ce responsable change le DG.
     */
    public function estDg(): bool
    {
        if ($this->employe_id === null) {
            return false;
        }

        $direction = ParametresApplication::courants()->direction_generale_id;

        return $direction !== null
            && Departement::query()->whereKey($direction)->where('responsable_id', $this->employe_id)->exists();
    }

    /**
     * Gestion des dossiers du personnel.
     */
    public function peutGererPersonnel(): bool
    {
        return $this->aLeDroit(Droit::PersonnelGerer) || $this->estDg();
    }

    /**
     * Consultation des dossiers : les habilités sur tout l'effectif,
     * les managers sur leur équipe.
     */
    public function peutConsulterPersonnel(): bool
    {
        return $this->peutGererPersonnel()
            || $this->aLeDroit(Droit::PersonnelConsulter)
            || $this->estManager();
    }

    /**
     * Contrats et salaires, qui alimentent le calcul de la paie.
     */
    public function peutGererContrats(): bool
    {
        return $this->aLeDroit(Droit::ContratsGerer) || $this->estDg();
    }

    /**
     * Données sensibles des employés : pièce d'identité, CNSS, compte bancaire.
     */
    public function peutVoirDonneesSensibles(): bool
    {
        return $this->aLeDroit(Droit::PersonnelDonneesSensibles) || $this->estDg();
    }

    /**
     * Préparation de la paie : génération des brouillons et ajustements.
     */
    public function peutPreparerPaie(): bool
    {
        return $this->aLeDroit(Droit::PaiePreparer) || $this->estDg();
    }

    /**
     * Validation de la paie : elle engage les montants, d'où sa séparation
     * d'avec la préparation.
     */
    public function peutValiderPaie(): bool
    {
        return $this->aLeDroit(Droit::PaieValider) || $this->estDg();
    }

    /**
     * Rapport de présence, sur tout l'effectif ou sur son équipe.
     */
    public function peutConsulterPresence(): bool
    {
        return $this->aLeDroit(Droit::PresenceConsulter)
            || $this->estDg()
            || $this->departementsSupervises()->exists();
    }

    /**
     * Saisie et correction des pointages.
     */
    public function peutSaisirPresence(): bool
    {
        return $this->aLeDroit(Droit::PresenceSaisir)
            || $this->estDg()
            || $this->departementsSupervises()->exists();
    }

    /**
     * Import du fichier de la pointeuse.
     */
    public function peutImporterPointages(): bool
    {
        return $this->aLeDroit(Droit::PresenceImporter) || $this->estDg();
    }

    /**
     * Déposer une demande de congé au nom d'un employé : tant que tout le personnel
     * n'a pas de compte, les habilités et les managers saisissent les demandes
     * reçues sur papier pour leur périmètre.
     */
    public function peutSaisirCongePourAutrui(): bool
    {
        return $this->aLeDroit(Droit::CongesSaisirPourAutrui)
            || $this->estDg()
            || $this->departementsSupervises()->exists();
    }

    /**
     * Établir l'attestation de congé : le service du personnel la prépare
     * (« pour attribution »), le directeur général la signe.
     */
    public function peutEtablirAttestationConge(): bool
    {
        return $this->aLeDroit(Droit::CongesAttestation) || $this->estDg();
    }

    /**
     * Dernière étape du circuit des congés.
     */
    public function peutValiderCongesEnDernierRessort(): bool
    {
        return $this->aLeDroit(Droit::CongesValider) || $this->estDg();
    }

    /**
     * File de validation des congés : le manager pour son unité,
     * la direction pour la dernière étape.
     */
    public function peutValiderConges(): bool
    {
        return $this->peutValiderCongesEnDernierRessort() || $this->estManager();
    }

    /**
     * Périmètre de consultation : tout l'effectif pour la direction et les comptes
     * habilités à consulter les dossiers, sinon les unités dont l'utilisateur est
     * responsable.
     */
    public function departementsSupervises(): Builder
    {
        $query = Departement::query();

        if ($this->aPerimetreGlobal()) {
            return $query;
        }

        if ($this->employe_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('responsable_id', $this->employe_id);
    }

    /**
     * Employés dont l'utilisateur peut consulter le dossier, la présence et les congés.
     */
    public function employesSupervises(): Builder
    {
        $query = Employe::query();

        if ($this->aPerimetreGlobal()) {
            return $query;
        }

        if ($this->employe_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('departement', fn (Builder $q) => $q->where('responsable_id', $this->employe_id));
    }

    /**
     * Voit tout l'effectif, et pas seulement son équipe.
     */
    private function aPerimetreGlobal(): bool
    {
        return $this->estDg()
            || $this->aLeDroit(Droit::PersonnelConsulter)
            || $this->aLeDroit(Droit::PersonnelGerer);
    }
}
