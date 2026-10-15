<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public function mount(): void
    {
        // Création de compte en libre accès : désactivée par défaut (Paramètres de l'application).
        abort_unless(\App\Models\ParametresApplication::courants()->inscription_ouverte, 404);
    }

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h1 class="text-2xl font-semibold tracking-tight text-ink">Créer un compte</h1>
    <p class="mt-1 text-sm text-secondaire">Le compte pourra ensuite être lié à une fiche employé par l'administration.</p>

    <form wire:submit="register" class="mt-6 space-y-4">
        <div>
            <x-input-label for="name" value="Nom" />
            <x-text-input wire:model="name" id="name" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="Adresse e-mail" />
            <x-text-input wire:model="email" id="email" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <x-input-label for="password" value="Mot de passe" />
            <x-text-input wire:model="password" id="password" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirmer le mot de passe" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
            <a class="bouton-lien" href="{{ route('login') }}" wire:navigate>Déjà inscrit ?</a>
            <x-primary-button class="py-2.5">S'inscrire</x-primary-button>
        </div>
    </form>
</div>
