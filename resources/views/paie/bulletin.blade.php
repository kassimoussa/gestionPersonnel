@use('App\Models\FichePaie')
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin de paie {{ $fiche->periodeLibelle() }}</title>
    <style>
        @page { margin: 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #111827; }
        h1 { font-size: 15pt; margin: 0; }
        .entete { border-bottom: 1px solid #111827; padding-bottom: 3mm; margin-bottom: 5mm; }
        .entete .societe { font-size: 11pt; font-weight: bold; }
        .entete .coordonnees { color: #5C6660; font-size: 8pt; white-space: pre-line; }
        .entete h1 { margin-top: 3mm; font-family: 'DejaVu Sans', sans-serif; }
        .entete .periode { color: #4b5563; }
        .bloc { margin-bottom: 5mm; }
        .titre-bloc { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin-bottom: 1.5mm; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1.6mm 2.5mm; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { text-align: left; font-weight: normal; color: #6b7280; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.05em; background: #f9fafb; }
        td.montant, th.montant { text-align: right; white-space: nowrap; }
        tr.total td { font-weight: bold; border-top: 1px solid #9ca3af; border-bottom: 1px solid #9ca3af; }
        tr.net td { font-weight: bold; font-size: 11pt; border-top: 2px solid #111827; border-bottom: 2px solid #111827; }
        .identite td { border: 0; padding: 0.8mm 0; }
        .identite td.libelle { color: #6b7280; width: 38mm; }
        .note { color: #6b7280; font-size: 8pt; }
        .pied { margin-top: 8mm; border-top: 1px solid #e5e7eb; padding-top: 2mm; color: #6b7280; font-size: 8pt; }
        .deux-colonnes { width: 100%; }
        .deux-colonnes td { border: 0; padding: 0; vertical-align: top; }
        .deux-colonnes td.gauche { width: 50%; padding-right: 4mm; }
    </style>
</head>
<body>
    @php($parametres ??= \App\Models\ParametresApplication::courants())
    <div class="entete">
        <table class="deux-colonnes">
            <tr>
                <td class="gauche">
                    @if($parametres->logoDataUri())
                        <img src="{{ $parametres->logoDataUri() }}" alt="" style="max-height: 14mm; max-width: 40mm; margin-bottom: 1.5mm;">
                    @endif
                    <div class="societe">{{ $parametres->nomPourDocuments() }}</div>
                    @if($parametres->adresse_entreprise)<div class="coordonnees">{{ $parametres->adresse_entreprise }}</div>@endif
                    @if($parametres->telephone_entreprise || $parametres->email_entreprise)
                        <div class="coordonnees">{{ implode(' · ', array_filter([$parametres->telephone_entreprise, $parametres->email_entreprise])) }}</div>
                    @endif
                    <h1>Bulletin de paie</h1>
                    <div class="periode">{{ $fiche->periodeLibelle() }}</div>
                </td>
                <td>
                    <table class="identite">
                        <tr><td class="libelle">Employé</td><td>{{ $fiche->employe?->nomComplet() ?? '—' }}</td></tr>
                        @if($fiche->employe?->matricule)
                            <tr><td class="libelle">Matricule</td><td>{{ $fiche->employe->matricule }}</td></tr>
                        @endif
                        @if($fiche->employe?->numero_cnss)
                            <tr><td class="libelle">N° CNSS</td><td>{{ $fiche->employe->numero_cnss }}</td></tr>
                        @endif
                        <tr><td class="libelle">Poste</td><td>{{ $fiche->employe?->poste ?? '—' }}@if($fiche->employe?->categorie) · {{ $fiche->employe->categorie }}@endif</td></tr>
                        <tr><td class="libelle">Département</td><td>{{ $fiche->employe?->departement?->nom ?? '—' }}</td></tr>
                        <tr><td class="libelle">Date d'embauche</td><td>{{ $fiche->employe?->date_embauche?->format('d/m/Y') ?? '—' }}</td></tr>
                        <tr><td class="libelle">Statut de la fiche</td><td>{{ $fiche->statut->libelle() }}@if($fiche->estValidee()), le {{ $fiche->validee_le?->format('d/m/Y') }} par {{ $fiche->valideePar?->name ?? '—' }}@endif</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="bloc">
        <div class="titre-bloc">Rémunération brute</div>
        <table>
            <tr><td>Salaire de base</td><td class="montant">{{ FichePaie::montant($fiche->salaire_base) }}</td></tr>
            <tr><td>Primes</td><td class="montant">{{ FichePaie::montant($fiche->primes) }}</td></tr>
            <tr><td>Indemnités</td><td class="montant">{{ FichePaie::montant($fiche->indemnites) }}</td></tr>
            <tr class="total"><td>Salaire brut</td><td class="montant">{{ FichePaie::montant($fiche->brut) }}</td></tr>
        </table>
    </div>

    <div class="bloc">
        <div class="titre-bloc">Cotisations CNSS · assiette {{ FichePaie::montant($detailCnss['assiette']) }}@if($detailCnss['assiette'] > (float) $fiche->brut) (plancher de cotisation appliqué)@endif</div>
        <table>
            <thead>
                <tr>
                    <th>Branche</th>
                    <th class="montant">Taux salarié</th>
                    <th class="montant">Part salariale</th>
                    <th class="montant">Taux employeur</th>
                    <th class="montant">Part patronale</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_unique([...array_keys($detailCnss['salarie']), ...array_keys($detailCnss['employeur'])]) as $branche)
                    @php($salarie = $detailCnss['salarie'][$branche] ?? null)
                    @php($employeur = $detailCnss['employeur'][$branche] ?? null)
                    <tr>
                        <td>{{ $salarie['libelle'] ?? $employeur['libelle'] }}</td>
                        <td class="montant">{{ $salarie ? number_format($salarie['taux'], 1, ',', ' ').' %' : '—' }}</td>
                        <td class="montant">{{ $salarie ? FichePaie::montant($salarie['montant']) : '—' }}</td>
                        <td class="montant">{{ $employeur ? number_format($employeur['taux'], 1, ',', ' ').' %' : '—' }}</td>
                        <td class="montant">{{ $employeur ? FichePaie::montant($employeur['montant']) : '—' }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>Total CNSS</td>
                    <td class="montant">{{ number_format(array_sum(array_column($detailCnss['salarie'], 'taux')), 1, ',', ' ') }} %</td>
                    <td class="montant">{{ FichePaie::montant($fiche->cnss_salarie) }}</td>
                    <td class="montant">{{ number_format(array_sum(array_column($detailCnss['employeur'], 'taux')), 1, ',', ' ') }} %</td>
                    <td class="montant">{{ FichePaie::montant($fiche->cnss_employeur) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="bloc">
        <div class="titre-bloc">Impôt sur les traitements et salaires (IRPP)</div>
        <table>
            <tr><td>Base imposable (brut − part salariale CNSS)</td><td class="montant" colspan="3">{{ FichePaie::montant($fiche->base_imposable) }}</td></tr>
            @if($detailIrpp['mode'] === 'progressif')
                @foreach($detailIrpp['tranches'] as $tranche)
                    <tr>
                        <td>Tranche {{ $tranche['a'] === null ? 'au-delà de '.FichePaie::montant($tranche['de']) : 'de '.FichePaie::montant($tranche['de']).' à '.FichePaie::montant($tranche['a']) }}</td>
                        <td class="montant">{{ FichePaie::montant($tranche['base']) }}</td>
                        <td class="montant">× {{ number_format($tranche['taux'], 0, ',', ' ') }} %</td>
                        <td class="montant">{{ FichePaie::montant($tranche['montant']) }}</td>
                    </tr>
                @endforeach
            @elseif($detailIrpp['mode'] === 'forfaitaire')
                <tr>
                    <td>Taux forfaitaire sur la totalité du brut (contrat de moins d'un mois civil)</td>
                    <td class="montant">{{ FichePaie::montant($fiche->brut) }}</td>
                    <td class="montant">× {{ number_format($detailIrpp['taux_forfaitaire'], 0, ',', ' ') }} %</td>
                    <td class="montant">{{ FichePaie::montant($fiche->irpp) }}</td>
                </tr>
            @else
                <tr><td colspan="4" class="note">Détail par tranche indisponible (contrat modifié après la génération de la fiche) : seul le montant total ci-dessous fait foi.</td></tr>
            @endif
            <tr class="total"><td>Total IRPP</td><td class="montant" colspan="3">{{ FichePaie::montant($fiche->irpp) }}</td></tr>
        </table>
    </div>

    <div class="bloc">
        <table>
            <tr class="net"><td>Net à payer</td><td class="montant">{{ FichePaie::montant($fiche->net_a_payer) }}</td></tr>
            <tr><td class="note">Coût total employeur (brut + part patronale CNSS)</td><td class="montant note">{{ FichePaie::montant($fiche->coutEmployeur()) }}</td></tr>
        </table>
    </div>

    @if($parametres->signataire_nom)
        <table style="width: 100%; margin-top: 10mm;">
            <tr>
                <td style="border: 0; width: 60%;"></td>
                <td style="border: 0; text-align: center;">
                    <div class="note">{{ $parametres->signataire_fonction ?: 'Pour l\'employeur' }}</div>
                    <div style="height: 16mm;"></div>
                    <div style="border-top: 1px solid #9ca3af; padding-top: 1.5mm; font-weight: bold;">{{ $parametres->signataire_nom }}</div>
                </td>
            </tr>
        </table>
    @endif

    <div class="pied">
        @if($parametres->pied_page_documents)
            <div style="white-space: pre-line; margin-bottom: 1mm;">{{ $parametres->pied_page_documents }}</div>
        @endif
        Document généré le {{ $genereLe->format('d/m/Y à H:i') }}. Taux CNSS et barème IRPP paramétrés dans l'application (config/paie.php).
    </div>
</body>
</html>
