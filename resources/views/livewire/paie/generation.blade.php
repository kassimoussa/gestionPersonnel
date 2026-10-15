@use('App\Models\FichePaie')
<x-page titre="Génération de la paie" sous-titre="Un brouillon par employé actif sous contrat sur la période, à partir du salaire de base de son contrat. Ajustez primes et indemnités puis validez : une fiche validée est figée et devient visible par l'employé.">
    <x-slot name="actions">
        <div class="w-full sm:w-44">
            <label class="etiquette" for="mois">Mois</label>
            <select id="mois" wire:model.live="mois" class="champ">
                @foreach($moisDisponibles as $numero => $libelle)
                    <option value="{{ $numero }}">{{ $libelle }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-full sm:w-32">
            <label class="etiquette" for="annee">Année</label>
            <select id="annee" wire:model.live="annee" class="champ">
                @foreach($annees as $uneAnnee)
                    <option value="{{ $uneAnnee }}">{{ $uneAnnee }}</option>
                @endforeach
            </select>
        </div>
        <button wire:click="generer" wire:confirm="Générer les brouillons de {{ $periode }} pour {{ $aGenerer }} employé(s) ?" class="bouton-principal w-full sm:w-auto" @disabled($aGenerer === 0)>
            Générer les brouillons ({{ $aGenerer }})
        </button>
    </x-slot>

    @if($information)
        <div class="message-succes">{{ $information }}</div>
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <div class="indicateur">
            <p class="indicateur-libelle">Fiches {{ $periode }}</p>
            <p class="indicateur-valeur">{{ $fiches->count() }}</p>
            <p class="text-xs text-secondaire">dont {{ $fiches->count() - $nombreBrouillons }} validée(s)</p>
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
                    <th>Employé</th>
                    <th class="montant">Salaire de base</th>
                    <th class="montant">Primes</th>
                    <th class="montant">Indemnités</th>
                    <th class="montant">Brut</th>
                    <th class="montant">CNSS sal.</th>
                    <th class="montant">IRPP</th>
                    <th class="montant">Net à payer</th>
                    <th>Statut</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fiches as $fiche)
                    <tr wire:key="fiche-{{ $fiche->id }}">
                        <td>
                            <div class="identite">
                                <x-avatar :nom="$fiche->employe?->nomComplet() ?? '?'" :photo="$fiche->employe?->photoUrl()" />
                                <div class="min-w-0">
                                    <span class="identite-nom">{{ $fiche->employe?->nomComplet() ?? '—' }}</span>
                                    <span class="sous-ligne">{{ $fiche->employe?->departement?->nom ?? 'Sans département' }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="montant">{{ FichePaie::montant($fiche->salaire_base) }}</td>
                        <td class="montant">
                            @if($fiche->estValidee())
                                {{ FichePaie::montant($fiche->primes) }}
                            @else
                                <span class="ms-auto block w-28"><input type="number" step="0.01" min="0" wire:model="primes.{{ $fiche->id }}" wire:change="mettreAJour({{ $fiche->id }})" class="champ text-right" aria-label="Primes de {{ $fiche->employe?->nomComplet() }}"></span>
                                @error("primes.{$fiche->id}") <p class="erreur">{{ $message }}</p> @enderror
                            @endif
                        </td>
                        <td class="montant">
                            @if($fiche->estValidee())
                                {{ FichePaie::montant($fiche->indemnites) }}
                            @else
                                <span class="ms-auto block w-28"><input type="number" step="0.01" min="0" wire:model="indemnites.{{ $fiche->id }}" wire:change="mettreAJour({{ $fiche->id }})" class="champ text-right" aria-label="Indemnités de {{ $fiche->employe?->nomComplet() }}"></span>
                                @error("indemnites.{$fiche->id}") <p class="erreur">{{ $message }}</p> @enderror
                            @endif
                        </td>
                        <td class="montant">{{ FichePaie::montant($fiche->brut) }}</td>
                        <td class="montant secondaire">{{ FichePaie::montant($fiche->cnss_salarie) }}</td>
                        <td class="montant secondaire">{{ FichePaie::montant($fiche->irpp) }}</td>
                        <td class="montant font-medium">{{ FichePaie::montant($fiche->net_a_payer) }}</td>
                        <td>
                            <x-badge :variante="$fiche->statut->varianteBadge()">{{ $fiche->statut->libelle() }}</x-badge>
                            @if($fiche->estValidee())
                                <span class="sous-ligne">le {{ $fiche->validee_le?->format('d/m/Y') }} par {{ $fiche->valideePar?->name ?? '—' }}</span>
                            @endif
                        </td>
                        <td class="actions">
                            <div class="actions-icones gap-2">
                                @if($fiche->estValidee())
                                    <x-bouton-icone icone="arrow-down-tray" libelle="Télécharger le bulletin" :href="route('paie.bulletin', $fiche)" />
                                @else
                                    {{-- Valider engage les montants : l'action garde son libellé. --}}
                                    @if($peutValider)
                                        <button wire:click="valider({{ $fiche->id }})" wire:confirm="Valider la fiche de {{ $fiche->employe?->nomComplet() }} ? Elle ne pourra plus être modifiée." class="bouton-lien-principal">Valider</button>
                                    @endif
                                    <x-bouton-icone icone="trash" libelle="Supprimer le brouillon" variante="danger" wire:click="supprimer({{ $fiche->id }})" wire:confirm="Supprimer ce brouillon ?" />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="vide"><x-icone nom="magnifying-glass" class="mx-auto mb-3 block h-8 w-8 text-bordure-forte" />Aucune fiche pour {{ $periode }}. Cliquez sur « Générer les brouillons ».</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($nombreBrouillons > 0 && ! $peutValider)
        <div class="message-info">Les brouillons sont prêts. Leur validation revient au directeur général.</div>
    @endif

    @if($nombreBrouillons > 0 && $peutValider)
        <div class="flex justify-end">
            <button wire:click="validerTout" wire:confirm="Valider les {{ $nombreBrouillons }} brouillon(s) de {{ $periode }} ? Ils ne pourront plus être modifiés." class="bouton-principal">
                Valider toutes les fiches brouillon ({{ $nombreBrouillons }})
            </button>
        </div>
    @endif
</x-page>
