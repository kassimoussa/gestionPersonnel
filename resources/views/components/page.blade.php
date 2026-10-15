@props(['titre', 'sousTitre' => null])
<div {{ $attributes->merge(['class' => 'space-y-6']) }}>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold tracking-tight text-ink sm:text-[1.75rem] sm:leading-9">{{ $titre }}</h1>
            @if($sousTitre)
                <p class="mt-1 max-w-3xl text-sm text-tertiaire">{{ $sousTitre }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex w-full flex-wrap items-end gap-2 sm:w-auto">{{ $actions }}</div>
        @endisset
    </div>
    {{ $slot }}
</div>
