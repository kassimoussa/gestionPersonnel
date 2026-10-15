@props(['taille' => 'md'])
@php($parametres = \App\Models\ParametresApplication::courants())
@php($dimensions = match ($taille) { 'xl' => 'h-20 w-20', 'lg' => 'h-12 w-12', 'sm' => 'h-8 w-8', default => 'h-9 w-9' })
{{-- Logo déposé dans Paramètres, sinon la marque ANEFIP redessinée en SVG (public/images/logo-anefip.svg). --}}
<img src="{{ $parametres->logoUrl() ?? asset('images/logo-anefip.svg') }}" alt="Logo {{ $parametres->nom_application }}" {{ $attributes->merge(['class' => $dimensions.' shrink-0 object-contain']) }}>
