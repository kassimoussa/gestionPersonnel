@php($parametres = \App\Models\ParametresApplication::courants())
<x-page titre="Rapport de présence" :sous-titre="'Statut par employé et par jour, calculé à partir des pointages, des congés approuvés et du calendrier (retard au-delà de '.$parametres->heureDebutTravail().' + '.$parametres->toleranceRetardMinutes().' min).'">
    <div class="panneau grid max-w-3xl grid-cols-1 gap-4 md:grid-cols-3">
        <div>
            <label class="etiquette">Du</label>
            <input type="date" wire:model.live="date_debut" class="champ">
        </div>
        <div>
            <label class="etiquette">Au</label>
            <input type="date" wire:model.live="date_fin" class="champ">
        </div>
        <div>
            <label class="etiquette">Département</label>
            <select wire:model.live="departement_id" class="champ">
                <option value="">{{ $estDg ? 'Tous les départements' : 'Tous mes départements' }}</option>
                @foreach($departements as $departement)
                    <option value="{{ $departement->id }}">{{ $departement->nom }}</option>
                @endforeach
            </select>
        </div>
        @if($erreurPeriode)
            <p class="erreur md:col-span-3">{{ $erreurPeriode }}</p>
        @endif
    </div>

    <div class="tableau-conteneur">
        <table class="tableau w-auto min-w-full">
            <thead>
                <tr>
                    <th class="sticky left-0 z-10 bg-fond">Employé</th>
                    @foreach($jours as $jour)
                        <th class="px-1 text-center {{ in_array($jour->dayOfWeekIso, $parametres->joursWeekend(), true) ? 'opacity-60' : '' }}">
                            <span class="block text-[10px]">{{ ucfirst($jour->locale('fr')->isoFormat('dd')) }}</span>
                            <span class="block">{{ $jour->format('d') }}</span>
                        </th>
                    @endforeach
                    <th class="px-2 text-center">Prés.</th>
                    <th class="px-2 text-center">Ret.</th>
                    <th class="px-2 text-center">Abs.</th>
                    <th class="px-2 text-center">Just.</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lignes as $ligne)
                    <tr wire:key="ligne-{{ $ligne['employe']->id }}">
                        <td class="sticky left-0 z-10 whitespace-nowrap bg-white">
                            <div class="identite">
                                <x-avatar :nom="$ligne['employe']->nomComplet()" :photo="$ligne['employe']->photoUrl()" taille="sm" />
                                <div class="min-w-0">
                                    <span class="identite-nom">{{ $ligne['employe']->nomComplet() }}</span>
                                    <span class="sous-ligne">{{ $ligne['employe']->departement?->nom ?? 'Sans département' }}</span>
                                </div>
                            </div>
                        </td>
                        @foreach($ligne['jours'] as $date => $cellule)
                            @php($pointage = $cellule['pointage'])
                            <td class="px-1 text-center">
                                <span class="cellule-presence badge-{{ $cellule['statut']->varianteCellule() }}"
                                      title="{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }} : {{ $cellule['statut']->libelle() }}{{ $pointage ? ' (arrivée '.($pointage->heureArriveeCourte() ?? '—').', départ '.($pointage->heureDepartCourte() ?? '—').')' : '' }}">
                                    {{ $cellule['statut']->abreviation() }}
                                </span>
                            </td>
                        @endforeach
                        <td class="px-2 text-center tabular-nums">{{ $ligne['totaux']['present'] + $ligne['totaux']['retard'] }}</td>
                        <td class="px-2 text-center tabular-nums {{ $ligne['totaux']['retard'] > 0 ? 'text-attention' : 'secondaire' }}">{{ $ligne['totaux']['retard'] }}</td>
                        <td class="px-2 text-center tabular-nums {{ $ligne['totaux']['absence'] > 0 ? 'text-danger' : 'secondaire' }}">{{ $ligne['totaux']['absence'] }}</td>
                        <td class="px-2 text-center tabular-nums {{ $ligne['totaux']['absence_justifiee'] > 0 ? 'text-attention' : 'secondaire' }}">{{ $ligne['totaux']['absence_justifiee'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($jours) + 5 }}" class="vide"><x-icone nom="magnifying-glass" class="mx-auto mb-3 block h-8 w-8 text-bordure-forte" />
                            {{ $erreurPeriode ? 'Corrigez la période pour afficher le rapport.' : 'Aucun employé actif dans ce périmètre.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-secondaire">
        @foreach($statuts as $statut)
            <span class="inline-flex items-center gap-1.5">
                <span class="cellule-presence badge-{{ $statut->varianteCellule() }} h-5 w-5">{{ $statut->abreviation() }}</span>
                {{ $statut->libelle() }}
            </span>
        @endforeach
    </div>
</x-page>
