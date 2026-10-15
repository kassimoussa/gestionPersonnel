<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h1 class="text-2xl font-semibold tracking-tight text-ink">Connexion</h1>
    <p class="mt-2 text-sm text-tertiaire">Accédez à votre espace avec votre adresse e-mail et votre mot de passe.</p>

    <x-auth-session-status class="mt-6" :status="session('status')" />

    <form wire:submit="login" class="mt-8 space-y-5">
        <div>
            <x-input-label for="email" value="Adresse e-mail" />
            <x-text-input wire:model="form.email" id="email" type="email" name="email" placeholder="prenom.nom@anefip.dj" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" />
        </div>

        <div>
            <x-input-label for="password" value="Mot de passe" />
            <x-text-input wire:model="form.password" id="password" type="password" name="password" placeholder="••••••••" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('form.password')" />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label for="remember" class="inline-flex items-center gap-2 text-sm font-medium text-secondaire">
                <input wire:model="form.remember" id="remember" type="checkbox" class="case" name="remember">
                Se souvenir de moi
            </label>

            @if (Route::has('password.request'))
                <a class="bouton-lien-principal" href="{{ route('password.request') }}" wire:navigate>Mot de passe oublié ?</a>
            @endif
        </div>

        <x-primary-button class="w-full py-2.5" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Se connecter</span>
            <span wire:loading wire:target="login">Connexion en cours…</span>
        </x-primary-button>
    </form>
</div>
