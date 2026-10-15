@props(['titre', 'description' => null, 'id'])
{{-- Case à cocher présentée en interrupteur : les attributs (wire:model...) vont sur la case. --}}
<label for="{{ $id }}" class="flex cursor-pointer items-start justify-between gap-6">
    <span class="min-w-0">
        <span class="block text-sm font-semibold text-ink">{{ $titre }}</span>
        @if($description)
            <span class="mt-0.5 block text-sm text-tertiaire">{{ $description }}</span>
        @endif
    </span>
    <span class="relative inline-flex shrink-0">
        <input id="{{ $id }}" type="checkbox" role="switch" {{ $attributes->merge(['class' => 'peer sr-only']) }}>
        <span class="interrupteur-piste" aria-hidden="true"></span>
    </span>
</label>
