@props(['titre', 'description' => null])
<section {{ $attributes->merge(['class' => 'section-formulaire']) }}>
    <div>
        <h3 class="section-formulaire-titre">{{ $titre }}</h3>
        @if($description)
            <p class="section-formulaire-description">{{ $description }}</p>
        @endif
    </div>
    <div class="section-formulaire-champs">
        {{ $slot }}
    </div>
</section>
