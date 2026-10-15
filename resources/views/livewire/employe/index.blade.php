@use('App\Models\Employe')
<x-page titre="Employés" sous-titre="Dossiers du personnel : identité, situation, coordonnées, poste et données administratives.">
    <x-slot name="actions">
        <div class="champ-icone w-full sm:w-72">
            <x-icone nom="magnifying-glass" />
            <input type="search" wire:model.live.debounce.400ms="recherche" placeholder="Rechercher nom, poste, matricule..." class="champ" aria-label="Rechercher un employé">
        </div>
        <div class="w-full sm:w-48">
            <select wire:model.live="filtreStatut" class="champ" aria-label="Filtrer par statut">
                <option value="tous">Tous les employés</option>
                <option value="actifs">Actifs seulement</option>
                <option value="inactifs">Inactifs seulement</option>
            </select>
        </div>
        @if($peutGerer)
            <button wire:click="nouveau" class="bouton-principal w-full sm:w-auto"><x-icone nom="plus" class="h-4 w-4" />Ajouter un employé</button>
        @endif
    </x-slot>

    @unless($peutGerer)
        <div class="message-info">Vous consultez les dossiers des employés de votre équipe. Leur modification revient au service RH.</div>
    @endunless

    @if($formulaireOuvert)
        <x-modale contexte="Employés" :titre="$editId ? 'Modifier l\'employé' : 'Nouvel employé'" sous-titre="Dossier du personnel : identité, coordonnées, poste et données administratives." largeur="xl">
            <form wire:submit="enregistrer">
                <div class="modale-corps">
                    <x-section-formulaire titre="Identité" description="État civil de l'employé et photo affichée dans l'application.">
                        <div class="flex flex-wrap items-center gap-5 sm:col-span-6">
                            <x-avatar :nom="trim($prenom.' '.$nom) ?: '?'" :photo="$apercuPhoto ?? $photoActuelle" taille="xl" />
                            <div class="space-y-2">
                                <p class="text-sm font-medium text-secondaire">Photo</p>
                                <div class="flex flex-wrap items-center gap-2">
                                    <input id="employe-photo" type="file" wire:model="photo" accept="image/*" class="sr-only">
                                    <label for="employe-photo" class="bouton-secondaire cursor-pointer"><x-icone nom="camera" class="h-4 w-4" />{{ ($apercuPhoto || $photoActuelle) ? 'Changer la photo' : 'Choisir une photo' }}</label>
                                    @if($apercuPhoto)
                                        <button type="button" wire:click="annulerPhoto" class="bouton-lien">Annuler</button>
                                    @elseif($editId && $photoActuelle)
                                        <button type="button" wire:click="$set('retirerPhoto', true)" class="bouton-lien-danger">Retirer</button>
                                    @elseif($retirerPhoto)
                                        <button type="button" wire:click="$set('retirerPhoto', false)" class="bouton-lien">Conserver la photo</button>
                                    @endif
                                </div>
                                <p class="aide mt-0">JPG ou PNG, 2 Mo maximum. <span wire:loading wire:target="photo">Envoi en cours…</span></p>
                                @error('photo') <p class="erreur">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="sm:col-span-3">
                            <label for="employe-prenom" class="etiquette">Prénom *</label>
                            <input id="employe-prenom" type="text" wire:model.live.debounce.500ms="prenom" class="champ" autocomplete="given-name">
                            @error('prenom') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="employe-nom" class="etiquette">Nom *</label>
                            <input id="employe-nom" type="text" wire:model.live.debounce.500ms="nom" class="champ" autocomplete="family-name">
                            @error('nom') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <span class="etiquette">Sexe</span>
                            <div class="segment" role="radiogroup" aria-label="Sexe">
                                <label><input type="radio" wire:model="sexe" value="" class="sr-only">Non précisé</label>
                                @foreach(Employe::SEXES as $valeur => $libelle)
                                    <label><input type="radio" wire:model="sexe" value="{{ $valeur }}" class="sr-only">{{ $libelle }}</label>
                                @endforeach
                            </div>
                            @error('sexe') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="employe-matricule" class="etiquette">Matricule</label>
                            <div class="champ-icone">
                                <x-icone nom="identification" />
                                <input id="employe-matricule" type="text" wire:model="matricule" class="champ" placeholder="1001">
                            </div>
                            <p class="aide">Même numéro que l'identifiant dans la pointeuse.</p>
                            @error('matricule') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="employe-naissance" class="etiquette">Date de naissance</label>
                            <input id="employe-naissance" type="date" wire:model="date_naissance" max="{{ now()->subDay()->toDateString() }}" class="champ">
                            @error('date_naissance') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="employe-lieu-naissance" class="etiquette">Lieu de naissance</label>
                            <div class="champ-icone">
                                <x-icone nom="map-pin" />
                                <input id="employe-lieu-naissance" type="text" wire:model="lieu_naissance" class="champ" placeholder="Djibouti">
                            </div>
                            @error('lieu_naissance') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="employe-nationalite" class="etiquette">Nationalité</label>
                            <input id="employe-nationalite" type="text" wire:model="nationalite" class="champ" placeholder="Djiboutienne">
                            @error('nationalite') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>

                    <x-section-formulaire titre="Situation familiale" description="Informations utiles au dossier administratif.">
                        <div class="sm:col-span-4">
                            <label for="employe-situation" class="etiquette">Situation familiale</label>
                            <select id="employe-situation" wire:model="situation_familiale" class="champ">
                                <option value="">Non renseignée</option>
                                @foreach(Employe::SITUATIONS_FAMILIALES as $valeur => $libelle)
                                    <option value="{{ $valeur }}">{{ $libelle }}</option>
                                @endforeach
                            </select>
                            @error('situation_familiale') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="employe-enfants" class="etiquette">Enfants à charge</label>
                            <input id="employe-enfants" type="number" min="0" max="30" wire:model="nombre_enfants" class="champ" placeholder="0">
                            @error('nombre_enfants') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>

                    <x-section-formulaire titre="Coordonnées" description="Moyens de joindre l'employé et une personne à prévenir en cas d'urgence.">
                        <div class="sm:col-span-3">
                            <label for="employe-email" class="etiquette">E-mail</label>
                            <div class="champ-icone">
                                <x-icone nom="envelope" />
                                <input id="employe-email" type="email" wire:model="email" class="champ" autocomplete="email" placeholder="prenom.nom@anefip.dj">
                            </div>
                            @error('email') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="employe-telephone" class="etiquette">Téléphone</label>
                            <div class="champ-icone">
                                <x-icone nom="phone" />
                                <input id="employe-telephone" type="tel" wire:model="telephone" class="champ" autocomplete="tel" placeholder="+253 77 00 00 00">
                            </div>
                            @error('telephone') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-6">
                            <label for="employe-adresse" class="etiquette">Adresse</label>
                            <textarea id="employe-adresse" wire:model="adresse" rows="2" class="champ" placeholder="Quartier, rue, ville"></textarea>
                            @error('adresse') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="employe-urgence-nom" class="etiquette">Contact d'urgence</label>
                            <div class="champ-icone">
                                <x-icone nom="user" />
                                <input id="employe-urgence-nom" type="text" wire:model="contact_urgence_nom" class="champ" placeholder="Nom et lien de parenté">
                            </div>
                            @error('contact_urgence_nom') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="employe-urgence-telephone" class="etiquette">Téléphone du contact</label>
                            <div class="champ-icone">
                                <x-icone nom="phone" />
                                <input id="employe-urgence-telephone" type="tel" wire:model="contact_urgence_telephone" class="champ">
                            </div>
                            @error('contact_urgence_telephone') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>

                    <x-section-formulaire titre="Poste" description="Fonction, rattachement dans l'organigramme et identifiant de la pointeuse.">
                        <div class="sm:col-span-3">
                            <label for="employe-poste" class="etiquette">Poste</label>
                            <div class="champ-icone">
                                <x-icone nom="briefcase" />
                                <input id="employe-poste" type="text" wire:model="poste" class="champ" placeholder="Chargée RH">
                            </div>
                            @error('poste') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="employe-categorie" class="etiquette">Catégorie ou grade</label>
                            <input id="employe-categorie" type="text" wire:model="categorie" class="champ" placeholder="Cadre">
                            @error('categorie') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="employe-departement" class="etiquette">Département</label>
                            <select id="employe-departement" wire:model="departement_id" class="champ">
                                <option value="">Aucun département</option>
                                @foreach($departements as $departement)
                                    <option value="{{ $departement->id }}">{{ $departement->nom }}</option>
                                @endforeach
                            </select>
                            @error('departement_id') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="employe-embauche" class="etiquette">Date d'embauche</label>
                            <input id="employe-embauche" type="date" wire:model="date_embauche" class="champ">
                            @error('date_embauche') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="employe-matricule-pointeuse" class="etiquette">Matricule pointeuse</label>
                            <div class="champ-icone">
                                <x-icone nom="finger-print" />
                                <input id="employe-matricule-pointeuse" type="text" wire:model="matricule_pointeuse" placeholder="Identifiant dans la machine" class="champ">
                            </div>
                            <p class="aide">Laissé vide, il reprend le matricule.</p>
                            @error('matricule_pointeuse') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="border-t border-bordure pt-5 sm:col-span-6">
                            <x-interrupteur id="actif" wire:model="actif" titre="Employé actif" description="Un employé inactif n'apparaît plus dans l'organigramme, la présence ni la paie." />
                        </div>
                    </x-section-formulaire>

                    <x-section-formulaire titre="Congés" :description="'Compteur de congé annuel pour '.now()->year.'. Il se remplit tout seul de '.number_format($joursCongeParMois, 1, ',', ' ').' jour(s) par mois ; corrigez-le ici en cas de reprise d\'un historique papier ou d\'accord particulier.'">
                        <div class="sm:col-span-3">
                            <label for="employe-solde-conge" class="etiquette">Jours acquis en {{ now()->year }}</label>
                            <div class="champ-unite">
                                <input id="employe-solde-conge" type="text" inputmode="decimal" wire:model="jours_conge_acquis" placeholder="Ex : 22,5" class="champ">
                                <span>jours</span>
                            </div>
                            <p class="aide">Droits crédités au titre de cette année seule : le reliquat des années précédentes s'y ajoute automatiquement. Pour reprendre un historique papier, additionnez-y ce reliquat. Le crédit automatique reprend le mois suivant.</p>
                            @error('jours_conge_acquis') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        @if($editId)
                            <div class="sm:col-span-3">
                                <p class="etiquette">Déjà posé cette année</p>
                                <p class="mt-2 text-lg font-semibold text-ink">{{ number_format($joursCongePris, 1, ',', ' ') }} j</p>
                                <p class="aide">Congés annuels approuvés ou en attente, déduits du solde disponible.</p>
                            </div>
                        @endif
                    </x-section-formulaire>

                    @if($voitDonneesSensibles)
                        <x-section-formulaire titre="Administratif et paie" description="Données sensibles, visibles uniquement par la DG et l'administrateur.">
                            <div class="sm:col-span-3">
                                <label for="employe-piece" class="etiquette">Numéro de pièce d'identité</label>
                                <div class="champ-icone">
                                    <x-icone nom="identification" />
                                    <input id="employe-piece" type="text" wire:model="numero_piece_identite" class="champ">
                                </div>
                                @error('numero_piece_identite') <p class="erreur">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-3">
                                <label for="employe-cnss" class="etiquette">Numéro d'immatriculation CNSS</label>
                                <input id="employe-cnss" type="text" wire:model="numero_cnss" class="champ">
                                @error('numero_cnss') <p class="erreur">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-3">
                                <label for="employe-banque" class="etiquette">Banque</label>
                                <div class="champ-icone">
                                    <x-icone nom="building-library" />
                                    <input id="employe-banque" type="text" wire:model="banque" class="champ">
                                </div>
                                @error('banque') <p class="erreur">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-3">
                                <label for="employe-compte" class="etiquette">Numéro de compte</label>
                                <div class="champ-icone">
                                    <x-icone nom="lock-closed" />
                                    <input id="employe-compte" type="text" wire:model="numero_compte_bancaire" class="champ">
                                </div>
                                @error('numero_compte_bancaire') <p class="erreur">{{ $message }}</p> @enderror
                            </div>
                        </x-section-formulaire>
                    @endif
                </div>
                <div class="modale-pied">
                    <p class="text-sm text-tertiaire">* Champs obligatoires</p>
                    <div class="modale-pied-actions">
                        <button type="button" wire:click="annuler" class="bouton-secondaire">Annuler</button>
                        <button type="submit" class="bouton-principal" wire:loading.attr="disabled" wire:target="enregistrer, photo">{{ $editId ? 'Enregistrer les modifications' : 'Créer l\'employé' }}</button>
                    </div>
                </div>
            </form>
        </x-modale>
    @endif

    @error('suppression')
        <div class="message-erreur">{{ $message }}</div>
    @enderror

    @if($information)
        <div class="message-succes">{{ $information }}</div>
    @endif

    <div class="tableau-conteneur">
        <table class="tableau">
            <thead>
                <tr>
                    <x-entete-triable colonne="nom" :tri="$tri" :sens="$sens">Nom</x-entete-triable>
                    <x-entete-triable colonne="matricule" :tri="$tri" :sens="$sens">Matricule</x-entete-triable>
                    <x-entete-triable colonne="poste" :tri="$tri" :sens="$sens">Poste</x-entete-triable>
                    <x-entete-triable colonne="departement" :tri="$tri" :sens="$sens">Département</x-entete-triable>
                    <x-entete-triable colonne="date_embauche" :tri="$tri" :sens="$sens">Embauché le</x-entete-triable>
                    <x-entete-triable colonne="actif" :tri="$tri" :sens="$sens">Statut</x-entete-triable>
                    @if($peutGerer)
                        <th class="actions">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($employes as $employe)
                    <tr wire:key="employe-{{ $employe->id }}">
                        <td>
                            <div class="identite">
                                <x-avatar :nom="$employe->nomComplet()" :photo="$employe->photoUrl()" />
                                <div class="min-w-0">
                                    <a href="{{ route('employes.fiche', $employe) }}" wire:navigate class="identite-nom hover:text-accent">{{ $employe->nomComplet() }}</a>
                                    <span class="sous-ligne truncate">{{ $employe->email ?? '—' }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="secondaire tabular-nums">{{ $employe->matricule ?? '—' }}</td>
                        <td>
                            {{ $employe->poste }}
                            @if($employe->categorie)
                                <span class="sous-ligne">{{ $employe->categorie }}</span>
                            @endif
                        </td>
                        <td class="secondaire">{{ $employe->departement?->nom ?? '—' }}</td>
                        <td class="secondaire">{{ $employe->date_embauche?->format('d/m/Y') }}</td>
                        <td>
                            <x-badge :variante="$employe->actif ? 'succes' : 'neutre'">{{ $employe->actif ? 'Actif' : 'Inactif' }}</x-badge>
                        </td>
                        @if($peutGerer)
                        <td class="actions">
                            <div class="actions-icones">
                                <x-bouton-icone icone="identification" libelle="Ouvrir la fiche" :href="route('employes.fiche', $employe)" wire:navigate />
                                <x-bouton-icone icone="pencil-square" libelle="Modifier" wire:click="editer({{ $employe->id }})" />
                                @if($employe->actif)
                                    <x-bouton-icone icone="x-mark" libelle="Désactiver" wire:click="basculerActivite({{ $employe->id }})" wire:confirm="Désactiver {{ $employe->nomComplet() }} ? Sa fiche et ses bulletins sont conservés, mais il sort de l'organigramme, de la présence et des prochaines paies." />
                                @else
                                    <x-bouton-icone icone="check-badge" libelle="Réactiver" variante="succes" wire:click="basculerActivite({{ $employe->id }})" wire:confirm="Réactiver {{ $employe->nomComplet() }} ?" />
                                @endif
                                {{-- Un employé ayant des fiches de paie ne peut pas être supprimé : le bouton n'est pas proposé. --}}
                                @if($employe->fiches_paie_count === 0)
                                    <x-bouton-icone icone="trash" libelle="Supprimer" variante="danger" wire:click="supprimer({{ $employe->id }})" wire:confirm="Êtes-vous sûr de vouloir supprimer cet employé ?" />
                                @endif
                            </div>
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="vide"><x-icone nom="magnifying-glass" class="mx-auto mb-3 block h-8 w-8 text-bordure-forte" />Aucun employé trouvé.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $employes->links() }}
    </div>
</x-page>
