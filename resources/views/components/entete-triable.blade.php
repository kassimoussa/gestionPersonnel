@props(['colonne', 'tri' => '', 'sens' => 'asc'])
{{-- En-tête de tableau cliquable : trie la liste, un second clic inverse le sens. --}}
@php($actif = $tri === $colonne)
<th {{ $attributes }} aria-sort="{{ $actif ? ($sens === 'asc' ? 'ascending' : 'descending') : 'none' }}">
    <button type="button" wire:click="trierPar('{{ $colonne }}')" class="entete-tri @if($actif) est-actif @endif">
        <span>{{ $slot }}</span>
        <x-icone :nom="$actif ? ($sens === 'asc' ? 'chevron-haut' : 'chevron-bas') : 'chevrons-tri'" />
    </button>
</th>
