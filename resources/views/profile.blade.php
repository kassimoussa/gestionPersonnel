<x-app-layout>
    <x-page titre="Profil" sous-titre="Informations de votre compte et mot de passe.">
        <div class="space-y-6">
            @if(auth()->user()->employe)
                <div class="panneau max-w-xl">
                    <div class="flex flex-wrap items-center gap-4">
                        <x-avatar :nom="auth()->user()->employe->nomComplet()" :photo="auth()->user()->employe->photoUrl()" taille="lg" />
                        <div class="min-w-0 flex-1">
                            <p class="text-base font-semibold text-ink">{{ auth()->user()->employe->nomComplet() }}</p>
                            <p class="text-sm text-tertiaire">{{ collect([auth()->user()->employe->poste, auth()->user()->employe->departement?->nom])->filter()->join(' · ') ?: 'Fiche du personnel' }}</p>
                        </div>
                        <a href="{{ route('employes.fiche', auth()->user()->employe) }}" wire:navigate class="bouton-secondaire">Voir ma fiche</a>
                    </div>
                </div>
            @endif

            <div class="panneau max-w-xl">
                <livewire:profile.update-profile-information-form />
            </div>

            <div class="panneau max-w-xl">
                <livewire:profile.update-password-form />
            </div>

            <div class="panneau max-w-xl">
                <livewire:profile.delete-user-form />
            </div>
        </div>
    </x-page>
</x-app-layout>
