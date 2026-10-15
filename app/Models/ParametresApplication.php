<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Paramètres de l'application : une seule ligne (id = 1), modifiable par l'administrateur.
 * Identité (nom, logo, couleurs, écran de connexion), organisation (Direction générale),
 * temps de travail et congés, identité de l'entreprise et mentions imprimées sur les PDF.
 * Les réglages laissés vides retombent sur config/personnel.php.
 */
class ParametresApplication extends Model
{
    public const ID_UNIQUE = 1;

    /** Clé du conteneur où la ligne est gardée pour la durée de la requête. */
    private const CLE_CACHE = 'parametres-application.courants';

    public const NOM_DEFAUT = 'ANEFIP';

    /** Bleu ANEFIP (charte graphique) : actions principales, lien actif, focus. */
    public const COULEUR_ACCENT_DEFAUT = '#1C3C8F';

    public const COULEUR_ACCENT_CLAIRE_DEFAUT = '#3255B1';

    /** Violet ANEFIP (charte graphique) : second ton, disponible via la classe Tailwind accent2. */
    public const COULEUR_ACCENT_SECONDAIRE = '#6B3F9E';

    protected $table = 'parametres_application';

    protected $fillable = [
        'nom_application', 'logo_path', 'couleur_accent', 'couleur_accent_secondaire',
        'titre_connexion', 'texte_connexion', 'inscription_ouverte',
        'nom_legal_entreprise', 'adresse_entreprise', 'telephone_entreprise', 'email_entreprise',
        'direction_generale_id',
        'jours_weekend', 'heure_debut_travail', 'tolerance_retard_minutes', 'jours_conge_par_mois',
        'pied_page_documents', 'entete_documents', 'signataire_nom', 'signataire_fonction',
    ];

