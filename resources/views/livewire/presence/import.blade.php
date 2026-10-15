<x-page titre="Import des pointages" sous-titre="Fichier CSV exporté par la pointeuse. Format attendu (provisoire) : colonnes matricule_pointeuse;date;heure;type, voir docs/format-import-pointages.md.">
    <x-slot name="actions">
        <button wire:click="nouveau" class="bouton-principal"><x-icone nom="arrow-up-tray" class="h-4 w-4" />Importer un fichier</button>
    </x-slot>

    @if($formulaireOuvert)
        <x-modale contexte="Présence" titre="Importer un fichier de pointeuse" sous-titre="Les lignes dont le matricule est inconnu sont signalées sans bloquer le reste de l'import." largeur="lg">
            <form wire:submit="importer">
                <div class="modale-corps">
                    <x-section-formulaire titre="Fichier" description="Export CSV de la machine à empreinte, 5 Mo maximum.">
                        <div class="sm:col-span-6">
                            <input id="fichier" type="file" wire:model="fichier" accept=".csv,.txt,text/csv" class="sr-only">
                            <label for="fichier" class="zone-fichier">
                                <span class="icone-mise-en-avant text-accent"><x-icone nom="document-arrow-up" /></span>
                                @if($fichier)
                                    <span class="text-sm font-semibold text-ink">{{ $fichier->getClientOriginalName() }}</span>
                                    <span class="text-sm text-tertiaire">Cliquez pour choisir un autre fichier</span>
                                @else
                                    <span class="text-sm font-semibold text-accent">Cliquez pour choisir un fichier CSV</span>
                                    <span class="text-sm text-tertiaire">Extensions .csv ou .txt</span>
                                @endif
                                <span class="text-sm text-tertiaire" wire:loading wire:target="fichier">Envoi du fichier…</span>
                            </label>
                            @error('fichier') <p class="erreur">{{ $message }}</p> @enderror
                        </div>
                    </x-section-formulaire>

                    <x-section-formulaire titre="Format attendu" description="Format provisoire, à ajuster lorsque l'export réel de la machine sera connu.">
                        <div class="sm:col-span-6">
                            <p class="text-sm text-secondaire">Une ligne d'en-tête puis une ligne par passage de badge, séparées par des points-virgules :</p>
                            <pre class="mt-3 overflow-x-auto rounded-lg bg-fond p-4 text-xs text-ink">matricule_pointeuse;date;heure;type
A001;2026-09-14;07:58;entree
A001;2026-09-14;17:31;sortie</pre>
                            <p class="aide">La première entrée et la dernière sortie de chaque jour forment le pointage de l'employé.</p>
                        </div>
                    </x-section-formulaire>
                </div>
                <div class="modale-pied">
                    <p class="text-sm text-tertiaire">Documentation : docs/format-import-pointages.md</p>
                    <div class="modale-pied-actions">
                        <button type="button" wire:click="annuler" class="bouton-secondaire">Annuler</button>
                        <button type="submit" class="bouton-principal" wire:loading.attr="disabled" wire:target="importer, fichier">
                            <span wire:loading.remove wire:target="importer">Importer</span>
                            <span wire:loading wire:target="importer">Import en cours…</span>
                        </button>
                    </div>
                </div>
            </form>
        </x-modale>
    @endif

    @if($dernierImport)
        <div class="{{ $dernierImport->nombre_erreurs > 0 ? 'message-attention' : 'message-succes' }} max-w-3xl space-y-1">
            <p class="font-medium">Import de « {{ $dernierImport->nom_fichier }} » terminé</p>
            <p>{{ $dernierImport->nombre_lignes_traitees }} ligne(s) traitée(s), {{ $dernierImport->nombre_erreurs }} erreur(s).</p>
            <p>{{ $pointagesCrees }} pointage(s) créé(s), {{ $pointagesMisAJour }} mis à jour, {{ $joursIgnores }} journée(s) ignorée(s) car déjà saisie(s) manuellement.</p>
        </div>
    @endif

    <div class="tableau-conteneur">
        <table class="tableau">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Fichier</th>
                    <th>Importé par</th>
                    <th class="montant">Lignes traitées</th>
                    <th>Erreurs</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($imports as $import)
                    <tr wire:key="import-{{ $import->id }}">
                        <td class="secondaire whitespace-nowrap">{{ $import->created_at->format('d/m/Y H:i') }}</td>
                        <td class="font-medium">{{ $import->nom_fichier }}</td>
                        <td class="secondaire">{{ $import->importePar?->name ?? '—' }}</td>
                        <td class="montant">{{ $import->nombre_lignes_traitees }}</td>
                        <td><x-badge :variante="$import->nombre_erreurs > 0 ? 'danger' : 'succes'" :point="false">{{ $import->nombre_erreurs }}</x-badge></td>
                        <td class="actions">
                            @if($import->nombre_erreurs > 0)
                                <button wire:click="voirDetails({{ $import->id }})" class="bouton-lien">
                                    {{ $detailsImportId === $import->id ? 'Masquer les erreurs' : 'Voir les erreurs' }}
                                </button>
                            @endif
                        </td>
                    </tr>
                    @if($importDetaille && $importDetaille->id === $import->id)
                        <tr wire:key="details-{{ $import->id }}">
                            <td colspan="6" class="bg-fond">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="text-left text-secondaire">
                                            <th class="w-16 py-1.5 pe-2 font-medium">Ligne</th>
                                            <th class="py-1.5 pe-2 font-medium">Erreur</th>
                                            <th class="py-1.5 font-medium">Contenu</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-bordure">
                                        @foreach($importDetaille->erreurs_detail ?? [] as $erreur)
                                            <tr>
                                                <td class="py-1.5 pe-2 text-secondaire">{{ $erreur['ligne'] ?? '—' }}</td>
                                                <td class="py-1.5 pe-2 text-danger">{{ $erreur['message'] }}</td>
                                                <td class="py-1.5 text-secondaire">{{ $erreur['contenu'] ?? '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="6" class="vide"><x-icone nom="magnifying-glass" class="mx-auto mb-3 block h-8 w-8 text-bordure-forte" />Aucun import pour l'instant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-page>
