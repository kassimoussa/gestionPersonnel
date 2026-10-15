@props(['noeud', 'niveau' => 0])
<li wire:key="noeud-{{ $noeud->departement->id }}" x-data="{ ouvert: true }" @organigramme-deplier.window="ouvert = true" @organigramme-replier.window="ouvert = false">
    <button type="button" @click="ouvert = ! ouvert" :aria-expanded="ouvert" class="w-full rounded-xl border bg-white p-4 text-left shadow-xs transition hover:border-bordure-forte hover:shadow-sm sm:w-auto sm:min-w-[24rem] {{ $noeud->posteVacant() ? 'border-attention-bord' : 'border-bordure' }}" @if($noeud->estDirectionGenerale) style="border-top: 3px solid var(--color-accent);" @endif>
        <div class="flex items-start gap-3">
            <x-icone nom="chevron-right" class="mt-0.5 h-4 w-4 text-tertiaire transition-transform" x-bind:class="ouvert ? 'rotate-90' : ''" />
            <div class="min-w-0 flex-1">
                @if($noeud->estDirectionGenerale && mb_strtolower($noeud->departement->nom) !== 'direction générale')
                    <p class="mb-1 text-xs font-medium text-accent">Direction générale</p>
                @endif
                <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-ink">
                    {{ $noeud->departement->nom }}
                    <x-badge :variante="$noeud->type->varianteBadge()" :point="false">{{ $noeud->type->libelle() }}</x-badge>
                </p>
                @if($noeud->responsable)
                    <div class="mt-2 flex items-center gap-2">
                        <x-avatar :nom="$noeud->responsable->nomComplet()" :photo="$noeud->responsable->photoUrl()" taille="sm" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-ink">{{ $noeud->responsable->nomComplet() }}</p>
                            <p class="truncate text-xs text-tertiaire">{{ $noeud->responsable->poste }}</p>
                        </div>
                    </div>
                @else
                    <p class="mt-2"><x-badge variante="attention">Poste vacant</x-badge></p>
                @endif
            </div>
            <span class="whitespace-nowrap rounded-full bg-fond px-2 py-0.5 text-xs font-medium text-secondaire">{{ $noeud->effectifTotal() }} pers.</span>
        </div>
    </button>

    <div x-show="ouvert">
        @if($noeud->employes->isNotEmpty())
            <ul class="ms-5 mt-3 space-y-2 border-s border-bordure ps-5">
                @foreach($noeud->employes as $employe)
                    <li wire:key="employe-{{ $employe->id }}" class="flex items-center gap-2">
                        <x-avatar :nom="$employe->nomComplet()" :photo="$employe->photoUrl()" taille="sm" />
                        <span class="text-sm text-ink">{{ $employe->nomComplet() }}</span>
                        <span class="truncate text-xs text-tertiaire">{{ $employe->poste }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if($noeud->enfants !== [])
            <ul class="ms-5 mt-3 space-y-3 border-s border-bordure ps-5">
                @foreach($noeud->enfants as $enfant)
                    <x-organigramme.noeud :noeud="$enfant" :niveau="$niveau + 1" />
                @endforeach
            </ul>
        @endif
    </div>
</li>
