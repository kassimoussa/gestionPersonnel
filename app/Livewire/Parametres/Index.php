<?php

namespace App\Livewire\Parametres;

use App\Enums\Droit;
use App\Enums\TypeDepartement;
use App\Models\Departement;
use App\Models\ParametresApplication;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Paramètres de l'application (administrateur) : identité et écran de connexion, organisation,
 * temps de travail et congés, identité de l'entreprise et mentions des documents PDF.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithFileUploads;

    public const NOMS_JOURS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    // Identité et connexion
    public string $nom_application = '';

    public string $couleur_accent = ParametresApplication::COULEUR_ACCENT_DEFAUT;

    public string $couleur_accent_secondaire = ParametresApplication::COULEUR_ACCENT_SECONDAIRE;

    public ?string $titre_connexion = '';

    public ?string $texte_connexion = '';

    public bool $inscription_ouverte = false;

    /** Nouveau logo en attente d'enregistrement (upload temporaire Livewire). */
    public $logo = null;

    public bool $retirerLogo = false;

    // Organisation
    public ?int $direction_generale_id = null;

    // Temps de travail et congés
    /** @var array<int, int|string> */
    public array $jours_weekend = [];

    public string $heure_debut_travail = '';

    public string $tolerance_retard_minutes = '';

    public string $jours_conge_par_mois = '';

    // Entreprise et documents
    public ?string $nom_legal_entreprise = '';

    public ?string $adresse_entreprise = '';

    public ?string $telephone_entreprise = '';

    public ?string $email_entreprise = '';

    public ?string $entete_documents = '';

    public ?string $pied_page_documents = '';

    public ?string $signataire_nom = '';

    public ?string $signataire_fonction = '';

    public ?string $information = null;

    public function mount(): void
    {
        $this->autoriser();

        $parametres = ParametresApplication::courants();

        $this->nom_application = $parametres->nom_application;
        $this->couleur_accent = $parametres->couleurAccent();
        $this->couleur_accent_secondaire = $parametres->couleurAccentSecondaire();
        $this->titre_connexion = $parametres->titre_connexion ?? '';
        $this->texte_connexion = $parametres->texte_connexion ?? '';
        $this->inscription_ouverte = $parametres->inscription_ouverte;
        $this->direction_generale_id = $parametres->direction_generale_id;
        $this->jours_weekend = array_map('strval', $parametres->joursWeekend());
        $this->heure_debut_travail = $parametres->heureDebutTravail();
        $this->tolerance_retard_minutes = (string) $parametres->toleranceRetardMinutes();
        $this->jours_conge_par_mois = (string) $parametres->joursCongeParMois();

        foreach (['nom_legal_entreprise', 'adresse_entreprise', 'telephone_entreprise', 'email_entreprise', 'entete_documents', 'pied_page_documents', 'signataire_nom', 'signataire_fonction'] as $champ) {
            $this->{$champ} = $parametres->{$champ} ?? '';
        }
    }

    public function render()
    {
        $couleurValide = ParametresApplication::estHexValide($this->couleur_accent);
        $accent = $couleurValide ? strtoupper($this->couleur_accent) : ParametresApplication::COULEUR_ACCENT_DEFAUT;

        return view('livewire.parametres.index', [
            'parametres' => ParametresApplication::courants(),
            'directions' => Departement::query()->where('type', TypeDepartement::Direction)->with('responsable')->orderBy('nom')->get(),
            'nomsJours' => self::NOMS_JOURS,
            'apercuCouleur' => $accent,
            'apercuCouleurClaire' => $accent === ParametresApplication::COULEUR_ACCENT_DEFAUT ? ParametresApplication::COULEUR_ACCENT_CLAIRE_DEFAUT : ParametresApplication::eclaircir($accent),
            'apercuCouleurDouce' => ParametresApplication::adoucir($accent),
            'apercuCouleurSecondaire' => ParametresApplication::estHexValide($this->couleur_accent_secondaire) ? strtoupper($this->couleur_accent_secondaire) : ParametresApplication::COULEUR_ACCENT_SECONDAIRE,
            // Aperçu seulement pour une image : un autre type de fichier est refusé à l'enregistrement.
            'apercuLogo' => $this->logo?->isPreviewable() ? $this->logo->temporaryUrl() : null,
        ]);
    }

    public function enregistrer(): void
    {
        $this->autoriser();

        foreach (['titre_connexion', 'texte_connexion', 'nom_legal_entreprise', 'adresse_entreprise', 'telephone_entreprise', 'email_entreprise', 'entete_documents', 'pied_page_documents', 'signataire_nom', 'signataire_fonction'] as $champ) {
            $this->{$champ} = trim((string) $this->{$champ}) ?: null;
        }
        $this->direction_generale_id = $this->direction_generale_id ?: null;

        $donnees = $this->validate([
            'nom_application' => ['required', 'string', 'max:80'],
            'couleur_accent' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'couleur_accent_secondaire' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'titre_connexion' => ['nullable', 'string', 'max:160'],
            'texte_connexion' => ['nullable', 'string', 'max:500'],
            'inscription_ouverte' => ['boolean'],
            'logo' => ['nullable', 'image', 'max:1024'],
            'direction_generale_id' => ['nullable', Rule::exists('departements', 'id')->where('type', TypeDepartement::Direction->value)],
            'jours_weekend' => ['array', 'max:6'],
            'jours_weekend.*' => ['integer', 'between:1,7'],
            'heure_debut_travail' => ['required', 'date_format:H:i'],
            'tolerance_retard_minutes' => ['required', 'integer', 'between:0,240'],
            'jours_conge_par_mois' => ['required', 'numeric', 'between:0,10'],
            'nom_legal_entreprise' => ['nullable', 'string', 'max:150'],
            'adresse_entreprise' => ['nullable', 'string', 'max:500'],
            'telephone_entreprise' => ['nullable', 'string', 'max:50'],
            'email_entreprise' => ['nullable', 'email', 'max:150'],
            'entete_documents' => ['nullable', 'string', 'max:500'],
            'pied_page_documents' => ['nullable', 'string', 'max:500'],
            'signataire_nom' => ['nullable', 'string', 'max:150'],
            'signataire_fonction' => ['nullable', 'string', 'max:150'],
        ], [
            'couleur_accent.regex' => 'La couleur doit être au format hexadécimal, par exemple #1C3C8F.',
            'couleur_accent_secondaire.regex' => 'La couleur doit être au format hexadécimal, par exemple #6B3F9E.',
            'jours_weekend.max' => 'Il doit rester au moins un jour ouvré dans la semaine.',
            'direction_generale_id.exists' => 'La Direction générale doit être une unité de type Direction.',
        ]);

        if (count(array_unique($donnees['jours_weekend'])) >= 7) {
            throw ValidationException::withMessages(['jours_weekend' => 'Il doit rester au moins un jour ouvré dans la semaine.']);
        }

        $parametres = ParametresApplication::courants();

        $donnees['couleur_accent'] = strtoupper($donnees['couleur_accent']);
        $donnees['couleur_accent_secondaire'] = strtoupper($donnees['couleur_accent_secondaire']);
        $donnees['jours_weekend'] = collect($donnees['jours_weekend'])->map(fn ($jour) => (int) $jour)->unique()->sort()->values()->all();

        if ($this->retirerLogo || $this->logo !== null) {
            if ($parametres->logo_path !== null) {
                Storage::disk('public')->delete($parametres->logo_path);
            }

            $donnees['logo_path'] = $this->logo?->store('logos', 'public');
        }

        unset($donnees['logo']);

        $parametres->update($donnees);

        $this->reset(['logo', 'retirerLogo']);
        $this->jours_weekend = array_map('strval', $donnees['jours_weekend']);
        $this->information = 'Paramètres enregistrés.';
    }

    public function annulerLogo(): void
    {
        $this->reset('logo');
    }

    private function autoriser(): void
    {
        abort_unless(auth()->user()->aLeDroit(Droit::ParametresGerer), 403);
    }
}
