@props(['href', 'actif' => false, 'icone' => null])
<a href="{{ $href }}" wire:navigate @if($actif) aria-current="page" @endif {{ $attributes->merge(['class' => 'lien-sidebar'.($actif ? ' lien-sidebar-actif' : '')]) }}>
    @if($icone)
        <x-icone :nom="$icone" />
    @endif
    <span class="truncate">{{ $slot }}</span>
</a>