    protected $casts = [
        'inscription_ouverte' => 'boolean',
        'jours_weekend' => 'array',
        'tolerance_retard_minutes' => 'integer',
        'jours_conge_par_mois' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => app()->forgetInstance(self::CLE_CACHE));
        static::deleted(fn () => app()->forgetInstance(self::CLE_CACHE));
    }

    /**
     * La ligne unique de paramètres, créée avec les valeurs de départ si elle n'existe pas.
     * Gardée pour la durée de la requête (lue par le layout, les politiques et les calculs).
     */
    public static function courants(): self
    {
        if (app()->bound(self::CLE_CACHE)) {
            return app(self::CLE_CACHE);
        }

        $parametres = static::query()->firstOrCreate(
            ['id' => self::ID_UNIQUE],
            ['nom_application' => self::NOM_DEFAUT, 'couleur_accent' => self::COULEUR_ACCENT_DEFAUT],
        );

        app()->instance(self::CLE_CACHE, $parametres);

        return $parametres;
    }

    /**
     * Unité de type Direction dont le responsable est le directeur général.
     */
    public function directionGenerale(): BelongsTo
    {
        return $this->belongsTo(Departement::class, 'direction_generale_id');
    }

    public function couleurAccentSecondaire(): string
    {
        return self::estHexValide($this->couleur_accent_secondaire) ? strtoupper($this->couleur_accent_secondaire) : self::COULEUR_ACCENT_SECONDAIRE;
    }

    public function titreConnexion(): string
    {
        return $this->titre_connexion ?: 'Le personnel, les congés, la présence et la paie au même endroit.';
    }

    public function texteConnexion(): string
    {
        return $this->texte_connexion ?: 'Un espace unique pour les équipes RH, les managers et chaque employé.';
    }

    /**
     * Jours non ouvrés, numéros ISO (1 = lundi ... 7 = dimanche).
     *
     * @return int[]
     */
    public function joursWeekend(): array
    {
        $jours = $this->jours_weekend ?? config('personnel.jours_weekend', [5, 6]);

        return array_values(array_map('intval', $jours));
    }

    public function heureDebutTravail(): string
    {
        return $this->heure_debut_travail ?: (string) config('personnel.heure_debut_travail', '08:00');
    }

    public function toleranceRetardMinutes(): int
    {
        return $this->tolerance_retard_minutes ?? (int) config('personnel.tolerance_retard_minutes', 0);
    }

    public function joursCongeParMois(): float
    {
        return $this->jours_conge_par_mois !== null
            ? (float) $this->jours_conge_par_mois
            : (float) config('personnel.conges.jours_acquis_par_mois', 2.5);
    }

    public function couleurAccent(): string
    {
        return self::estHexValide($this->couleur_accent) ? strtoupper($this->couleur_accent) : self::COULEUR_ACCENT_DEFAUT;
    }

    /**
     * Variante claire (survol, états actifs secondaires) : la valeur de la charte pour
     * l'accent par défaut, sinon calculée en éclaircissant la couleur choisie.
     */
    public function couleurAccentClaire(): string
    {
        return $this->couleurAccent() === self::COULEUR_ACCENT_DEFAUT
            ? self::COULEUR_ACCENT_CLAIRE_DEFAUT
            : self::eclaircir($this->couleurAccent());
    }

    /**
     * Teinte très pâle de l'accent (fond du lien actif de la navigation).
     */
    public function couleurAccentDouce(): string
    {
        return self::adoucir($this->couleurAccent());
    }

    public function logoUrl(): ?string
    {
        if ($this->logo_path === null || ! Storage::disk('public')->exists($this->logo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    /**
     * Logo encodé pour les PDF (dompdf ne charge pas d'URL distante).
     */
    public function logoDataUri(): ?string
    {
        if ($this->logo_path === null || ! Storage::disk('public')->exists($this->logo_path)) {
            return null;
        }

        $extension = strtolower(pathinfo($this->logo_path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($this->logo_path));
    }

    /**
     * Nom imprimé sur les documents : le nom légal s'il est renseigné, sinon le nom de l'application.
     */
    public function nomPourDocuments(): string
    {
        return $this->nom_legal_entreprise ?: $this->nom_application;
    }

    public function aCoordonneesEntreprise(): bool
    {
        return (bool) ($this->adresse_entreprise || $this->telephone_entreprise || $this->email_entreprise);
    }

    public static function estHexValide(?string $couleur): bool
    {
        return $couleur !== null && preg_match('/^#[0-9A-Fa-f]{6}$/', $couleur) === 1;
    }

    /**
     * Éclaircit une couleur (luminosité +11 points, saturation −11 points en HSL).
     */
    public static function eclaircir(string $hex, float $deltaLuminosite = 0.11, float $deltaSaturation = -0.11): string
    {
        [$teinte, $saturation, $luminosite] = self::hexVersHsl($hex);

        return self::hslVersHex(
            $teinte,
            max(0.0, min(1.0, $saturation + $deltaSaturation)),
            max(0.0, min(1.0, $luminosite + $deltaLuminosite)),
        );
    }

    /**
     * Mélange une couleur avec du blanc (90 % de blanc par défaut).
     */
    public static function adoucir(string $hex, float $partBlanc = 0.9): string
    {
        [$r, $g, $b] = self::hexVersRgb($hex);
        $melanger = fn (int $composante): int => (int) round($composante + (255 - $composante) * $partBlanc);

        return self::rgbVersHex($melanger($r), $melanger($g), $melanger($b));
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function hexVersRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    private static function rgbVersHex(int $r, int $g, int $b): string
    {
        return sprintf('#%02X%02X%02X', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
    }

    /**
     * @return array{0: float, 1: float, 2: float} Teinte (0-360), saturation et luminosité (0-1)
     */
    private static function hexVersHsl(string $hex): array
    {
        [$r, $g, $b] = array_map(fn (int $c) => $c / 255, self::hexVersRgb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $luminosite = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $luminosite];
        }

        $delta = $max - $min;
        $saturation = $luminosite > 0.5 ? $delta / (2 - $max - $min) : $delta / ($max + $min);

        $teinte = match ($max) {
            $r => fmod(($g - $b) / $delta + ($g < $b ? 6 : 0), 6),
            $g => ($b - $r) / $delta + 2,
            default => ($r - $g) / $delta + 4,
        };

        return [$teinte * 60, $saturation, $luminosite];
    }

    private static function hslVersHex(float $teinte, float $saturation, float $luminosite): string
    {
        $c = (1 - abs(2 * $luminosite - 1)) * $saturation;
        $x = $c * (1 - abs(fmod($teinte / 60, 2) - 1));
        $m = $luminosite - $c / 2;

        [$r, $g, $b] = match (true) {
            $teinte < 60 => [$c, $x, 0],
            $teinte < 120 => [$x, $c, 0],
            $teinte < 180 => [0, $c, $x],
            $teinte < 240 => [0, $x, $c],
            $teinte < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return self::rgbVersHex((int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
    }
}
