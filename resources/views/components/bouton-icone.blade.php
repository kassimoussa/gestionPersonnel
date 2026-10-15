@props(['icone', 'libelle', 'href' => null, 'variante' => 'neutre'])
{{--
    Action de ligne réduite à son icône. Le libellé reste porté par title et
    aria-label : il s'affiche au survol et il est lu par les lecteurs d'écran.
--}}
@php($classes = 'bouton-icone '.match ($variante) {
    'danger' => 'bouton-icone-danger',
    'accent' => 'bouton-icone-accent',
    'succes' => 'bouton-icone-succes',
    default => '',
})
@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }} title="{{ $libelle }}" aria-label="{{ $libelle }}">
        <x-icone :nom="$icone" />
    </a>
@else
    <button type="button" {{ $attributes->merge(['class' => $classes]) }} title="{{ $libelle }}" aria-label="{{ $libelle }}">
        <x-icone :nom="$icone" />
    </button>
@endif
