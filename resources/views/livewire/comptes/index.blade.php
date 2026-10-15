<x-page titre="Comptes utilisateurs" sous-titre="Accès à l'application, lien avec la fiche employé et droits accordés compte par compte. Le directeur général et les managers sont déduits de l'organigramme.">
    <x-slot name="actions">
        <div class="champ-icone w-full sm:w-72">
            <x-icone nom="magnifying-glass" />
            <input type="search" wire:model.live.debounce.400ms="recherche" placeholder="Rechercher nom, e-mail..." class="champ" aria-label="Rechercher un compte">
        </div>
        <button wire:click="nouveau" class="bouton-principal w-full sm:w-auto"><x-icone nom="plus" class="h-4 w-4" />Créer un compte</button>
    </x-slot>

    @error('suppression')
        <div class="message-erreur">{{ $message }}</div>
    @enderror

    @if($formulaireOuvert)
        <x-modale contexte="Comptes utilisateurs" :titre="$editId ? 'Modifier le compte' : 'Nouveau compte'" sous-titre="La personne se connectera avec cette adresse e-mail et ce mot de passe." largeur="lg">
            <form wire:submit="enregistrer">
                <div class="modale-corps">
                    <x-section-formulaire titre="Personne" description="Nécessaire pour les congés, les bulletins et les rôles de manager ou de directeur général.">
                        <div class="sm:col-span-6">
                            <x-selecteur-employe
                                id="compte-employe"
                                label="Fiche employé"
                                aide="Laissez vide pour un compte technique. Le nom et l'adresse e-mail sont repris de la fiche choisie."
                                :recherche="$rechercheEmploye"
                                :resultats="$employesTrouves"
                                :choisi="$employeChoisi" />
                            @error('employe_id') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>

                    <x-section-formulaire titre="Connexion" description="Identifiants de connexion à l'application.">
                        <div class="sm:col-span-6">
                            <label for="compte-nom" class="etiquette">Nom affiché *</label>
                            <input id="compte-nom" type="text" wire:model="name" class="champ">
                            @error('name') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="compte-email" class="etiquette">Adresse e-mail *</label>
                            <div class="champ-icone">
                                <x-icone nom="envelope" />
                                <input id="compte-email" type="email" wire:model="email" class="champ" autocomplete="off">
                            </div>
                            @error('email') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="compte-mot-de-passe" class="etiquette">{{ $editId ? 'Nouveau mot de passe' : 'Mot de passe *' }}</label>
                            <div class="champ-icone">
                                <x-icone nom="lock-closed" />
                                <input id="compte-mot-de-passe" type="password" wire:model="password" class="champ" autocomplete="new-password">
                            </div>
                            @if($editId)
                                <p class="aide">Laisser vide pour conserver le mot de passe actuel.</p>
                            @endif
                            @error('password') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>

                    <x-section-formulaire titre="Droits" description="Le directeur général et les managers sont déduits de l'organigramme : leurs droits s'ajoutent à ceux cochés ici.">
                        @unless($peutAttribuerDroits)
                            <div class="sm:col-span-6">
                                <div class="message-info">Seul un administrateur attribue les droits. Vous pouvez créer le compte et le lier à une fiche employé.</div>
                            </div>
                        @else
                            <div class="sm:col-span-6">
                                <x-interrupteur id="compte-admin" wire:model.live="admin" titre="Administrateur de l'application" description="Tous les droits, sans exception, et lui seul peut en attribuer aux autres comptes." />
                                @error('admin') <p class="erreur">{{ $message }}</p> @enderror
                            </div>

                            @if($admin)
                                <div class="sm:col-span-6">
                                    <div class="message-info">Ce compte a tous les droits de l'application. Les cases ci-dessous réapparaîtront si vous lui retirez le rôle d'administrateur.</div>
                                </div>
                            @else
                                <div class="border-t border-bordure pt-5 sm:col-span-6">
                                    <p class="etiquette">Partir d'un profil</p>
                                    <p class="aide">Coche un jeu de droits habituel, que vous pouvez ensuite ajuster case par case.</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach($profils as $profil)
                                            <button type="button" wire:click="appliquerProfil('{{ $profil->value }}')" class="bouton-secondaire" title="{{ $profil->description() }}">{{ $profil->libelle() }}</button>
                                        @endforeach
                                        <button type="button" wire:click="retirerTousLesDroits" class="bouton-lien-danger">Tout décocher</button>
                                    </div>
                                </div>

                                @foreach($droitsParGroupe as $groupe => $droitsDuGroupe)
                                    <div class="sm:col-span-6">
                                        <p class="titre-sidebar mb-2">{{ $groupe }}</p>
                                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            @foreach($droitsDuGroupe as $droit)
                                                <label class="choix">
                                                    <input type="checkbox" wire:model="droits" value="{{ $droit->value }}">
                                                    <span class="min-w-0">
                                                        <span class="choix-titre">{{ $droit->libelle() }}</span>
                                                        <span class="choix-description">{{ $droit->description() }}</span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                                @error('droits.*') <p class="erreur sm:col-span-6">{{ $message }}</p> @enderror
                            @endif
                        @endunless
                    </x-section-formulaire>
                </div>
                <div class="modale-pied">
                    <p class="text-sm text-tertiaire">* Champs obligatoires</p>
                    <div class="modale-pied-actions">
                        <button type="button" wire:click="annuler" class="bouton-secondaire">Annuler</button>
                        <button type="submit" class="bouton-principal">{{ $editId ? 'Enregistrer les modifications' : 'Créer le compte' }}</button>
                    </div>
                </div>
            </form>
        </x-modale>
    @endif

    <div class="tableau-conteneur">
        <table class="tableau">
            <thead>
                <tr>
                    <x-entete-triable colonne="nom" :tri="$tri" :sens="$sens">Compte</x-entete-triable>
                    <x-entete-triable colonne="employe" :tri="$tri" :sens="$sens">Fiche employé</x-entete-triable>
                    <th>Droits</th>
                    <x-entete-triable colonne="created_at" :tri="$tri" :sens="$sens">Créé le</x-entete-triable>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($comptes as $compte)
                    <tr wire:key="compte-{{ $compte->id }}">
                        <td>
                            <div class="identite">
                                <x-avatar :nom="$compte->name" :photo="$compte->employe?->photoUrl()" />
                                <div class="min-w-0">
                                    <span class="identite-nom">{{ $compte->name }}</span>
                                    <span class="sous-ligne">{{ $compte->email }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($compte->employe)
                                {{ $compte->employe->nomComplet() }}
                                <span class="sous-ligne">{{ $compte->employe->poste }} · {{ $compte->employe->departement?->nom ?? 'Sans département' }}</span>
                            @else
                                <span class="text-tertiaire">Non liée</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-1.5">
                                @if($compte->estAdmin())
                                    <x-badge variante="accent" :point="false">Administrateur</x-badge>
                                @endif
                                @if($compte->estDg())
                                    <x-badge variante="division" :point="false">Directeur général</x-badge>
                                @elseif($compte->estManager())
                                    <x-badge variante="info" :point="false">Manager</x-badge>
                                @endif
                                @php($nombreDroits = count($compte->droitsAttribues()))
                                @if(! $compte->estAdmin() && $nombreDroits > 0)
                                    <x-badge variante="succes" :point="false">{{ $nombreDroits }} droit(s)</x-badge>
                                @endif
                                @if(! $compte->estAdmin() && $nombreDroits === 0 && ! $compte->estDg() && ! $compte->estManager())
                                    <span class="text-tertiaire">Employé</span>
                                @endif
                            </div>
                        </td>
                        <td class="secondaire">{{ $compte->created_at?->format('d/m/Y') }}</td>
                        <td class="actions">
                            <div class="actions-icones">
                            <x-bouton-icone icone="pencil-square" libelle="Modifier" wire:click="editer({{ $compte->id }})" />
                            @unless($compte->is(auth()->user()))
                                <x-bouton-icone icone="trash" libelle="Supprimer le compte" variante="danger" wire:click="supprimer({{ $compte->id }})" wire:confirm="Supprimer le compte de {{ $compte->name }} ? La fiche employé est conservée." />
                            @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="vide"><x-icone nom="magnifying-glass" class="mx-auto mb-3 block h-8 w-8 text-bordure-forte" />Aucun compte trouvé.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $comptes->links() }}
    </div>
</x-page>
