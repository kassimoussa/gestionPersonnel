@props(['titre', 'sousTitre' => null, 'contexte' => null, 'fermer' => 'annuler', 'largeur' => 'lg'])
@php
    $largeurs = ['sm' => '40rem', 'md' => '48rem', 'lg' => '60rem', 'xl' => '72rem'];
    $idTitre = 'modale-titre-'.substr(md5($titre), 0, 8);
@endphp
{{--
    Formulaire en plein écran piloté par le composant Livewire parent : rendu sous un @if
    (par exemple @if($formulaireOuvert)) et fermé en appelant la méthode $fermer.
    Le contenu (slot) est un <form> contenant .modale-corps puis .modale-pied.
    Pas de x-show : l'animation est en CSS, pour qu'une mise à jour Livewire ne la masque ni ne la rejoue.
--}}
<div
    x-data="{
        init() { document.documentElement.classList.add('overflow-hidden') },
        destroy() { document.documentElement.classList.remove('overflow-hidden') },
    }"
    x-trap="true"
    x-on:keydown.escape.window="$wire.{{ $fermer }}()"
    class="modale-plein-ecran fixed inset-0 z-50"
    style="--largeur-modale: {{ $largeurs[$largeur] ?? $largeurs['lg'] }};"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $idTitre }}"
    {{ $attributes }}
>
    <div class="modale-entete modale-aligne">
        <div class="pe-12">
            @if($contexte)
                <p class="text-sm font-medium text-accent">{{ $contexte }}</p>
            @endif
            <h2 id="{{ $idTitre }}" class="text-xl font-semibold tracking-tight text-ink sm:text-2xl">{{ $titre }}</h2>
            @if($sousTitre)
                <p class="mt-1 max-w-3xl text-sm text-tertiaire">{{ $sousTitre }}</p>
            @endif
        </div>
    </div>

    {{ $slot }}

    {{-- Placé en dernier pour que le focus arrive d'abord sur le premier champ du formulaire. --}}
    <button type="button" wire:click="{{ $fermer }}" class="bouton-icone absolute end-3 top-4 sm:end-6" aria-label="Fermer" title="Fermer (Échap)">
        <x-icone nom="x-mark" class="h-6 w-6" />
    </button>
</div>
