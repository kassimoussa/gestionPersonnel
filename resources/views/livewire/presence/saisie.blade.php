@use('App\Enums\SourcePointage')
<x-page titre="Saisie manuelle des pointages" sous-titre="Pour corriger un pointage ou en ajouter un en cas de panne de la pointeuse ou d'oubli de badge. Une saisie manuelle remplace le pointage importé du même jour.">
    <x-slot name="actions">
        <div class="w-full sm:w-60">
            <select wire:model.live="filtreEmployeId" class="champ" aria-label="Filtrer par employé">
                <option value="">Tous les employés</option>
                @foreach($employes as $employe)
                    <option value="{{ $employe->id }}">{{ $employe->nomComplet() }}</option>
                @endforeach
            </select>
        </div>
        <button wire:click="nouveau" class="bouton-principal w-full sm:w-auto"><x-icone nom="plus" class="h-4 w-4" />Ajouter un pointage</button>
    </x-slot>

    @if($formulaireOuvert)
        <x-modale contexte="Présence" :titre="$editId ? 'Corriger le pointage' : 'Nouveau pointage'" :sous-titre="$editId ? 'L\'employé et la date ne sont pas modifiables : supprimez le pointage pour le recréer un autre jour.' : 'Une saisie manuelle remplace le pointage importé du même jour.'" largeur="lg">
            <form wire:submit="enregistrer">
                <div class="modale-corps">
                    <x-section-formulaire titre="Employé et date" description="Seuls les employés de votre périmètre sont proposés.">
                        <div class="sm:col-span-4">
                            <x-selecteur-employe
                                id="pointage-employe"
                                :requis="true"
                                :recherche="$rechercheEmploye"
                                :resultats="$employesTrouves"
                                :choisi="$employeChoisi"
                                :verrouille="(bool) $editId" />
                            @error('employe_id') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="pointage-date" class="etiquette">Date *</label>
                            <input id="pointage-date" type="date" wire:model="date" @disabled($editId) max="{{ now()->toDateString() }}" class="champ">
                            @error('date') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>

                    <x-section-formulaire titre="Horaires" description="Au moins l'heure d'arrivée ou de départ. Le retard est calculé selon l'heure de début et la tolérance des paramètres.">
                        <div class="sm:col-span-3">
                            <label for="pointage-arrivee" class="etiquette">Heure d'arrivée</label>
                            <div class="champ-icone">
                                <x-icone nom="clock" />
                                <input id="pointage-arrivee" type="time" wire:model="heure_arrivee" class="champ">
                            </div>
                            @error('heure_arrivee') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="pointage-depart" class="etiquette">Heure de départ</label>
                            <div class="champ-icone">
                                <x-icone nom="clock" />
                                <input id="pointage-depart" type="time" wire:model="heure_depart" class="champ">
                            </div>
                            @error('heure_depart') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-6">
                            <label for="pointage-commentaire" class="etiquette">Commentaire</label>
                            <textarea id="pointage-commentaire" wire:model="commentaire" rows="3" placeholder="Ex : oubli de badge, pointeuse en panne" class="champ"></textarea>
                            @error('commentaire') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>
                </div>
                <div class="modale-pied">
                    <p class="text-sm text-tertiaire">* Champs obligatoires</p>
                    <div class="modale-pied-actions">
                        <button type="button" wire:click="annuler" class="bouton-secondaire">Annuler</button>
                        <button type="submit" class="bouton-principal">{{ $editId ? 'Enregistrer la correction' : 'Enregistrer le pointage' }}</button>
                    </div>
                </div>
            </form>
        </x-modale>
    @endif

    <div class="tableau-conteneur">
        <table class="tableau">
            <thead>
                <tr>
                    <x-entete-triable colonne="date" :tri="$tri" :sens="$sens">Date</x-entete-triable>
                    <x-entete-triable colonne="employe" :tri="$tri" :sens="$sens">Employé</x-entete-triable>
                    <x-entete-triable colonne="heure_arrivee" :tri="$tri" :sens="$sens">Arrivée</x-entete-triable>
                    <x-entete-triable colonne="heure_depart" :tri="$tri" :sens="$sens">Départ</x-entete-triable>
                    <x-entete-triable colonne="source" :tri="$tri" :sens="$sens">Source</x-entete-triable>
                    <th>Commentaire</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pointages as $pointage)
                    <tr wire:key="pointage-{{ $pointage->id }}">
                        <td class="secondaire whitespace-nowrap">{{ $pointage->date->format('d/m/Y') }}</td>
                        <td>
                            <div class="identite">
                                <x-avatar :nom="$pointage->employe?->nomComplet() ?? '?'" :photo="$pointage->employe?->photoUrl()" taille="sm" />
                                <span class="identite-nom">{{ $pointage->employe?->nomComplet() ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="tabular-nums {{ $pointage->estEnRetard() ? 'text-attention' : '' }}">
                            {{ $pointage->heureArriveeCourte() ?? '—' }}
                            @if($pointage->estEnRetard()) <span class="text-xs">(retard)</span> @endif
                        </td>
                        <td class="tabular-nums">{{ $pointage->heureDepartCourte() ?? '—' }}</td>
                        <td>
                            <x-badge variante="neutre" :point="false">{{ $pointage->source->libelle() }}</x-badge>
                            @if($pointage->source === SourcePointage::Manuel && $pointage->saisiPar)
                                <span class="sous-ligne">par {{ $pointage->saisiPar->name }}</span>
                            @endif
                        </td>
                        <td class="secondaire max-w-xs text-xs">{{ $pointage->commentaire ?: '—' }}</td>
                        <td class="actions">
                            <div class="actions-icones">
                                <x-bouton-icone icone="pencil-square" libelle="Corriger" wire:click="editer({{ $pointage->id }})" />
                                <x-bouton-icone icone="trash" libelle="Supprimer" variante="danger" wire:click="supprimer({{ $pointage->id }})" wire:confirm="Supprimer ce pointage ?" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="vide"><x-icone nom="magnifying-glass" class="mx-auto mb-3 block h-8 w-8 text-bordure-forte" />Aucun pointage pour votre périmètre.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $pointages->links() }}
    </div>
</x-page>
