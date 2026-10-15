<x-page titre="Organigramme" sous-titre="Directions et unités organisationnelles avec leur responsable, puis les employés actifs rattachés. La Direction générale apparaît en premier. Cliquez sur une unité pour la déplier ou la replier." x-data>
    <x-slot name="actions">
        <button type="button" @click="$dispatch('organigramme-deplier')" class="bouton-secondaire">Tout déplier</button>
        <button type="button" @click="$dispatch('organigramme-replier')" class="bouton-secondaire">Tout replier</button>
        <a href="{{ route('organigramme.pdf') }}" class="bouton-principal"><x-icone nom="arrow-down-tray" class="h-4 w-4" />Exporter en PDF</a>
    </x-slot>

    @if($organigramme->estVide())
        <div class="panneau py-12 text-center">
            <x-icone nom="share" class="mx-auto mb-3 block h-8 w-8 text-bordure-forte" />
            <p class="text-sm font-medium text-ink">Organigramme vide</p>
            <p class="text-sm text-tertiaire">Aucun département ni employé actif pour l'instant.</p>
        </div>
    @else

        @if($organigramme->racines !== [])
            <ul class="ms-2 space-y-3 border-s border-bordure ps-5">
                @foreach($organigramme->racines as $racine)
                    <x-organigramme.noeud :noeud="$racine" />
                @endforeach
            </ul>
        @else
            <p class="text-sm text-tertiaire">Aucun département.</p>
        @endif

        @if($organigramme->employesSansDepartement->isNotEmpty())
            <div class="panneau">
                <p class="panneau-titre">Employés sans département</p>
                <ul class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($organigramme->employesSansDepartement as $employe)
                        <li wire:key="sans-departement-{{ $employe->id }}" class="flex items-center gap-2">
                            <x-avatar :nom="$employe->nomComplet()" :photo="$employe->photoUrl()" taille="sm" />
                            <span class="text-sm text-ink">{{ $employe->nomComplet() }}</span>
                            <span class="truncate text-xs text-tertiaire">{{ $employe->poste }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <p class="text-xs text-tertiaire">{{ $organigramme->effectifTotal() }} personne(s) active(s) dans l'organigramme.</p>
    @endif
</x-page>
