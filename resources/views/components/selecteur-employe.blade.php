@props([
    'id' => 'recherche-employe',
    'label' => 'Employé',
    'requis' => false,
    'resultats' => null,
    'choisi' => null,
    'recherche' => '',
    'aide' => 'Cherchez par nom, matricule ou numéro de pointeuse.',
    'placeholder' => 'Ex : Abdirazak, 1067…',
    'verrouille' => false,
])
{{--
    Choix d'un employé par recherche libre. Le composant parent utilise le trait
    RechercheEmploye : la saisie alimente $rechercheEmploye, le clic appelle
    choisirEmploye() et le bouton « changer » appelle effacerEmploye().
--}}
<div class="relative">
    <label for="{{ $id }}" class="etiquette">{{ $label }}{{ $requis ? ' *' : '' }}</label>

    @if($choisi)
        <div class="flex flex-wrap items-center gap-3 rounded-lg border border-bordure bg-fond p-3">
            <x-avatar :nom="$choisi->nomComplet()" :photo="$choisi->photoUrl()" taille="sm" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-ink">{{ $choisi->nomComplet() }}</p>
                <p class="text-xs text-tertiaire">{{ collect([$choisi->matricule ? 'Matricule '.$choisi->matricule : null, $choisi->poste, $choisi->departement?->nom])->filter()->join(' · ') }}</p>
            </div>
            @unless($verrouille)
                <button type="button" wire:click="effacerEmploye" class="bouton-lien">Changer</button>
            @endunless
        </div>
    @else
        <div class="champ-icone">
            <x-icone nom="magnifying-glass" />
            <input
                id="{{ $id }}"
                type="search"
                wire:model.live.debounce.300ms="rechercheEmploye"
                placeholder="{{ $placeholder }}"
                autocomplete="off"
                class="champ">
        </div>

        @if($resultats !== null && $resultats->isNotEmpty())
            <ul class="liste-suggestions">
                @foreach($resultats as $employe)
                    <li wire:key="suggestion-{{ $employe->id }}">
                        <button type="button" wire:click="choisirEmploye({{ $employe->id }})" class="suggestion">
                            <x-avatar :nom="$employe->nomComplet()" :photo="$employe->photoUrl()" taille="sm" />
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-ink">{{ $employe->nomComplet() }}</span>
                                <span class="block truncate text-xs text-tertiaire">{{ collect([$employe->matricule, $employe->poste, $employe->departement?->nom])->filter()->join(' · ') ?: 'Sans matricule' }}</span>
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @elseif(trim($recherche) !== '')
            <p class="aide">Aucun employé ne correspond à « {{ $recherche }} ».</p>
        @elseif($aide)
            <p class="aide">{{ $aide }}</p>
        @endif
    @endif
</div>
