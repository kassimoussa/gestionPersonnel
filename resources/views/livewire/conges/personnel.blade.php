@use('App\Enums\StatutConge')
<x-page titre="Congés du personnel" sous-titre="Le circuit complet : formulaire remis à l'employé, visa du chef, attestation et récapitulatif pour la direction, signature du directeur général.">
    <x-slot name="actions">
        <div class="w-full sm:w-52">
            <select wire:model.live="filtreStatut" class="champ" aria-label="Filtrer par étape">
                <option value="en_cours">En cours de circuit</option>
                <option value="acte">Congés actés</option>
                <option value="clos">Refusés ou annulés</option>
                <option value="tous">Tous</option>
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
        <button wire:click="nouveau" class="bouton-principal w-full sm:w-auto"><x-icone nom="plus" class="h-4 w-4" />Ouvrir une demande</button>
    </x-slot>

    @if($information)
        <div class="message-succes">{{ $information }}</div>
    @endif

    @if($formulaireOuvert)
        <x-modale contexte="Congés du personnel" titre="Ouvrir une demande de congé" sous-titre="L'employé vient chercher son formulaire : renseignez la période, imprimez le formulaire et remettez-le-lui à faire viser." largeur="lg">
            <form wire:submit="enregistrer">
                <div class="modale-corps">
                    <x-section-formulaire titre="Employé" description="Cherchez par nom, matricule ou numéro de pointeuse. Seuls les employés actifs de votre périmètre sont proposés.">
                        <div class="sm:col-span-4">
                            <x-selecteur-employe
                                id="conge-employe"
                                :requis="true"
                                :recherche="$rechercheEmploye"
                                :resultats="$employesTrouves"
                                :choisi="$employeChoisi" />
                            @error('employe_id') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        @if($soldeDisponible !== null)
                            <div class="sm:col-span-2">
                                <p class="etiquette">Solde disponible</p>
                                <p class="mt-2 text-lg font-semibold text-ink">{{ number_format($soldeDisponible, 1, ',', ' ') }} j</p>
                            </div>
                        @endif
                    </x-section-formulaire>

                    <x-section-formulaire titre="Type de congé" description="Seul le congé annuel est déduit du solde de l'employé.">
                        @foreach($types as $typeConge)
                            <label class="choix sm:col-span-3">
                                <input type="radio" wire:model.live="type" value="{{ $typeConge->value }}">
                                <span>
                                    <span class="choix-titre">{{ $typeConge->libelle() }}</span>
                                    <span class="choix-description">{{ $typeConge->consommeSolde() ? 'Déduit du solde' : 'Sans effet sur le solde' }}</span>
                                </span>
                            </label>
                        @endforeach
                        @error('type') <p class="erreur sm:col-span-6">{{ $message }}</p> @enderror
                    </x-section-formulaire>

                    <x-section-formulaire titre="Période" description="Dates incluses. Le weekend et les jours fériés ne sont pas décomptés. La reprise est fixée au premier jour travaillé qui suit.">
                        <div class="sm:col-span-3">
                            <label for="conge-debut" class="etiquette">Premier jour *</label>
                            <input id="conge-debut" type="date" wire:model.live="date_debut" class="champ">
                            @error('date_debut') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3">
                            <label for="conge-fin" class="etiquette">Dernier jour *</label>
                            <input id="conge-fin" type="date" wire:model.live="date_fin" class="champ">
                            @error('date_fin') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        @php($depasse = $type === 'conge_annuel' && $soldeDisponible !== null && $nombreJours > $soldeDisponible)
                        <div class="flex items-center gap-4 rounded-lg p-4 sm:col-span-6 {{ $depasse ? 'bg-attention-doux text-attention' : 'bg-fond' }}" aria-live="polite">
                            <span class="icone-mise-en-avant {{ $depasse ? 'text-attention' : 'text-accent' }}"><x-icone nom="calendar-days" /></span>
                            <div class="min-w-0">
                                <p class="text-lg font-semibold {{ $depasse ? '' : 'text-ink' }}">{{ $nombreJours }} jour(s) décompté(s)</p>
                                <p class="text-sm {{ $depasse ? 'font-medium' : 'text-tertiaire' }}">
                                    @if($depasse)
                                        La demande dépasse le solde disponible de l'employé.
                                    @elseif($dateReprise)
                                        Reprise du travail le {{ $dateReprise }}.
                                    @else
                                        Renseignez les dates pour connaître la date de reprise.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </x-section-formulaire>

                    <x-section-formulaire titre="Motif et circuit" description="Le motif figure sur le formulaire remis à l'employé.">
                        <div class="sm:col-span-6">
                            <label for="conge-motif" class="etiquette">Motif</label>
                            <textarea id="conge-motif" wire:model="motif" rows="3" class="champ" placeholder="Facultatif"></textarea>
                            @error('motif') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        @if($peutEnregistrerAccorde)
                            <div class="sm:col-span-6">
                                <x-interrupteur
                                    id="conge-deja-accorde"
                                    titre="Congé déjà accordé hors application"
                                    description="À cocher pour reprendre un congé ancien : il est enregistré comme acté, sans passer par le circuit. Votre nom reste attaché à la saisie."
                                    wire:model.live="dejaAccorde" />
                            </div>
                        @endif
                    </x-section-formulaire>
                </div>
                <div class="modale-pied">
                    <p class="text-sm text-tertiaire">* Champs obligatoires</p>
                    <div class="modale-pied-actions">
                        <button type="button" wire:click="annuler" class="bouton-secondaire">Annuler</button>
                        <button type="submit" class="bouton-principal">{{ $dejaAccorde ? 'Enregistrer le congé' : 'Ouvrir la demande' }}</button>
                    </div>
                </div>
            </form>
        </x-modale>
    @endif

    {{-- Étape 2 : le formulaire visé revient --}}
    @if($etape && $etape->statut === StatutConge::FormulaireRemis)
        <x-modale contexte="Congés du personnel" titre="Enregistrer le formulaire visé" :sous-titre="'Demande de '.$etape->employe?->nomComplet().' du '.$etape->date_debut->format('d/m/Y').' au '.$etape->date_fin->format('d/m/Y').'.'" fermer="annulerEtape" largeur="md">
            <form wire:submit="enregistrerVisa">
                <div class="modale-corps">
                    <x-section-formulaire titre="Visa du chef" description="Le chef a signé le formulaire que l'employé rapporte au service du personnel.">
                        <div class="sm:col-span-4">
                            <label for="visa-chef" class="etiquette">Chef ayant visé *</label>
                            <div class="champ-icone">
                                <x-icone nom="user" />
                                <select id="visa-chef" wire:model="chefVisaId" class="champ">
                                    <option value="">Sélectionner...</option>
                                    @foreach($chefsPossibles as $chef)
                                        <option value="{{ $chef->id }}">{{ $chef->nomComplet() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="aide">Pré-rempli avec le responsable de l'unité de l'employé.</p>
                            @error('chefVisaId') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="visa-date" class="etiquette">Date du visa *</label>
                            <input id="visa-date" type="date" wire:model="dateVisa" max="{{ now()->toDateString() }}" class="champ">
                            @error('dateVisa') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-6">
                            <input id="visa-piece" type="file" wire:model="formulaireSigne" accept=".pdf,image/*" class="sr-only">
                            <label for="visa-piece" class="zone-fichier">
                                <x-icone nom="document-arrow-up" class="h-6 w-6 text-tertiaire" />
                                <span class="text-sm font-medium text-ink">{{ $formulaireSigne ? $formulaireSigne->getClientOriginalName() : 'Joindre le formulaire signé *' }}</span>
                                <span class="text-xs text-tertiaire">PDF ou image, 2 Mo maximum. <span wire:loading wire:target="formulaireSigne">Envoi en cours…</span></span>
                            </label>
                            @error('formulaireSigne') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>
                </div>
                <div class="modale-pied">
                    <p class="text-sm text-tertiaire">* Champs obligatoires</p>
                    <div class="modale-pied-actions">
                        <button type="button" wire:click="annulerEtape" class="bouton-secondaire">Annuler</button>
                        <button type="submit" class="bouton-principal" wire:loading.attr="disabled" wire:target="enregistrerVisa, formulaireSigne">Enregistrer le visa</button>
                    </div>
                </div>
            </form>
        </x-modale>
    @endif

    {{-- Étape 4 : l'attestation revient signée --}}
    @if($etape && $etape->statut === StatutConge::AttestationASigner)
        <x-modale contexte="Congés du personnel" titre="Enregistrer la signature du directeur général" :sous-titre="'Attestation n° '.$etape->referenceAttestation().' — '.$etape->employe?->nomComplet().'.'" fermer="annulerEtape" largeur="md">
            <form wire:submit="enregistrerSignature">
                <div class="modale-corps">
                    <x-section-formulaire titre="Attestation signée" description="Une fois la signature enregistrée, le congé est acté et le solde de l'employé décompté.">
                        <div class="sm:col-span-3">
                            <label for="signature-date" class="etiquette">Date de signature *</label>
                            <input id="signature-date" type="date" wire:model="dateSignature" max="{{ now()->toDateString() }}" class="champ">
                            @error('dateSignature') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-6">
                            <input id="signature-piece" type="file" wire:model="attestationSignee" accept=".pdf,image/*" class="sr-only">
                            <label for="signature-piece" class="zone-fichier">
                                <x-icone nom="document-arrow-up" class="h-6 w-6 text-tertiaire" />
                                <span class="text-sm font-medium text-ink">{{ $attestationSignee ? $attestationSignee->getClientOriginalName() : "Joindre l'attestation signée *" }}</span>
                                <span class="text-xs text-tertiaire">PDF ou image, 2 Mo maximum. <span wire:loading wire:target="attestationSignee">Envoi en cours…</span></span>
                            </label>
                            @error('attestationSignee') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>
                </div>
                <div class="modale-pied">
                    <p class="text-sm text-tertiaire">* Champs obligatoires</p>
                    <div class="modale-pied-actions">
                        <button type="button" wire:click="annulerEtape" class="bouton-secondaire">Annuler</button>
                        <button type="submit" class="bouton-principal" wire:loading.attr="disabled" wire:target="enregistrerSignature, attestationSignee">Acter le congé</button>
                    </div>
                </div>
            </form>
        </x-modale>
    @endif

    {{-- Refus --}}
    @if($congeRefuse)
        <x-modale contexte="Congés du personnel" titre="Refuser la demande" sous-titre="Le motif reste au dossier et reste visible par l'employé." fermer="annulerEtape" largeur="md">
            <form wire:submit="confirmerRefus">
                <div class="modale-corps">
                    <x-section-formulaire titre="Demande concernée" description="Rappel de la demande.">
                        <div class="flex flex-wrap items-center gap-4 sm:col-span-6">
                            <x-avatar :nom="$congeRefuse->employe?->nomComplet() ?? '?'" :photo="$congeRefuse->employe?->photoUrl()" taille="lg" />
                            <div class="min-w-0 flex-1">
                                <p class="text-base font-semibold text-ink">{{ $congeRefuse->employe?->nomComplet() ?? '—' }}</p>
                                <p class="text-sm text-tertiaire">{{ $congeRefuse->type->libelle() }} du {{ $congeRefuse->date_debut->format('d/m/Y') }} au {{ $congeRefuse->date_fin->format('d/m/Y') }}</p>
                            </div>
                            <x-badge :variante="$congeRefuse->statut->varianteBadge()">{{ $congeRefuse->statut->libelle() }}</x-badge>
                        </div>
                    </x-section-formulaire>

                    <x-section-formulaire titre="Motif du refus" description="Expliquez la raison : effectif insuffisant, solde épuisé, refus du chef.">
                        <div class="sm:col-span-6">
                            <label for="motif-refus" class="etiquette">Motif du refus (obligatoire)</label>
                            <textarea id="motif-refus" wire:model="motifRefus" rows="4" class="champ"></textarea>
                            @error('motifRefus') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>
                </div>
                <div class="modale-pied">
                    <p class="text-sm text-tertiaire">Cette décision clôt la demande.</p>
                    <div class="modale-pied-actions">
                        <button type="button" wire:click="annulerEtape" class="bouton-secondaire">Annuler</button>
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
                    <x-entete-triable colonne="employe" :tri="$tri" :sens="$sens">Employé</x-entete-triable>
                    <x-entete-triable colonne="type" :tri="$tri" :sens="$sens">Type</x-entete-triable>
                    <x-entete-triable colonne="date_debut" :tri="$tri" :sens="$sens">Période</x-entete-triable>
                    <x-entete-triable colonne="nombre_jours" :tri="$tri" :sens="$sens" class="montant">Jours</x-entete-triable>
                    <x-entete-triable colonne="statut" :tri="$tri" :sens="$sens">Étape</x-entete-triable>
                    <th>Documents</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($conges as $conge)
                    <tr wire:key="conge-{{ $conge->id }}">
                        <td>
                            <div class="identite">
                                <x-avatar :nom="$conge->employe?->nomComplet() ?? '?'" :photo="$conge->employe?->photoUrl()" taille="sm" />
                                <span class="min-w-0">
                                    <span class="identite-nom">{{ $conge->employe?->nomComplet() ?? '—' }}</span>
                                    <span class="sous-ligne">{{ $conge->employe?->departement?->nom ?? 'sans département' }}</span>
                                </span>
                            </div>
                        </td>
                        <td>
                            <x-badge variante="neutre" :point="false">{{ $conge->type->libelle() }}</x-badge>
                            @if($conge->saisiPar)
                                <span class="sous-ligne">ouverte par {{ $conge->saisiPar->name }}</span>
                            @endif
                        </td>
                        <td class="secondaire whitespace-nowrap">
                            du {{ $conge->date_debut->format('d/m/Y') }} au {{ $conge->date_fin->format('d/m/Y') }}
                            <span class="sous-ligne">reprise le {{ $conge->jourReprise()->format('d/m/Y') }}</span>
                        </td>
                        <td class="montant">{{ number_format($conge->nombre_jours, 1, ',', ' ') }}</td>
                        <td>
                            <x-badge :variante="$conge->statut->varianteBadge()">{{ $conge->statut->libelle() }}</x-badge>
                            @if($conge->chefVisa && $conge->date_decision_manager)
                                <span class="sous-ligne">visé par {{ $conge->chefVisa->nomComplet() }} le {{ $conge->date_decision_manager->format('d/m/Y') }}</span>
                            @endif
                            @if($conge->estActe() && $conge->date_decision_dg)
                                <span class="sous-ligne">signé le {{ $conge->date_decision_dg->format('d/m/Y') }}</span>
                            @endif
                            @if($conge->estEnregistrementRetroactif())
                                <span class="sous-ligne">repris hors circuit</span>
                            @endif
                            @if($conge->statut === StatutConge::Refuse && $conge->commentaire_refus)
                                <span class="sous-ligne text-danger">{{ $conge->commentaire_refus }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions-icones justify-start">
                                <x-bouton-icone icone="document-text" libelle="Formulaire de demande" :href="route('conges.formulaire', $conge)" />
                                @if($conge->formulaireSigneRecu())
                                    <x-bouton-icone icone="check-badge" libelle="Formulaire visé par le chef" variante="succes" :href="route('conges.piece', [$conge, 'formulaire'])" />
                                @endif
                                @if($conge->attestationEtablie())
                                    <x-bouton-icone icone="document-duplicate" libelle="Attestation de congé" variante="accent" :href="route('conges.attestation', $conge)" />
                                    <x-bouton-icone icone="chart-bar" libelle="Récapitulatif des congés" :href="route('conges.recapitulatif', $conge)" />
                                @endif
                                @if($conge->attestationSigneeRecue())
                                    <x-bouton-icone icone="finger-print" libelle="Attestation signée par le DG" variante="succes" :href="route('conges.piece', [$conge, 'attestation'])" />
                                @endif
                            </div>
                        </td>
                        <td class="actions">
                            <div class="actions-icones gap-2">
                                @if($conge->statut === StatutConge::FormulaireRemis)
                                    <button wire:click="ouvrirVisa({{ $conge->id }})" class="bouton-lien">Visa reçu</button>
                                @elseif($conge->statut === StatutConge::ViseParLeChef)
                                    <button wire:click="etablirAttestation({{ $conge->id }})" class="bouton-lien-principal">Établir l'attestation</button>
                                @elseif($conge->statut === StatutConge::AttestationASigner)
                                    <button wire:click="ouvrirSignature({{ $conge->id }})" class="bouton-lien-principal">Signature reçue</button>
                                @endif
                                @if($conge->estEnCours())
                                    <x-bouton-icone icone="x-mark" libelle="Refuser la demande" variante="danger" wire:click="refuser({{ $conge->id }})" />
                                    <x-bouton-icone icone="trash" libelle="Retirer la demande" variante="danger" wire:click="supprimer({{ $conge->id }})" wire:confirm="Retirer cette demande ? Les pièces déposées seront supprimées." />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="vide"><x-icone nom="magnifying-glass" class="mx-auto mb-3 block h-8 w-8 text-bordure-forte" />Aucune demande pour cette sélection.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $conges->links() }}
    </div>
</x-page>
