@props(['value'])

<label {{ $attributes->merge(['class' => 'etiquette']) }}>
    {{ $value ?? $slot }}
</label>
