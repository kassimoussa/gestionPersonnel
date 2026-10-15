@use('App\Models\FichePaie')
@php($prenom = $employe?->prenom ?? $utilisateur->name)
<x-page :titre="'Bonjour, '.$prenom" :sous-titre="ucfirst(now()->locale('fr')->isoFormat('dddd D MMMM YYYY')).'. Voici l\'essentiel du jour.'">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="indicateur">
            <div class="flex items-start justify-between gap-3">
                <p class="indicateur-libelle">Effectif actif</p>
                <span class="icone-mise-en-avant"><x-icone nom="users" /></span>
            </div>
            <p class="indicateur-valeur">{{ $effectifActif }}</p>
            <p class="mt-1 text-sm text-tertiaire">réparti dans {{ $nombreUnites }} unité(s)</p>
        </div>

        <div class="indicateur">
            <div class="flex items-start justify-between gap-3">
                <p class="indicateur-libelle">Congés en cours de circuit</p>
                <span class="icone-mise-en-avant"><x-icone nom="check-badge" /></span>
            </div>
            <p class="indicateur-valeur">{{ $nombreATraiter }}</p>
            <p class="mt-1 text-sm text-tertiaire">
                @if($nombreATraiter > 0)
                    <a href="{{ route('conges.personnel') }}" wire:navigate class="bouton-lien-principal">Ouvrir les congés du personnel</a>
                @else
                    aucune demande en cours
                @endif
            </p>
        </div>


        @if($presence)
            <div class="indicateur">
                <div class="flex items-start justify-between gap-3">
                    <p class="indicateur-libelle">Présents aujourd'hui</p>
                    <span class="icone-mise-en-avant"><x-icone nom="clock" /></span>
                </div>
                <p class="indicateur-valeur">{{ $presence['presents'] }}<span class="text-base font-medium text-tertiaire"> / {{ $presence['effectif'] }}</span></p>
                <p class="mt-1 text-sm text-tertiaire">{{ $presence['jour_ouvre'] ? 'pointages enregistrés dans votre périmètre' : 'jour non ouvré' }}</p>
            </div>
        @endif

        @if($paie)
            <div class="indicateur">
                <div class="flex items-start justify-between gap-3">
                    <p class="indicateur-libelle">Masse salariale · {{ $paie['periode'] }}</p>
                    <span class="icone-mise-en-avant"><x-icone nom="banknotes" /></span>
                </div>
                <p class="indicateur-valeur">{{ FichePaie::montant($paie['brut']) }}</p>
                <p class="mt-1 text-sm text-tertiaire">{{ $paie['nombre'] }} fiche(s), dont {{ $paie['brouillons'] }} brouillon(s)</p>
            </div>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="tableau-conteneur lg:col-span-2">
            <div class="flex items-center justify-between gap-4 border-b border-bordure px-5 py-4">
                <div>
                    <h2 class="panneau-titre">Demandes de congé en cours</h2>
                    <p class="text-sm text-tertiaire">Les plus anciennes en premier.</p>
                </div>
                <a href="{{ route('conges.personnel') }}" wire:navigate class="bouton-secondaire">Tout voir</a>
            </div>
            <ul class="divide-y divide-bordure">
                @forelse($demandesATraiter as $conge)
                    <li wire:key="a-traiter-{{ $conge->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3.5">
                        <div class="identite min-w-[12rem] flex-1">
                            <x-avatar :nom="$conge->employe?->nomComplet() ?? '?'" :photo="$conge->employe?->photoUrl()" />
                            <div class="min-w-0">
                                <span class="identite-nom">{{ $conge->employe?->nomComplet() ?? '—' }}</span>
                                <span class="block truncate text-xs text-tertiaire">{{ $conge->type->libelle() }} · du {{ $conge->date_debut->format('d/m') }} au {{ $conge->date_fin->format('d/m/Y') }}</span>
                            </div>
                        </div>
                        <x-badge :variante="$conge->statut->varianteBadge()">{{ $conge->statut->libelle() }}</x-badge>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center">
                        <x-icone nom="check-badge" class="mx-auto mb-3 block h-8 w-8 text-bordure-forte" />
                        <p class="text-sm font-medium text-ink">Rien à valider</p>
                        <p class="text-sm text-tertiaire">Les demandes de votre équipe apparaîtront ici.</p>
                    </li>
                @endforelse
            </ul>
        </section>

        <section class="tableau-conteneur">
            <div class="flex items-center justify-between gap-4 border-b border-bordure px-5 py-4">
                <h2 class="panneau-titre">Derniers congés actés</h2>
            </div>
            <ul class="divide-y divide-bordure">
                @forelse($dernierActes as $conge)
                    <li wire:key="acte-{{ $conge->id }}" class="flex items-center justify-between gap-3 px-5 py-3.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-ink">{{ $conge->employe?->nomComplet() ?? '—' }}</p>
                            <p class="text-xs text-tertiaire">{{ $conge->type->libelle() }} · du {{ $conge->date_debut->format('d/m') }} au {{ $conge->date_fin->format('d/m/Y') }}</p>
                        </div>
                        <x-badge variante="succes" :point="false">{{ number_format($conge->nombre_jours, 1, ',', ' ') }} j</x-badge>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-sm text-tertiaire">Aucun congé acté pour l'instant.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <section>
        <h2 class="panneau-titre">Accès rapides</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @php($raccourcis = array_values(array_filter([
                ['route' => 'organigramme.index', 'icone' => 'share', 'titre' => 'Organigramme', 'texte' => 'Directions et services'],
                $utilisateur->peutSaisirCongePourAutrui() ? ['route' => 'conges.personnel', 'icone' => 'calendar-days', 'titre' => 'Congés du personnel', 'texte' => 'Circuit des demandes'] : null,
                $utilisateur->peutSaisirPresence() ? ['route' => 'presence.justificatifs', 'icone' => 'document-text', 'titre' => 'Justificatifs', 'texte' => 'Absences à justifier'] : null,
                $utilisateur->peutConsulterPersonnel() ? ['route' => 'employes.index', 'icone' => 'users', 'titre' => 'Employés', 'texte' => 'Fiches du personnel'] : null,
                $presence ? ['route' => 'presence.rapport', 'icone' => 'chart-bar', 'titre' => 'Rapport de présence', 'texte' => 'Retards et absences'] : null,
                $utilisateur->peutPreparerPaie() ? ['route' => 'paie.historique', 'icone' => 'document-duplicate', 'titre' => 'Historique de la paie', 'texte' => 'Masse salariale'] : null,
            ])))
            @foreach($raccourcis as $raccourci)
                <a href="{{ route($raccourci['route']) }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-bordure bg-white p-4 shadow-xs transition hover:border-bordure-forte hover:shadow-sm">
                    <span class="icone-mise-en-avant group-hover:text-accent"><x-icone :nom="$raccourci['icone']" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-ink">{{ $raccourci['titre'] }}</span>
                        <span class="block truncate text-xs text-tertiaire">{{ $raccourci['texte'] }}</span>
                    </span>
                    <x-icone nom="chevron-right" class="h-4 w-4 text-bordure-forte group-hover:text-tertiaire" />
                </a>
            @endforeach
        </div>
    </section>
</x-page>
