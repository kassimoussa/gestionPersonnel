@props(['nom', 'photo' => null, 'taille' => 'md'])
@php
    $dimensions = match ($taille) {
        'sm' => 'h-8 w-8 text-xs',
        'lg' => 'h-11 w-11 text-sm',
        'xl' => 'h-20 w-20 text-xl',
        default => 'h-9 w-9 text-xs',
    };
@endphp
@if($photo)
    <img src="{{ $photo }}" alt="" {{ $attributes->merge(['class' => $dimensions.' shrink-0 rounded-full object-cover ring-1 ring-black/5']) }}>
@else
    @php
        $mots = preg_split('/\s+/u', trim((string) $nom), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
        $initiales = mb_strtoupper(mb_substr($mots[0], 0, 1).(count($mots) > 1 ? mb_substr(end($mots), 0, 1) : ''));
        // Teinte stable par personne : fond pâle et texte foncé, contraste suffisant.
        $palettes = [
            ['#E8ECF4', '#1C3C8F'], ['#F4F0FA', '#6B3F9E'], ['#ECFDF3', '#067647'],
            ['#FFFAEB', '#B54708'], ['#F0FDF9', '#107569'], ['#FDF2FA', '#C11574'],
        ];
        [$fond, $texte] = $palettes[crc32((string) $nom) % count($palettes)];
    @endphp
    <span aria-hidden="true" {{ $attributes->merge(['class' => $dimensions.' inline-flex shrink-0 items-center justify-center rounded-full font-semibold']) }} style="background-color: {{ $fond }}; color: {{ $texte }};">{{ $initiales }}</span>
@endif
