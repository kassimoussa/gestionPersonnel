<x-page titre="Justificatifs d'absence" sous-titre="Pièces fournies par les employés de votre périmètre. Une justification acceptée change le statut de la journée dans le rapport de présence.">
    <x-slot name="actions">
        <div class="w-full sm:w-52">
            <select wire:model.live="filtreStatut" class="champ" aria-label="Filtrer par statut">
                <option value="en_attente">En attente</option>
                <option value="accepte">Acceptées</option>
                <option value="refuse">Refusées</option>
                <option value="tous">Toutes</option>
            </select>
        </div>
        <div class="w-full sm:w-60">
            <select wire:model.live="filtreEmployeId" class="champ" aria-label="Filtrer par employé">
                <option value="">Tous les employés</option>
                @foreach($employes as $employe)
                    <option value="{{ $employe->id }}">{{ $employe->nomComplet() }}</option>
                @endforeach
            </select>
        </div>
        <button wire:click="nouveau" class="bouton-principal w-full sm:w-auto"><x-icone nom="plus" class="h-4 w-4" />Enregistrer une justification</button>
    </x-slot>

    @if($information)
        <div class="message-succes">{{ $information }}</div>
    @endif

    @if($formulaireOuvert)
        <x-modale contexte="Justificatifs d'absence" titre="Justification reçue" sous-titre="Pour un employé qui a remis sa pièce sur papier. Elle reste à accepter ensuite." largeur="lg">
            <form wire:submit="enregistrer">
                <div class="modale-corps">
                    <x-section-formulaire titre="Employé et journée" description="Seuls les employés de votre périmètre sont proposés.">
                        <div class="sm:col-span-4">
                            <x-selecteur-employe
                                id="justificatif-employe"
                                :requis="true"
                                :recherche="$rechercheEmploye"
                                :resultats="$employesTrouves"
                                :choisi="$employeChoisi" />
                            @error('employe_id') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="justificatif-date" class="etiquette">Date *</label>
                            <input id="justificatif-date" type="date" wire:model="date" max="{{ now()->toDateString() }}" class="champ">
                            @error('date') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>

                    <x-section-formulaire titre="Motif et pièce" description="La pièce n'est accessible qu'à l'employé concerné et à ceux qui décident.">
                        <div class="sm:col-span-6">
                            <label for="justificatif-motif" class="etiquette">Motif *</label>
                            <textarea id="justificatif-motif" wire:model="motif" rows="3" class="champ" placeholder="Ex : certificat médical du 14/09"></textarea>
                            @error('motif') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-6">
                            <label for="justificatif-piece" class="etiquette">Pièce justificative</label>
                            <input id="justificatif-piece" type="file" wire:model="piece" accept=".pdf,image/*" class="sr-only">
                            <label for="justificatif-piece" class="zone-fichier mt-1">
                                <x-icone nom="document-arrow-up" class="h-6 w-6 text-tertiaire" />
                                <span class="text-sm font-medium text-ink">{{ $piece ? $piece->getClientOriginalName() : 'Choisir un fichier' }}</span>
                                <span class="text-xs text-tertiaire">PDF ou image, 2 Mo maximum. <span wire:loading wire:target="piece">Envoi en cours…</span></span>
                            </label>
                            @error('piece') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>
                </div>
                <div class="modale-pied">
                    <p class="text-sm text-tertiaire">* Champs obligatoires</p>
                    <div class="modale-pied-actions">
                        <button type="button" wire:click="annuler" class="bouton-secondaire">Annuler</button>
                        <button type="submit" class="bouton-principal" wire:loading.attr="disabled" wire:target="enregistrer, piece">Enregistrer</button>
                    </div>
                </div>
            </form>
        </x-modale>
    @endif

    @php($justificatifRefuse = $refusId ? $justificatifs->firstWhere('id', $refusId) : null)
    @if($justificatifRefuse)
        <x-modale contexte="Justificatifs d'absence" titre="Refuser la justification" sous-titre="Le motif du refus sera visible par l'employé." fermer="annulerRefus" largeur="md">
            <form wire:submit="confirmerRefus">
                <div class="modale-corps">
                    <x-section-formulaire titre="Justification concernée" description="Rappel de la demande.">
                        <div class="flex flex-wrap items-center gap-4 sm:col-span-6">
                            <x-avatar :nom="$justificatifRefuse->employe?->nomComplet() ?? '?'" :photo="$justificatifRefuse->employe?->photoUrl()" taille="lg" />
                            <div class="min-w-0 flex-1">
                                <p class="text-base font-semibold text-ink">{{ $justificatifRefuse->employe?->nomComplet() ?? '—' }}</p>
                                <p class="text-sm text-tertiaire">Absence du {{ $justificatifRefuse->date->format('d/m/Y') }} · {{ $justificatifRefuse->motif }}</p>
                            </div>
                        </div>
                    </x-section-formulaire>

                    <x-section-formulaire titre="Motif du refus" description="Expliquez ce qui manque : pièce illisible, document absent, motif non recevable.">
                        <div class="sm:col-span-6">
                            <label for="motif-refus" class="etiquette">Motif du refus (obligatoire)</label>
                            <textarea id="motif-refus" wire:model="motifRefus" rows="4" class="champ"></textarea>
                            @error('motifRefus') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>
                </div>
                <div class="modale-pied">
                    <p class="text-sm text-tertiaire">L'absence restera non justifiée.</p>
                    <div class="modale-pied-actions">
                        <button type="button" wire:click="annulerRefus" class="bouton-secondaire">Annuler</button>
                        <button type="submit" class="bouton-danger">Confirmer le refus</button>
                    </div>
                </div>
            </form>
        </x-modale>
    @endif

    <div class="tableau-conteneur">
        <table class="tableau">
            <thead>
                <tr>
                    <x-entete-triable colonne="date" :tri="$tri" :sens="$sens">Journée</x-entete-triable>
                    <x-entete-triable colonne="employe" :tri="$tri" :sens="$sens">Employé</x-entete-triable>
                    <th>Motif</th>
                    <th>Pièce</th>
                    <x-entete-triable colonne="statut" :tri="$tri" :sens="$sens">Statut</x-entete-triable>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($justificatifs as $justificatif)
                    <tr wire:key="justificatif-{{ $justificatif->id }}">
                        <td class="secondaire whitespace-nowrap">{{ $justificatif->date->format('d/m/Y') }}</td>
                        <td>
                            <div class="identite">
                                <x-avatar :nom="$justificatif->employe?->nomComplet() ?? '?'" :photo="$justificatif->employe?->photoUrl()" taille="sm" />
                                <span class="min-w-0">
                                    <span class="identite-nom">{{ $justificatif->employe?->nomComplet() ?? '—' }}</span>
                                    <span class="sous-ligne">{{ $justificatif->employe?->departement?->nom ?? 'sans département' }}</span>
                                </span>
                            </div>
                        </td>
                        <td class="secondaire max-w-xs text-xs">
                            {{ $justificatif->motif }}
                            @if($justificatif->saisiPar)
                                <span class="sous-ligne">saisi par {{ $justificatif->saisiPar->name }}</span>
                            @endif
                        </td>
                        <td class="text-xs">
                            @if($justificatif->aUnePiece())
                                <x-bouton-icone icone="document-text" libelle="Ouvrir la pièce" :href="route('presence.justificatif.piece', $justificatif)" />
                            @else
                                <span class="text-tertiaire">Aucune</span>
                            @endif
                        </td>
                        <td>
                            <x-badge :variante="$justificatif->statut->varianteBadge()">{{ $justificatif->statut->libelle() }}</x-badge>
                            @if($justificatif->decidePar)
                                <span class="sous-ligne">{{ $justificatif->decidePar->name }} le {{ $justificatif->date_decision?->format('d/m/Y') }}</span>
                            @endif
                            @if($justificatif->commentaire_decision)
                                <span class="sous-ligne">{{ $justificatif->commentaire_decision }}</span>
                            @endif
                        </td>
                        <td class="actions space-x-3">
                            @can('decider', $justificatif)
                                @if($justificatif->estEnAttente())
                                    <button wire:click="accepter({{ $justificatif->id }})" class="bouton-lien-principal">Accepter</button>
                                    <button wire:click="refuser({{ $justificatif->id }})" class="bouton-lien-danger">Refuser</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="vide"><x-icone nom="magnifying-glass" class="mx-auto mb-3 block h-8 w-8 text-bordure-forte" />Aucune justification à afficher.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $justificatifs->links() }}
    </div>
</x-page>
