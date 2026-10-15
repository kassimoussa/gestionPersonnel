{{-- Couleurs choisies dans les paramètres : surchargent les valeurs par défaut de app.css. --}}
<style>
    :root {
        --color-accent: {{ $parametres->couleurAccent() }};
        --color-accent-light: {{ $parametres->couleurAccentClaire() }};
        --color-accent-soft: {{ $parametres->couleurAccentDouce() }};
        --color-accent-2: {{ $parametres->couleurAccentSecondaire() }};
    }
</style>
