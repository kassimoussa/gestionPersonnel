@use('App\Models\Employe')
<x-page :titre="$employe->nomComplet()" :sous-titre="collect([$employe->poste, $employe->departement?->nom])->filter()->join(' · ') ?: 'Fiche du personnel'">
    <x-slot name="actions">
        <a href="{{ route('employes.index') }}" wire:navigate class="bouton-secondaire">Retour à la liste</a>
        @if($peutModifier)
            <a href="{{ route('employes.index', ['editer' => $employe->id]) }}" wire:navigate class="bouton-principal"><x-icone nom="pencil-square" class="h-4 w-4" />Modifier la fiche</a>
        @endif
    </x-slot>

    @if($information)
        <div class="message-succes">{{ $information }}</div>
    @endif

    {{-- Identité --}}
    <div class="panneau">
        <div class="flex flex-wrap items-center gap-5">
            <x-avatar :nom="$employe->nomComplet()" :photo="$employe->photoUrl()" taille="xl" />
            <div class="min-w-0 flex-1">
                <p class="text-xl font-semibold text-ink">{{ $employe->nomComplet() }}</p>
                <p class="text-sm text-tertiaire">{{ collect([$employe->matricule ? 'Matricule '.$employe->matricule : null, $employe->email, $employe->telephone])->filter()->join(' · ') ?: 'Coordonnées non renseignées' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-badge :variante="$employe->actif ? 'succes' : 'neutre'">{{ $employe->actif ? 'Actif' : 'Inactif' }}</x-badge>
                @if($employe->departementsDiriges()->exists())
                    <x-badge variante="info" :point="false">Responsable d'unité</x-badge>
                @endif
                @if($employe->user)
                    <x-badge variante="neutre" :point="false">Compte : {{ $employe->user->email }}</x-badge>
                @else
                    <x-badge variante="attention" :point="false">Sans compte utilisateur</x-badge>
                @endif
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- État civil et coordonnées --}}
        <div class="panneau lg:col-span-2">
            <p class="panneau-titre">État civil et coordonnées</p>
            <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium text-tertiaire">Date de naissance</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $employe->date_naissance?->format('d/m/Y') ?? '—' }}@if($employe->age()) <span class="text-tertiaire">({{ $employe->age() }} ans)</span>@endif</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-tertiaire">Lieu de naissance</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $employe->lieu_naissance ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-tertiaire">Nationalité</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $employe->nationalite ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-tertiaire">Sexe</dt>
                    <dd class="mt-1 text-sm text-ink">{{ Employe::SEXES[$employe->sexe] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-tertiaire">Situation familiale</dt>
                    <dd class="mt-1 text-sm text-ink">{{ Employe::SITUATIONS_FAMILIALES[$employe->situation_familiale] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-tertiaire">Enfants</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $employe->nombre_enfants ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-tertiaire">Adresse</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $employe->adresse ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-tertiaire">Contact d'urgence</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $employe->contact_urgence_nom ?? '—' }}<span class="sous-ligne">{{ $employe->contact_urgence_telephone }}</span></dd>
                </div>
            </dl>
        </div>

        {{-- Poste --}}
        <div class="panneau">
            <p class="panneau-titre">Poste</p>
            <dl class="mt-4 space-y-4">
                <div>
                    <dt class="text-xs font-medium text-tertiaire">Fonction</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $employe->poste ?? '—' }}@if($employe->categorie) <span class="text-tertiaire">· {{ $employe->categorie }}</span>@endif</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-tertiaire">Unité</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $employe->departement?->nom ?? 'Sans unité' }}<span class="sous-ligne">Responsable : {{ $employe->departement?->responsable?->nomComplet() ?? 'poste vacant' }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-tertiaire">Date d'embauche</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $employe->date_embauche?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                @if($voitDonneesSensibles)
                    <div>
                        <dt class="text-xs font-medium text-tertiaire">Contrat en cours</dt>
                        <dd class="mt-1 text-sm text-ink">
                            @if($contrat)
                                {{ $contrat->type }} depuis le {{ $contrat->date_debut->format('d/m/Y') }}
                                <span class="sous-ligne">{{ \App\Models\FichePaie::montant($contrat->salaire_base) }}</span>
                            @else
                                <span class="text-tertiaire">Aucun contrat en cours</span>
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    @if($voitDonneesSensibles)
        <div class="panneau">
            <p class="panneau-titre">Administratif et paie</p>
            <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div>
                    <dt class="text-xs font-medium text-tertiaire">Pièce d'identité</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $employe->numero_piece_identite ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-tertiaire">Numéro CNSS</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $employe->numero_cnss ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-tertiaire">Banque</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $employe->banque ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-tertiaire">Compte bancaire</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $employe->numero_compte_bancaire ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    @endif

    {{-- Congés et présence --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="panneau">
            <p class="panneau-titre">Congés {{ $annee }}</p>
            <div class="mt-4 grid grid-cols-2 gap-3 text-center sm:grid-cols-4">
                <div class="rounded-lg bg-fond p-3">
                    <p class="text-xs text-tertiaire">Report</p>
                    <p class="text-lg font-semibold text-ink">{{ number_format($soldeConge['reporte'], 1, ',', ' ') }} j</p>
                </div>
                <div class="rounded-lg bg-fond p-3">
                    <p class="text-xs text-tertiaire">Acquis {{ $annee }}</p>
                    <p class="text-lg font-semibold text-ink">{{ number_format($soldeConge['acquis'], 1, ',', ' ') }} j</p>
                </div>
                <div class="rounded-lg bg-fond p-3">
                    <p class="text-xs text-tertiaire">Pris {{ $annee }}</p>
                    <p class="text-lg font-semibold text-ink">{{ number_format($soldeConge['approuves'], 1, ',', ' ') }} j</p>
                </div>
                <div class="rounded-lg bg-fond p-3">
                    <p class="text-xs text-tertiaire">Disponible</p>
                    <p class="text-lg font-semibold text-accent">{{ number_format($soldeConge['disponible'], 1, ',', ' ') }} j</p>
                </div>
            </div>
            <ul class="mt-4 space-y-2">
                @forelse($congesRecents as $conge)
                    <li class="flex items-center justify-between gap-3 border-t border-bordure pt-2 text-sm">
                        <span class="min-w-0">
                            <span class="block font-medium text-ink">{{ $conge->type->libelle() }}</span>
                            <span class="sous-ligne">du {{ $conge->date_debut->format('d/m/Y') }} au {{ $conge->date_fin->format('d/m/Y') }} · {{ number_format($conge->nombre_jours, 1, ',', ' ') }} j</span>
                        </span>
                        <span class="flex items-center gap-1">
                            <x-badge :variante="$conge->statut->varianteBadge()">{{ $conge->statut->libelle() }}</x-badge>
                            @can('telechargerLesDocuments', $conge)
                                <x-bouton-icone icone="document-text" libelle="Formulaire de demande" :href="route('conges.formulaire', $conge)" />
                                @if($conge->attestationEtablie())
                                    <x-bouton-icone icone="document-duplicate" libelle="Attestation de congé" variante="accent" :href="route('conges.attestation', $conge)" />
                                @endif
                            @endcan
                        </span>
                    </li>
                @empty
                    <li class="border-t border-bordure pt-3 text-sm text-tertiaire">Aucune demande enregistrée.</li>
                @endforelse
            </ul>
        </div>

        <div class="panneau">
            <p class="panneau-titre">Présence · {{ $libelleMois }}</p>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                <div class="rounded-lg bg-fond p-3 text-center">
                    <p class="text-xs text-tertiaire">Présences</p>
                    <p class="text-lg font-semibold text-ink">{{ $presence['present'] + $presence['retard'] }}</p>
                </div>
                <div class="rounded-lg bg-fond p-3 text-center">
                    <p class="text-xs text-tertiaire">Retards</p>
                    <p class="text-lg font-semibold {{ $presence['retard'] > 0 ? 'text-attention' : 'text-ink' }}">{{ $presence['retard'] }}</p>
                </div>
                <div class="rounded-lg bg-fond p-3 text-center">
                    <p class="text-xs text-tertiaire">Congés</p>
                    <p class="text-lg font-semibold text-ink">{{ $presence['conge'] }}</p>
                </div>
                <div class="rounded-lg bg-fond p-3 text-center">
                    <p class="text-xs text-tertiaire">Absences</p>
                    <p class="text-lg font-semibold {{ $presence['absence'] > 0 ? 'text-danger' : 'text-ink' }}">{{ $presence['absence'] }}</p>
                </div>
                <div class="rounded-lg bg-fond p-3 text-center">
                    <p class="text-xs text-tertiaire">Justifiées</p>
                    <p class="text-lg font-semibold text-ink">{{ $presence['absence_justifiee'] }}</p>
                </div>
            </div>
            @unless($employe->actif)
                <p class="mt-3 text-sm text-tertiaire">Employé inactif : la présence n'est plus suivie.</p>
            @endunless
        </div>
    </div>

    {{-- Bulletins de paie --}}
    @if($peutVoirLaPaie)
        <div class="panneau">
            <p class="panneau-titre">Bulletins de paie</p>
            <p class="panneau-sous-titre">Six derniers bulletins de l'agent. L'historique complet est dans le module Paie.</p>

            <div class="tableau-conteneur mt-4">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>Période</th>
                            <th class="montant">Brut</th>
                            <th class="montant">Net à payer</th>
                            <th>Statut</th>
                            <th class="actions">Bulletin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bulletins as $bulletin)
                            <tr wire:key="bulletin-{{ $bulletin->id }}">
                                <td class="font-medium">{{ $bulletin->periodeLibelle() }}</td>
                                <td class="montant secondaire">{{ \App\Models\FichePaie::montant($bulletin->brut) }}</td>
                                <td class="montant font-medium">{{ \App\Models\FichePaie::montant($bulletin->net_a_payer) }}</td>
                                <td><x-badge :variante="$bulletin->statut->varianteBadge()">{{ $bulletin->statut->libelle() }}</x-badge></td>
                                <td class="actions">
                                    <div class="actions-icones">
                                        <x-bouton-icone icone="arrow-down-tray" libelle="Télécharger le bulletin" :href="route('paie.bulletin', $bulletin)" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="vide"><x-icone nom="banknotes" class="mx-auto mb-3 block h-8 w-8 text-bordure-forte" />Aucun bulletin pour cet agent.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Discipline et parcours --}}
    @if($peutVoirLesEvenements)
        <div class="panneau">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="panneau-titre">Discipline et parcours</p>
                    <p class="panneau-sous-titre">Sanctions prononcées et faits de carrière consignés au dossier. Une mise à pied apparaît dans le rapport de présence sur toute sa période.</p>
                </div>
                @if($peutEnregistrerUnEvenement)
                    <button wire:click="ajouterUnEvenement" class="bouton-secondaire"><x-icone nom="plus" class="h-4 w-4" />Consigner un événement</button>
                @endif
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 text-center sm:grid-cols-3">
                <div class="rounded-lg bg-fond p-3">
                    <p class="text-xs text-tertiaire">Mises à pied</p>
                    <p class="text-lg font-semibold {{ $bilanMisesAPied['nombre'] > 0 ? 'text-danger' : 'text-ink' }}">{{ $bilanMisesAPied['nombre'] }}</p>
                </div>
                <div class="rounded-lg bg-fond p-3">
                    <p class="text-xs text-tertiaire">Jours de suspension</p>
                    <p class="text-lg font-semibold text-ink">{{ $bilanMisesAPied['jours'] }}</p>
                </div>
                <div class="rounded-lg bg-fond p-3">
                    <p class="text-xs text-tertiaire">Sanctions au total</p>
                    <p class="text-lg font-semibold text-ink">{{ $nombreSanctions }}</p>
                </div>
            </div>

            <div class="tableau-conteneur mt-4">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>Événement</th>
                            <th>Période</th>
                            <th>Motif</th>
                            <th>Consigné</th>
                            <th class="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($evenements as $evenement)
                            <tr wire:key="evenement-{{ $evenement->id }}">
                                <td>
                                    <x-badge :variante="$evenement->type->varianteBadge()" :point="false">{{ $evenement->type->libelle() }}</x-badge>
                                    @if($evenement->reference)
                                        <span class="sous-ligne">Réf. {{ $evenement->reference }}</span>
                                    @endif
                                </td>
                                <td class="secondaire whitespace-nowrap">
                                    {{ $evenement->periode() }}
                                    @if($evenement->type->suspendLeTravail())
                                        <span class="sous-ligne">{{ $evenement->nombreJours() }} jour(s){{ $evenement->avec_solde === null ? '' : ($evenement->avec_solde ? ', avec solde' : ', sans solde') }}</span>
                                    @endif
                                </td>
                                <td class="secondaire max-w-xs text-xs">{{ $evenement->motif }}</td>
                                <td class="secondaire text-xs">
                                    {{ $evenement->created_at?->format('d/m/Y') }}
                                    @if($evenement->enregistrePar)
                                        <span class="sous-ligne">par {{ $evenement->enregistrePar->name }}</span>
                                    @endif
                                </td>
                                <td class="actions">
                                    <div class="actions-icones">
                                        @if($evenement->aUnePiece())
                                            <x-bouton-icone icone="document-text" libelle="Ouvrir la décision" :href="route('employes.evenement.piece', $evenement)" />
                                        @endif
                                        @if($peutEnregistrerUnEvenement)
                                            <x-bouton-icone icone="trash" libelle="Retirer du dossier" variante="danger" wire:click="supprimerEvenement({{ $evenement->id }})" wire:confirm="Retirer cet événement du dossier ? La pièce jointe sera supprimée." />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="vide"><x-icone nom="check-badge" class="mx-auto mb-3 block h-8 w-8 text-bordure-forte" />Aucun événement au dossier.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($formulaireEvenement)
        <x-modale contexte="Dossier employé" titre="Consigner un événement" :sous-titre="'Sanction ou fait de carrière au dossier de '.$employe->nomComplet().'.'" fermer="annulerEvenement" largeur="md">
            <form wire:submit="enregistrerEvenement">
                <div class="modale-corps">
                    <x-section-formulaire titre="Nature" description="Les sanctions se comptent ; la mise à pied suspend le travail sur une période.">
                        <div class="sm:col-span-6">
                            <label for="evenement-type" class="etiquette">Type *</label>
                            <select id="evenement-type" wire:model.live="evenementType" class="champ">
                                <optgroup label="Sanctions disciplinaires">
                                    @foreach($typesSanction as $type)
                                        <option value="{{ $type->value }}">{{ $type->libelle() }}</option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Faits de carrière">
                                    @foreach($typesCarriere as $type)
                                        <option value="{{ $type->value }}">{{ $type->libelle() }}</option>
                                    @endforeach
                                </optgroup>
                            </select>
                            @error('evenementType') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>

                    <x-section-formulaire titre="Période" description="Une mise à pied court du premier au dernier jour de suspension.">
                        <div class="sm:col-span-3">
                            <label for="evenement-debut" class="etiquette">Date *</label>
                            <input id="evenement-debut" type="date" wire:model="evenementDebut" class="champ">
                            @error('evenementDebut') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        @if($evenementType === 'mise_a_pied')
                            <div class="sm:col-span-3">
                                <label for="evenement-fin" class="etiquette">Dernier jour</label>
                                <input id="evenement-fin" type="date" wire:model="evenementFin" class="champ">
                                @error('evenementFin') <p class="erreur">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-6">
                                <span class="etiquette">Rémunération</span>
                                <div class="segment" role="radiogroup" aria-label="Rémunération pendant la suspension">
                                    <label><input type="radio" wire:model="evenementAvecSolde" value="" class="sr-only">Non précisé</label>
                                    <label><input type="radio" wire:model="evenementAvecSolde" value="avec" class="sr-only">Avec solde</label>
                                    <label><input type="radio" wire:model="evenementAvecSolde" value="sans" class="sr-only">Sans solde</label>
                                </div>
                            </div>
                        @endif
                    </x-section-formulaire>

                    <x-section-formulaire titre="Décision" description="Le motif et la référence de la note ou de la décision.">
                        <div class="sm:col-span-6">
                            <label for="evenement-motif" class="etiquette">Motif *</label>
                            <textarea id="evenement-motif" wire:model="evenementMotif" rows="3" class="champ" placeholder="Ex : absences répétées non justifiées"></textarea>
                            @error('evenementMotif') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="evenement-reference" class="etiquette">Référence</label>
                            <input id="evenement-reference" type="text" wire:model="evenementReference" class="champ" placeholder="Ex : Décision n° 12/ANEFIP">
                            @error('evenementReference') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-6">
                            <input id="evenement-piece" type="file" wire:model="evenementPiece" accept=".pdf,.doc,.docx,image/*" class="sr-only">
                            <label for="evenement-piece" class="zone-fichier">
                                <x-icone nom="document-arrow-up" class="h-6 w-6 text-tertiaire" />
                                <span class="text-sm font-medium text-ink">{{ $evenementPiece ? $evenementPiece->getClientOriginalName() : 'Joindre la décision signée' }}</span>
                                <span class="text-xs text-tertiaire">PDF, image ou Word, 2 Mo maximum. <span wire:loading wire:target="evenementPiece">Envoi en cours…</span></span>
                            </label>
                            @error('evenementPiece') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>
                </div>
                <div class="modale-pied">
                    <p class="text-sm text-tertiaire">* Champs obligatoires</p>
                    <div class="modale-pied-actions">
                        <button type="button" wire:click="annulerEvenement" class="bouton-secondaire">Annuler</button>
                        <button type="submit" class="bouton-principal" wire:loading.attr="disabled" wire:target="enregistrerEvenement, evenementPiece">Consigner</button>
                    </div>
                </div>
            </form>
        </x-modale>
    @endif

    {{-- Documents --}}
    @if($peutVoirLesDocuments)
        <div class="panneau">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="panneau-titre">Documents du dossier</p>
                    <p class="panneau-sous-titre">Contrat signé, diplômes, pièce d'identité, courriers. Accessibles à l'employé et au service du personnel.</p>
                </div>
                @if($peutAjouterUnDocument)
                    <button wire:click="ajouterUnDocument" class="bouton-secondaire"><x-icone nom="plus" class="h-4 w-4" />Ajouter un document</button>
                @endif
            </div>

            <div class="tableau-conteneur mt-4">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Ajouté</th>
                            <th class="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $document)
                            <tr wire:key="document-{{ $document->id }}">
                                <td>
                                    <span class="font-medium text-ink">{{ $document->libelle }}</span>
                                    <span class="sous-ligne">{{ $document->fichier_nom }} · {{ $document->taille() }}</span>
                                </td>
                                <td><x-badge :variante="$document->type->varianteBadge()" :point="false">{{ $document->type->libelle() }}</x-badge></td>
                                <td class="secondaire whitespace-nowrap">{{ $document->date_document?->format('d/m/Y') ?? '—' }}</td>
                                <td class="secondaire text-xs">
                                    {{ $document->created_at?->format('d/m/Y') }}
                                    @if($document->ajoutePar)
                                        <span class="sous-ligne">par {{ $document->ajoutePar->name }}</span>
                                    @endif
                                </td>
                                <td class="actions">
                                    <div class="actions-icones">
                                        <x-bouton-icone icone="arrow-down-tray" libelle="Télécharger" :href="route('employes.document', $document)" />
                                        @if($peutAjouterUnDocument)
                                            <x-bouton-icone icone="trash" libelle="Retirer du dossier" variante="danger" wire:click="supprimerDocument({{ $document->id }})" wire:confirm="Retirer « {{ $document->libelle }} » du dossier ? Le fichier sera supprimé." />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="vide"><x-icone nom="document-text" class="mx-auto mb-3 block h-8 w-8 text-bordure-forte" />Aucun document dans ce dossier.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($formulaireDocument)
            <x-modale contexte="Dossier employé" titre="Ajouter un document" :sous-titre="'Pièce rattachée au dossier de '.$employe->nomComplet().'.'" fermer="annulerDocument" largeur="md">
                <form wire:submit="enregistrerDocument">
                    <div class="modale-corps">
                        <x-section-formulaire titre="Nature de la pièce" description="Le libellé apparaît dans la liste du dossier.">
                            <div class="sm:col-span-3">
                                <label for="document-type" class="etiquette">Type *</label>
                                <select id="document-type" wire:model="type" class="champ">
                                    @foreach($typesDocument as $typeDocument)
                                        <option value="{{ $typeDocument->value }}">{{ $typeDocument->libelle() }}</option>
                                    @endforeach
                                </select>
                                @error('type') <p class="erreur">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-3">
                                <label for="document-date" class="etiquette">Date du document</label>
                                <input id="document-date" type="date" wire:model="date_document" class="champ">
                                @error('date_document') <p class="erreur">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-6">
                                <label for="document-libelle" class="etiquette">Libellé *</label>
                                <input id="document-libelle" type="text" wire:model="libelle" class="champ" placeholder="Ex : contrat CDI signé">
                                @error('libelle') <p class="erreur">{{ $message }}</p> @enderror
                            </div>
                        </x-section-formulaire>

                        <x-section-formulaire titre="Fichier" description="Il est rangé hors de l'espace public : seul le dossier y donne accès.">
                            <div class="sm:col-span-6">
                                <input id="document-fichier" type="file" wire:model="fichier" accept=".pdf,.doc,.docx,image/*" class="sr-only">
                                <label for="document-fichier" class="zone-fichier">
                                    <x-icone nom="document-arrow-up" class="h-6 w-6 text-tertiaire" />
                                    <span class="text-sm font-medium text-ink">{{ $fichier ? $fichier->getClientOriginalName() : 'Choisir un fichier' }}</span>
                                    <span class="text-xs text-tertiaire">PDF, image ou Word, 2 Mo maximum. <span wire:loading wire:target="fichier">Envoi en cours…</span></span>
                                </label>
                                @error('fichier') <p class="erreur">{{ $message }}</p> @enderror
                            </div>
                        </x-section-formulaire>
                    </div>
                    <div class="modale-pied">
                        <p class="text-sm text-tertiaire">* Champs obligatoires</p>
                        <div class="modale-pied-actions">
                            <button type="button" wire:click="annulerDocument" class="bouton-secondaire">Annuler</button>
                            <button type="submit" class="bouton-principal" wire:loading.attr="disabled" wire:target="enregistrerDocument, fichier">Ajouter au dossier</button>
                        </div>
                    </div>
                </form>
            </x-modale>
        @endif
    @endif
</x-page>
