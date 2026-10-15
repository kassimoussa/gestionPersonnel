<x-page titre="Départements" sous-titre="Unités organisationnelles (directions, divisions, départements, services), leur responsable et leur rattachement. Le responsable de la Direction générale est le directeur général.">
    <x-slot name="actions">
        @if($peutGerer)
            <button wire:click="nouveau" class="bouton-principal"><x-icone nom="plus" class="h-4 w-4" />Ajouter un département</button>
        @endif
    </x-slot>

    @unless($peutGerer)
        <div class="message-info">Seul l'administrateur peut modifier la structure des départements.</div>
    @endunless

    @if($formulaireOuvert)
        <x-modale contexte="Départements" :titre="$editId ? 'Modifier l\'unité' : 'Nouvelle unité'" sous-titre="Nom, type d'unité, rattachement et responsable dans l'organigramme." largeur="lg">
            <form wire:submit="enregistrer">
                <div class="modale-corps">
                    <x-section-formulaire titre="Unité" description="Schémas possibles : Direction, Division puis Service ; Direction, Département puis Service ; ou Direction puis Service.">
                        <div class="sm:col-span-6">
                            <label for="departement-nom" class="etiquette">Nom *</label>
                            <div class="champ-icone">
                                <x-icone nom="building-office-2" />
                                <input id="departement-nom" type="text" wire:model="nom" class="champ" placeholder="Direction administrative et financière">
                            </div>
                            @error('nom') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <fieldset class="sm:col-span-6">
                            <legend class="etiquette">Type d'unité *</legend>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                @foreach($types as $typeUnite)
                                    <label class="choix">
                                        <input type="radio" wire:model.live="type" value="{{ $typeUnite->value }}">
                                        <span>
                                            <span class="choix-titre">{{ $typeUnite->libelle() }}</span>
                                            <span class="choix-description">
                                                @if($typeUnite->peutEtreRacine())
                                                    Au sommet, ou rattachée à une {{ $typeUnite->libelleParentsAutorises() }}
                                                @else
                                                    Rattachée à : {{ $typeUnite->libelleParentsAutorises() }}
                                                @endif
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="aide">Un service est toujours une feuille : il ne peut pas avoir de sous-unités.</p>
                            @error('type') <p class="erreur">{{ $message }}</p> @enderror
                        </fieldset>
                    </x-section-formulaire>

                    <x-section-formulaire titre="Rattachement et responsable" description="Le responsable devient manager des employés de l'unité. Celui de la Direction générale est le directeur général.">
                        <div class="sm:col-span-6">
                            <label for="departement-parent" class="etiquette">Unité parente</label>
                            @if($typeChoisi === null)
                                <p class="champ bg-fond text-tertiaire">Choisissez d'abord un type d'unité.</p>
                            @else
                                <select id="departement-parent" wire:model="parent_departement_id" class="champ">
                                    <option value="">{{ $typeChoisi->peutEtreRacine() ? "Aucune : au sommet de l'organigramme" : 'Sélectionner une unité parente...' }}</option>
                                    @foreach($parentsPossibles as $parent)
                                        <option value="{{ $parent->id }}">{{ $parent->nom }} ({{ $parent->type->libelle() }})</option>
                                    @endforeach
                                </select>
                                <p class="aide">
                                    Parents possibles pour une unité de type {{ $typeChoisi->libelle() }} : {{ $typeChoisi->libelleParentsAutorises() }}. L'unité éditée et ses sous-unités sont exclues.
                                    @if($typeChoisi->peutEtreRacine()) Une direction rattachée à la Direction générale signifie que le directeur général en chapeaute le directeur. @endif
                                </p>
                            @endif
                            @error('parent_departement_id') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-6">
                            <label for="departement-responsable" class="etiquette">{{ $editId !== null && $editId === $directionGeneraleId ? 'Directeur général' : 'Responsable' }}</label>
                            <div class="champ-icone">
                                <x-icone nom="user" />
                                <select id="departement-responsable" wire:model="responsable_id" class="champ">
                                    <option value="">Aucun (poste vacant)</option>
                                    @foreach($employesPossibles as $employe)
                                        <option value="{{ $employe->id }}">{{ $employe->nomComplet() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @if($editId !== null && $editId === $directionGeneraleId)
                                <p class="aide">Cette unité est la Direction générale : son responsable reçoit les droits du DG (validation des congés, paie).</p>
                            @endif
                            @error('responsable_id') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>
                </div>
                <div class="modale-pied">
                    <p class="text-sm text-tertiaire">* Champs obligatoires</p>
                    <div class="modale-pied-actions">
                        <button type="button" wire:click="annuler" class="bouton-secondaire">Annuler</button>
                        <button type="submit" class="bouton-principal">{{ $editId ? 'Enregistrer les modifications' : 'Créer l\'unité' }}</button>
                    </div>
                </div>
            </form>
        </x-modale>
    @endif

    <div class="tableau-conteneur">
        <table class="tableau">
            <thead>
                <tr>
                    <x-entete-triable colonne="nom" :tri="$tri" :sens="$sens">Nom</x-entete-triable>
                    <x-entete-triable colonne="type" :tri="$tri" :sens="$sens">Type</x-entete-triable>
                    <x-entete-triable colonne="parent" :tri="$tri" :sens="$sens">Unité parente</x-entete-triable>
                    <x-entete-triable colonne="responsable" :tri="$tri" :sens="$sens">Responsable</x-entete-triable>
                    <x-entete-triable colonne="employes" :tri="$tri" :sens="$sens">Employés</x-entete-triable>
                    @if($peutGerer)
                        <th class="actions">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($departements as $departement)
                    <tr wire:key="departement-{{ $departement->id }}">
                        <td>
                            <div class="identite">
                                <span class="icone-mise-en-avant h-9 w-9"><x-icone nom="building-office-2" class="h-4 w-4" /></span>
                                <div class="min-w-0">
                                    <span class="identite-nom">{{ $departement->nom }}</span>
                                    @if($departement->id === $directionGeneraleId)
                                        <span class="sous-ligne">Direction générale</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td><x-badge :variante="$departement->type->varianteBadge()" :point="false">{{ $departement->type->libelle() }}</x-badge></td>
                        <td class="secondaire">{{ $departement->parent?->nom ?? '—' }}</td>
                        <td>
                            @if($departement->responsable)
                                <div class="identite">
                                    <x-avatar :nom="$departement->responsable->nomComplet()" :photo="$departement->responsable->photoUrl()" taille="sm" />
                                    <span class="truncate">{{ $departement->responsable->nomComplet() }}</span>
                                </div>
                            @else
                                <x-badge variante="attention">Poste vacant</x-badge>
                            @endif
                        </td>
                        <td class="secondaire">{{ $departement->employes_count }}</td>
                        @if($peutGerer)
                            <td class="actions">
                                <div class="actions-icones">
                                    <x-bouton-icone icone="pencil-square" libelle="Modifier" wire:click="editer({{ $departement->id }})" />
                                    <x-bouton-icone icone="trash" libelle="Supprimer" variante="danger" wire:click="supprimer({{ $departement->id }})" wire:confirm="Êtes-vous sûr de vouloir supprimer ce département ?" />
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="vide"><x-icone nom="magnifying-glass" class="mx-auto mb-3 block h-8 w-8 text-bordure-forte" />Aucun département pour l'instant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-page>
