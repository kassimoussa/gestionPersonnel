<x-page titre="Contrats" sous-titre="Types de contrat, dates et salaire de base de chaque employé.">
    <x-slot name="actions">
        <button wire:click="nouveau" class="bouton-principal"><x-icone nom="plus" class="h-4 w-4" />Ajouter un contrat</button>
    </x-slot>

    @if($formulaireOuvert)
        <x-modale contexte="Contrats" :titre="$editId ? 'Modifier le contrat' : 'Nouveau contrat'" sous-titre="Type de contrat, période et salaire de base de l'employé." largeur="lg">
            <form wire:submit="enregistrer">
                <div class="modale-corps">
                    <x-section-formulaire titre="Employé" description="La personne concernée par ce contrat.">
                        <div class="sm:col-span-6">
                            <x-selecteur-employe
                                id="contrat-employe"
                                :requis="true"
                                :recherche="$rechercheEmploye"
                                :resultats="$employesTrouves"
                                :choisi="$employeChoisi" />
                            @error('employe_id') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>

                    <x-section-formulaire titre="Type de contrat" description="Un contrat de moins d'un mois civil est imposé au taux forfaitaire sur la paie.">
                        @foreach(['CDI' => 'Durée indéterminée', 'CDD' => 'Durée déterminée', 'Stage' => 'Convention de stage', 'Consultant' => 'Prestation de service'] as $valeur => $description)
                            <label class="choix sm:col-span-3">
                                <input type="radio" wire:model="type" value="{{ $valeur }}">
                                <span>
                                    <span class="choix-titre">{{ $valeur }}</span>
                                    <span class="choix-description">{{ $description }}</span>
                                </span>
                            </label>
                        @endforeach
                        @error('type') <p class="erreur sm:col-span-6">{{ $message }}</p> @enderror
                    </x-section-formulaire>

                    <x-section-formulaire titre="Période et rémunération" description="Le salaire de base est copié dans les fiches de paie au moment de leur génération.">
                        <div class="sm:col-span-3">
                            <label for="contrat-debut" class="etiquette">Date de début *</label>
                            <input id="contrat-debut" type="date" wire:model="date_debut" class="champ">
                            @error('date_debut') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="contrat-fin" class="etiquette">Date de fin</label>
                            <input id="contrat-fin" type="date" wire:model="date_fin" class="champ">
                            <p class="aide">Laisser vide pour un contrat sans date de fin.</p>
                            @error('date_fin') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="contrat-salaire" class="etiquette">Salaire de base mensuel *</label>
                            <div class="champ-unite">
                                <input id="contrat-salaire" type="number" step="0.01" min="0" wire:model="salaire_base" placeholder="150000" class="champ">
                                <span>FD</span>
                            </div>
                            @error('salaire_base') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>
                </div>
                <div class="modale-pied">
                    <p class="text-sm text-tertiaire">* Champs obligatoires</p>
                    <div class="modale-pied-actions">
                        <button type="button" wire:click="annuler" class="bouton-secondaire">Annuler</button>
                        <button type="submit" class="bouton-principal">{{ $editId ? 'Enregistrer les modifications' : 'Créer le contrat' }}</button>
                    </div>
                </div>
            </form>
        </x-modale>
    @endif

    <div class="tableau-conteneur">
        <table class="tableau">
            <thead>
                <tr>
                    <x-entete-triable colonne="employe" :tri="$tri" :sens="$sens">Employé</x-entete-triable>
                    <x-entete-triable colonne="type" :tri="$tri" :sens="$sens">Type</x-entete-triable>
                    <x-entete-triable colonne="date_debut" :tri="$tri" :sens="$sens">Début</x-entete-triable>
                    <x-entete-triable colonne="date_fin" :tri="$tri" :sens="$sens">Fin</x-entete-triable>
                    <x-entete-triable colonne="salaire_base" :tri="$tri" :sens="$sens" class="montant">Salaire de base</x-entete-triable>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contrats as $contrat)
                    <tr wire:key="contrat-{{ $contrat->id }}">
                        <td>
                            <div class="identite">
                                <x-avatar :nom="$contrat->employe?->nomComplet() ?? '?'" :photo="$contrat->employe?->photoUrl()" />
                                <span class="identite-nom">{{ $contrat->employe?->nomComplet() ?? '—' }}</span>
                            </div>
                        </td>
                        <td><x-badge variante="neutre" :point="false">{{ $contrat->type }}</x-badge></td>
                        <td class="secondaire">{{ $contrat->date_debut?->format('d/m/Y') }}</td>
                        <td class="secondaire">{{ $contrat->date_fin ? $contrat->date_fin->format('d/m/Y') : 'En cours' }}</td>
                        <td class="montant">{{ number_format($contrat->salaire_base, 2, ',', ' ') }}</td>
                        <td class="actions">
                            <div class="actions-icones">
                                <x-bouton-icone icone="pencil-square" libelle="Modifier" wire:click="editer({{ $contrat->id }})" />
                                <x-bouton-icone icone="trash" libelle="Supprimer" variante="danger" wire:click="supprimer({{ $contrat->id }})" wire:confirm="Êtes-vous sûr de vouloir supprimer ce contrat ?" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="vide"><x-icone nom="magnifying-glass" class="mx-auto mb-3 block h-8 w-8 text-bordure-forte" />Aucun contrat pour l'instant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-page>
