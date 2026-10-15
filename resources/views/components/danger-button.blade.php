<button {{ $attributes->merge(['type' => 'submit', 'class' => 'bouton-danger']) }}>
    {{ $slot }}
</button>
