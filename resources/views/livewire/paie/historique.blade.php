@use('App\Models\FichePaie')
<x-page titre="Historique de la paie" sous-titre="Toutes les fiches, brouillons compris, avec la masse salariale et le coût employeur de la sélection.">
    <div class="panneau grid max-w-4xl grid-cols-2 gap-4 md:grid-cols-4">
        <div>
            <label class="etiquette" for="mois">Mois</label>
            <select id="mois" wire:model.live="mois" class="champ">
                <option value="">Toute l'année</option>
                @foreach($moisDisponibles as $numero => $libelle)
                    <option value="{{ $numero }}">{{ $libelle }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="etiquette" for="annee">Année</label>
            <select id="annee" wire:model.live="annee" class="champ">
                @foreach($annees as $uneAnnee)
                    <option value="{{ $uneAnnee }}">{{ $uneAnnee }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="etiquette" for="departement_id">Département</label>
            <select id="departement_id" wire:model.live="departement_id" class="champ">
                <option value="">Tous les départements</option>
                @foreach($departements as $departement)
                    <option value="{{ $departement->id }}">{{ $departement->nom }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="etiquette" for="statut">Statut</label>
            <select id="statut" wire:model.live="statut" class="champ">
                <option value="">Tous les statuts</option>
                @foreach($statuts as $unStatut)
                    <option value="{{ $unStatut->value }}">{{ $unStatut->libelle() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <div class="indicateur">
            <p class="indicateur-libelle">Fiches · {{ $periode }}</p>
            <p class="indicateur-valeur">{{ $totaux['nombre'] }}</p>
        </div>
        <div class="indicateur">
            <p class="indicateur-libelle">Masse salariale brute</p>
            <p class="indicateur-valeur">{{ FichePaie::montant($totaux['brut']) }}</p>
        </div>
        <div class="indicateur">
            <p class="indicateur-libelle">CNSS employeur</p>
            <p class="indicateur-valeur">{{ FichePaie::montant($totaux['cnss_employeur']) }}</p>
        </div>
        <div class="indicateur">
            <p class="indicateur-libelle">Net à payer</p>
            <p class="indicateur-valeur">{{ FichePaie::montant($totaux['net']) }}</p>
        </div>
        <div class="indicateur">
            <p class="indicateur-libelle">Coût employeur</p>
            <p class="indicateur-valeur">{{ FichePaie::montant($totaux['cout_employeur']) }}</p>
        </div>
    </div>

    <div class="tableau-conteneur">
        <table class="tableau">
            <thead>
                <tr>
                    <x-entete-triable colonne="periode" :tri="$tri" :sens="$sens">Période</x-entete-triable>
                    <x-entete-triable colonne="employe" :tri="$tri" :sens="$sens">Employé</x-entete-triable>
                    <x-entete-triable colonne="brut" :tri="$tri" :sens="$sens" class="montant">Brut</x-entete-triable>
                    <th class="montant">CNSS sal.</th>
                    <th class="montant">CNSS emp.</th>
                    <x-entete-triable colonne="irpp" :tri="$tri" :sens="$sens" class="montant">IRPP</x-entete-triable>
                    <x-entete-triable colonne="net" :tri="$tri" :sens="$sens" class="montant">Net à payer</x-entete-triable>
                    <x-entete-triable colonne="statut" :tri="$tri" :sens="$sens">Statut</x-entete-triable>
                    <th class="actions">Bulletin</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fiches as $fiche)
                    <tr wire:key="fiche-{{ $fiche->id }}">
                        <td class="secondaire whitespace-nowrap">{{ $fiche->periodeLibelle() }}</td>
                        <td>
                            <div class="identite">
                                <x-avatar :nom="$fiche->employe?->nomComplet() ?? '?'" :photo="$fiche->employe?->photoUrl()" />
                                <div class="min-w-0">
                                    <span class="identite-nom">{{ $fiche->employe?->nomComplet() ?? '—' }}</span>
                                    <span class="sous-ligne">{{ $fiche->employe?->departement?->nom ?? 'Sans département' }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="montant">{{ FichePaie::montant($fiche->brut) }}</td>
                        <td class="montant secondaire">{{ FichePaie::montant($fiche->cnss_salarie) }}</td>
                        <td class="montant secondaire">{{ FichePaie::montant($fiche->cnss_employeur) }}</td>
                        <td class="montant secondaire">{{ FichePaie::montant($fiche->irpp) }}</td>
                        <td class="montant font-medium">{{ FichePaie::montant($fiche->net_a_payer) }}</td>
                        <td>
                            <x-badge :variante="$fiche->statut->varianteBadge()">{{ $fiche->statut->libelle() }}</x-badge>
                            @if($fiche->estValidee())
                                <span class="sous-ligne">le {{ $fiche->validee_le?->format('d/m/Y') }}</span>
                            @endif
                        </td>
                        <td class="actions">
                            <div class="actions-icones">
                                <x-bouton-icone icone="arrow-down-tray" libelle="Télécharger le bulletin" :href="route('paie.bulletin', $fiche)" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="vide"><x-icone nom="magnifying-glass" class="mx-auto mb-3 block h-8 w-8 text-bordure-forte" />Aucune fiche pour cette sélection.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $fiches->links() }}
    </div>
</x-page>
