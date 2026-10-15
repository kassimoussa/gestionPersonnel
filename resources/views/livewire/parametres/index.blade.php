<x-page titre="Paramètres de l'application" sous-titre="Identité, organisation, temps de travail et documents. Les modifications s'appliquent immédiatement, sans recompilation.">
    @if($information)
        <div class="message-succes">{{ $information }}</div>
    @endif

    <form wire:submit="enregistrer" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="panneau space-y-5">
                <div>
                    <h2 class="panneau-titre">Identité de l'application</h2>
                    <p class="panneau-sous-titre">Nom, logo et couleurs de l'interface.</p>
                </div>
                <div>
                    <label for="nom_application" class="etiquette">Nom de l'application</label>
                    <input id="nom_application" type="text" wire:model.live.debounce.400ms="nom_application" class="champ max-w-md">
                    <p class="aide">Affiché dans l'onglet du navigateur, la navigation et l'écran de connexion.</p>
                    @error('nom_application') <p class="erreur">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="logo" class="etiquette">Logo</label>
                    <div class="flex flex-wrap items-center gap-4">
                        <img src="{{ $apercuLogo ?? (($parametres->logoUrl() && ! $retirerLogo) ? $parametres->logoUrl() : asset('images/logo-anefip.svg')) }}" alt="Aperçu du logo" class="h-14 w-14 rounded-lg border border-bordure bg-white object-contain p-1">
                        <div class="space-y-2">
                            <input id="logo" type="file" wire:model="logo" accept="image/*" class="block text-sm text-ink file:me-3 file:rounded-lg file:border file:border-bordure-forte file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-secondaire hover:file:bg-fond">
                            <div class="flex flex-wrap items-center gap-4">
                                @if($apercuLogo)
                                    <button type="button" wire:click="annulerLogo" class="bouton-lien">Annuler le nouveau logo</button>
                                @endif
                                @if($parametres->logo_path)
                                    <label class="inline-flex items-center gap-2 text-sm text-secondaire">
                                        <input type="checkbox" wire:model.live="retirerLogo" class="case"> Retirer le logo actuel
                                    </label>
                                @endif
                            </div>
                            <p class="aide mt-0">PNG, JPG ou SVG, 1 Mo maximum. Utilisé aussi comme en-tête des PDF. <span wire:loading wire:target="logo">Envoi en cours…</span></p>
                            @error('logo') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="couleur_accent" class="etiquette">Couleur principale</label>
                        <div class="flex items-center gap-2">
                            <input id="couleur_accent" type="color" wire:model.live="couleur_accent" class="h-9 w-12 shrink-0 cursor-pointer rounded-lg border border-bordure-forte bg-white p-0.5" aria-label="Sélecteur de la couleur principale">
                            <span class="w-32"><input type="text" wire:model.live.debounce.300ms="couleur_accent" class="champ" aria-label="Couleur principale en hexadécimal"></span>
                        </div>
                        <p class="aide">Boutons, lien actif, focus des champs.</p>
                        @error('couleur_accent') <p class="erreur">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="couleur_accent_secondaire" class="etiquette">Couleur secondaire</label>
                        <div class="flex items-center gap-2">
                            <input id="couleur_accent_secondaire" type="color" wire:model.live="couleur_accent_secondaire" class="h-9 w-12 shrink-0 cursor-pointer rounded-lg border border-bordure-forte bg-white p-0.5" aria-label="Sélecteur de la couleur secondaire">
                            <span class="w-32"><input type="text" wire:model.live.debounce.300ms="couleur_accent_secondaire" class="champ" aria-label="Couleur secondaire en hexadécimal"></span>
                        </div>
                        <p class="aide">Dégradé du panneau de l'écran de connexion.</p>
                        @error('couleur_accent_secondaire') <p class="erreur">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="panneau space-y-5">
                <div>
                    <h2 class="panneau-titre">Écran de connexion</h2>
                    <p class="panneau-sous-titre">Texte du panneau de marque et création de compte en libre accès.</p>
                </div>
                <div>
                    <label for="titre_connexion" class="etiquette">Accroche</label>
                    <input id="titre_connexion" type="text" wire:model="titre_connexion" class="champ" placeholder="{{ $parametres->titreConnexion() }}">
                    @error('titre_connexion') <p class="erreur">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="texte_connexion" class="etiquette">Texte d'accompagnement</label>
                    <textarea id="texte_connexion" wire:model="texte_connexion" rows="2" class="champ" placeholder="{{ $parametres->texteConnexion() }}"></textarea>
                    @error('texte_connexion') <p class="erreur">{{ $message }}</p> @enderror
                </div>
                <label class="flex items-start gap-3 rounded-lg border border-bordure p-4">
                    <input type="checkbox" wire:model="inscription_ouverte" class="case mt-0.5">
                    <span>
                        <span class="block text-sm font-semibold text-ink">Autoriser l'inscription libre</span>
                        <span class="block text-sm text-tertiaire">Toute personne pourrait créer un compte depuis la page d'inscription. Désactivé par défaut : les comptes sont créés par l'administrateur.</span>
                    </span>
                </label>
            </section>

            <section class="panneau space-y-5">
                <div>
                    <h2 class="panneau-titre">Organisation</h2>
                    <p class="panneau-sous-titre">Le responsable de l'unité choisie est le directeur général : il valide les congés au second niveau et gère la paie.</p>
                </div>
                <div>
                    <label for="direction_generale_id" class="etiquette">Direction générale</label>
                    <select id="direction_generale_id" wire:model="direction_generale_id" class="champ max-w-md">
                        <option value="">Aucune</option>
                        @foreach($directions as $direction)
                            <option value="{{ $direction->id }}">{{ $direction->nom }} · {{ $direction->responsable?->nomComplet() ?? 'poste vacant' }}</option>
                        @endforeach
                    </select>
                    <p class="aide">Pour changer de directeur, modifiez le responsable de cette unité dans Départements.</p>
                    @error('direction_generale_id') <p class="erreur">{{ $message }}</p> @enderror
                </div>
            </section>

            <section class="panneau space-y-5">
                <div>
                    <h2 class="panneau-titre">Temps de travail et congés</h2>
                    <p class="panneau-sous-titre">Utilisés pour la présence, les retards et le décompte des congés. Les jours fériés se règlent sur leur propre écran.</p>
                </div>
                <fieldset>
                    <legend class="etiquette">Jours de weekend</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach($nomsJours as $numero => $libelle)
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium transition {{ in_array((string) $numero, array_map('strval', $jours_weekend), true) ? 'border-accent bg-accent-soft text-accent' : 'border-bordure-forte bg-white text-secondaire hover:bg-fond' }}">
                                <input type="checkbox" wire:model.live="jours_weekend" value="{{ $numero }}" class="case">
                                {{ $libelle }}
                            </label>
                        @endforeach
                    </div>
                    @error('jours_weekend') <p class="erreur">{{ $message }}</p> @enderror
                </fieldset>
                <div class="grid gap-5 sm:grid-cols-3">
                    <div>
                        <label for="heure_debut_travail" class="etiquette">Heure de début de travail</label>
                        <input id="heure_debut_travail" type="time" wire:model="heure_debut_travail" class="champ">
                        @error('heure_debut_travail') <p class="erreur">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="tolerance_retard_minutes" class="etiquette">Tolérance de retard (minutes)</label>
                        <input id="tolerance_retard_minutes" type="number" min="0" max="240" wire:model="tolerance_retard_minutes" class="champ">
                        @error('tolerance_retard_minutes') <p class="erreur">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="jours_conge_par_mois" class="etiquette">Congé annuel acquis par mois (jours)</label>
                        <input id="jours_conge_par_mois" type="number" step="0.25" min="0" max="10" wire:model="jours_conge_par_mois" class="champ">
                        @error('jours_conge_par_mois') <p class="erreur">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="panneau space-y-5">
                <div>
                    <h2 class="panneau-titre">Entreprise et documents PDF</h2>
                    <p class="panneau-sous-titre">En-tête, pied de page et signature de l'organigramme et des bulletins de paie.</p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="nom_legal_entreprise" class="etiquette">Nom légal</label>
                        <input id="nom_legal_entreprise" type="text" wire:model="nom_legal_entreprise" class="champ">
                        @error('nom_legal_entreprise') <p class="erreur">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="adresse_entreprise" class="etiquette">Adresse</label>
                        <textarea id="adresse_entreprise" wire:model="adresse_entreprise" rows="2" class="champ"></textarea>
                        @error('adresse_entreprise') <p class="erreur">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="telephone_entreprise" class="etiquette">Téléphone</label>
                        <input id="telephone_entreprise" type="text" wire:model="telephone_entreprise" class="champ">
                        @error('telephone_entreprise') <p class="erreur">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="email_entreprise" class="etiquette">E-mail</label>
                        <input id="email_entreprise" type="email" wire:model="email_entreprise" class="champ">
                        @error('email_entreprise') <p class="erreur">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="entete_documents" class="etiquette">En-tête officiel des documents</label>
                        <textarea id="entete_documents" wire:model="entete_documents" rows="4" class="champ" placeholder="Une ligne par ligne d'en-tête, ministère de tutelle compris"></textarea>
                        <p class="mt-1 text-xs text-tertiaire">Repris en haut de l'attestation de congé, sous le logo.</p>
                        @error('entete_documents') <p class="erreur">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="signataire_nom" class="etiquette">Signataire des bulletins et attestations</label>
                        <input id="signataire_nom" type="text" wire:model="signataire_nom" class="champ" placeholder="Nom et prénom">
                        @error('signataire_nom') <p class="erreur">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="signataire_fonction" class="etiquette">Fonction du signataire</label>
                        <input id="signataire_fonction" type="text" wire:model="signataire_fonction" class="champ" placeholder="Ex : Directeur général">
                        @error('signataire_fonction') <p class="erreur">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="pied_page_documents" class="etiquette">Pied de page des documents</label>
                        <textarea id="pied_page_documents" wire:model="pied_page_documents" rows="2" class="champ" placeholder="Ex : numéro d'identification fiscale, mentions légales"></textarea>
                        @error('pied_page_documents') <p class="erreur">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <div class="flex items-center gap-3">
                <button type="submit" class="bouton-principal" wire:loading.attr="disabled" wire:target="enregistrer, logo">Enregistrer les paramètres</button>
                <span class="text-sm text-tertiaire" wire:loading wire:target="enregistrer">Enregistrement…</span>
            </div>
        </div>

        <aside class="lg:col-span-1">
            <div class="space-y-3 lg:sticky lg:top-8">
                <h2 class="panneau-titre">Aperçu</h2>
                <div class="panneau space-y-4" style="--color-accent: {{ $apercuCouleur }}; --color-accent-light: {{ $apercuCouleurClaire }}; --color-accent-soft: {{ $apercuCouleurDouce }};">
                    <div class="flex items-center gap-3">
                        <img src="{{ $apercuLogo ?? (($parametres->logoUrl() && ! $retirerLogo) ? $parametres->logoUrl() : asset('images/logo-anefip.svg')) }}" alt="" class="h-9 w-9 object-contain">
                        <span class="text-base font-semibold text-ink">{{ $nom_application ?: 'Nom de l\'application' }}</span>
                    </div>
                    <div class="space-y-1">
                        <span class="lien-sidebar lien-sidebar-actif"><x-icone nom="home" />Lien actif</span>
                        <span class="lien-sidebar"><x-icone nom="users" />Autre lien</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="bouton-principal">Action principale</span>
                        <span class="bouton-secondaire">Secondaire</span>
                    </div>
                    <div class="h-16 rounded-lg" style="background-image: linear-gradient(135deg, {{ $apercuCouleur }} 0%, {{ $apercuCouleurSecondaire }} 100%);" aria-hidden="true"></div>
                    <p class="text-xs text-tertiaire">Principale {{ $apercuCouleur }}, claire {{ $apercuCouleurClaire }}, secondaire {{ $apercuCouleurSecondaire }}.</p>
                </div>
            </div>
        </aside>
    </form>
</x-page>
