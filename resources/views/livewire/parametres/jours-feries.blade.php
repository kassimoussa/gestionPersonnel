<x-page titre="Jours fériés" sous-titre="Ces journées ne sont décomptées ni des congés ni de la présence. Les fêtes à date fixe valent pour toutes les années ; les fêtes mobiles se saisissent année par année.">
    <x-slot name="actions">
        <div class="w-full sm:w-40">
            <select wire:model.live="annee" class="champ" aria-label="Année">
                @foreach($annees as $valeur)
                    <option value="{{ $valeur }}">{{ $valeur }}</option>
                @endforeach
            </select>
        </div>
        <button wire:click="nouveau" class="bouton-principal w-full sm:w-auto"><x-icone nom="plus" class="h-4 w-4" />Ajouter un jour férié</button>
    </x-slot>

    @if($information)
        <div class="message-succes">{{ $information }}</div>
    @endif

    @if($formulaireOuvert)
        <x-modale contexte="Paramètres" :titre="$editId ? 'Modifier le jour férié' : 'Nouveau jour férié'" sous-titre="Une fête à date fixe se saisit une seule fois ; une fête mobile est propre à son année." largeur="md">
            <form wire:submit="enregistrer">
                <div class="modale-corps">
                    <x-section-formulaire titre="Journée" description="Le 1er janvier, la fête de l'Indépendance ou une fête religieuse.">
                        <div class="sm:col-span-3">
                            <label for="ferie-date" class="etiquette">Date *</label>
                            <input id="ferie-date" type="date" wire:model="date" class="champ">
                            @error('date') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="ferie-libelle" class="etiquette">Libellé *</label>
                            <input id="ferie-libelle" type="text" wire:model="libelle" class="champ" placeholder="Ex : Aïd el-Fitr">
                            @error('libelle') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-6">
                            <x-interrupteur
                                id="ferie-recurrent"
                                titre="Fête à date fixe"
                                description="Revient le même jour chaque année : seuls le jour et le mois comptent. À laisser décoché pour les fêtes musulmanes, dont la date change."
                                wire:model="recurrent" />
                            @error('recurrent') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>
                </div>
                <div class="modale-pied">
                    <p class="text-sm text-tertiaire">* Champs obligatoires</p>
                    <div class="modale-pied-actions">
                        <button type="button" wire:click="annuler" class="bouton-secondaire">Annuler</button>
                        <button type="submit" class="bouton-principal">{{ $editId ? 'Enregistrer' : 'Ajouter' }}</button>
                    </div>
                </div>
            </form>
        </x-modale>
    @endif

    <div class="tableau-conteneur">
        <table class="tableau">
            <thead>
                <tr>
                    <th>Journée</th>
                    <th>Libellé</th>
                    <th>Nature</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($feries as $ferie)
                    <tr wire:key="ferie-{{ $ferie->id }}">
                        <td class="whitespace-nowrap font-medium">{{ $ferie->libelleDate() }}</td>
                        <td class="secondaire">{{ $ferie->libelle }}</td>
                        <td>
                            <x-badge :variante="$ferie->recurrent ? 'info' : 'neutre'" :point="false">
                                {{ $ferie->recurrent ? 'Date fixe' : 'Fête mobile' }}
                            </x-badge>
                        </td>
                        <td class="actions">
                            <div class="actions-icones">
                                <x-bouton-icone icone="pencil-square" libelle="Modifier" wire:click="editer({{ $ferie->id }})" />
                                <x-bouton-icone icone="trash" libelle="Retirer du calendrier" variante="danger" wire:click="supprimer({{ $ferie->id }})" wire:confirm="Retirer ce jour férié du calendrier ?" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="vide"><x-icone nom="calendar-days" class="mx-auto mb-3 block h-8 w-8 text-bordure-forte" />Aucun jour férié enregistré pour {{ $annee }}.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-page>
