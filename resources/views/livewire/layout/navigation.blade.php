<?php

use App\Livewire\Actions\Logout;
use App\Models\ParametresApplication;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@use('App\Enums\Droit')
@php($parametres = ParametresApplication::courants())
@php($utilisateur = auth()->user())
<nav class="flex h-full flex-col border-e border-bordure bg-white" aria-label="Navigation principale">
    <div class="flex h-16 shrink-0 items-center px-5">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex min-w-0 items-center gap-3 rounded-lg">
            <x-application-logo />
            <span class="truncate text-base font-semibold text-ink">{{ $parametres->nom_application }}</span>
        </a>
    </div>

    <div class="flex-1 space-y-0.5 overflow-y-auto px-3 pb-4">
        <x-lien-sidebar :href="route('dashboard')" :actif="request()->routeIs('dashboard')" icone="home">Tableau de bord</x-lien-sidebar>
        <x-lien-sidebar :href="route('organigramme.index')" :actif="request()->routeIs('organigramme.*')" icone="share">Organigramme</x-lien-sidebar>

        <p class="titre-sidebar">Personnel</p>
        @if($utilisateur->peutConsulterPersonnel())
            <x-lien-sidebar :href="route('employes.index')" :actif="request()->routeIs('employes.*')" icone="users">Employés</x-lien-sidebar>
        @endif
        <x-lien-sidebar :href="route('departements.index')" :actif="request()->routeIs('departements.*')" icone="building-office-2">Départements</x-lien-sidebar>
        @if($utilisateur->peutGererContrats())
            <x-lien-sidebar :href="route('contrats.index')" :actif="request()->routeIs('contrats.*')" icone="document-text">Contrats</x-lien-sidebar>
        @endif

        @if($utilisateur->peutSaisirCongePourAutrui())
            <p class="titre-sidebar">Congés</p>
            <x-lien-sidebar :href="route('conges.personnel')" :actif="request()->routeIs('conges.personnel')" icone="calendar-days">Congés du personnel</x-lien-sidebar>
        @endif

        @if($utilisateur->peutConsulterPresence() || $utilisateur->peutSaisirPresence() || $utilisateur->peutImporterPointages())
            <p class="titre-sidebar">Présence</p>
            @if($utilisateur->peutConsulterPresence())
                <x-lien-sidebar :href="route('presence.rapport')" :actif="request()->routeIs('presence.rapport')" icone="chart-bar">Rapport de présence</x-lien-sidebar>
            @endif
            @if($utilisateur->peutSaisirPresence())
                <x-lien-sidebar :href="route('presence.saisie')" :actif="request()->routeIs('presence.saisie')" icone="clock">Saisie manuelle</x-lien-sidebar>
                <x-lien-sidebar :href="route('presence.justificatifs')" :actif="request()->routeIs('presence.justificatifs')" icone="document-text">Justificatifs d'absence</x-lien-sidebar>
            @endif
            @if($utilisateur->peutImporterPointages())
                <x-lien-sidebar :href="route('presence.import')" :actif="request()->routeIs('presence.import')" icone="arrow-up-tray">Import pointeuse</x-lien-sidebar>
            @endif
        @endif

        @if($utilisateur->peutPreparerPaie())
            <p class="titre-sidebar">Paie</p>
            <x-lien-sidebar :href="route('paie.generation')" :actif="request()->routeIs('paie.generation')" icone="pencil-square">Génération de la paie</x-lien-sidebar>
            <x-lien-sidebar :href="route('paie.historique')" :actif="request()->routeIs('paie.historique')" icone="document-duplicate">Historique de la paie</x-lien-sidebar>
        @endif

        @if($utilisateur->aLeDroit(Droit::ComptesGerer) || $utilisateur->aLeDroit(Droit::ParametresGerer))
            <p class="titre-sidebar">Administration</p>
            @if($utilisateur->aLeDroit(Droit::ComptesGerer))
                <x-lien-sidebar :href="route('comptes.index')" :actif="request()->routeIs('comptes.*')" icone="user-circle">Comptes utilisateurs</x-lien-sidebar>
            @endif
            @if($utilisateur->aLeDroit(Droit::ParametresGerer))
                <x-lien-sidebar :href="route('parametres.index')" :actif="request()->routeIs('parametres.index')" icone="cog-6-tooth">Paramètres</x-lien-sidebar>
                <x-lien-sidebar :href="route('parametres.jours-feries')" :actif="request()->routeIs('parametres.jours-feries')" icone="calendar-days">Jours fériés</x-lien-sidebar>
            @endif
        @endif
    </div>

    <div class="shrink-0 border-t border-bordure p-3">
        <div class="flex items-center gap-3 rounded-lg px-2 py-2">
            <x-avatar :nom="$utilisateur->name" :photo="$utilisateur->employe?->photoUrl()" />
            <a href="{{ route('profile') }}" wire:navigate class="min-w-0 flex-1 rounded" title="Mon profil">
                <p class="truncate text-sm font-semibold text-ink" x-data="{{ json_encode(['name' => $utilisateur->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></p>
                <p class="truncate text-xs text-tertiaire">{{ $utilisateur->email }}</p>
            </a>
            <button type="button" wire:click="logout" class="bouton-icone" aria-label="Déconnexion" title="Déconnexion">
                <x-icone nom="arrow-right-start-on-rectangle" />
            </button>
        </div>
    </div>
</nav>
