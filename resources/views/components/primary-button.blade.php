<button {{ $attributes->merge(['type' => 'submit', 'class' => 'bouton-principal']) }}>
    {{ $slot }}
</button>
