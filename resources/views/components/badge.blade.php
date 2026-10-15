@props(['variante' => 'neutre', 'point' => true])
{{-- Pastille : "point" pour un statut (brouillon, approuvé, présent...), sans point pour une catégorie (type, source). --}}
<span {{ $attributes->merge(['class' => 'badge badge-'.$variante.($point ? ' badge-point' : '')]) }}>{{ $slot }}</span>
