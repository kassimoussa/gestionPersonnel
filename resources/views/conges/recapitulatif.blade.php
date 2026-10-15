<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Récapitulatif des congés — {{ $employe?->nomComplet() }}</title>
    <style>
        @page { margin: 16mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #111827; line-height: 1.5; }
        .entete { text-align: center; border-bottom: 1px solid #111827; padding-bottom: 3mm; }
        .entete .ligne { font-size: 9pt; }
        .entete .ligne.principale { font-weight: bold; font-size: 10pt; }
        .entete img { max-height: 18mm; max-width: 40mm; margin-bottom: 2mm; }
        h1 { font-size: 13pt; text-align: center; text-decoration: underline; margin: 10mm 0 6mm; }
        .agent { margin-bottom: 6mm; }
        .agent td { padding: 1.4mm 0; }
        .agent td.libelle { width: 45mm; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; }
        .titre-bloc { font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin: 6mm 0 2mm; }
        .grille th, .grille td { border: 1px solid #d1d5db; padding: 2mm; }
        .grille th { background: #f3f4f6; font-size: 8.5pt; font-weight: normal; color: #4b5563; text-align: left; }
        .grille td.nombre { text-align: right; white-space: nowrap; }
        .grille tr.total td { font-weight: bold; background: #f9fafb; }
        .note { margin-top: 5mm; font-size: 8.5pt; color: #4b5563; }
        .pied { position: fixed; bottom: 0; left: 0; right: 0; border-top: 1px solid #e5e7eb; padding-top: 2mm; color: #6b7280; font-size: 7.5pt; text-align: center; white-space: pre-line; }
    </style>
</head>
<body>
    <div class="entete">
        @if($parametres->logoDataUri())
            <img src="{{ $parametres->logoDataUri() }}" alt="">
        @endif
        @foreach(preg_split('/\r\n|\r|\n/', (string) ($parametres->entete_documents ?: $parametres->nomPourDocuments())) as $index => $ligne)
            @if(trim($ligne) !== '')
                <div class="ligne {{ $index === 0 ? 'principale' : '' }}">{{ $ligne }}</div>
            @endif
        @endforeach
    </div>

    <h1>RÉCAPITULATIF DES CONGÉS</h1>

    <table class="agent">
        <tr><td class="libelle">Agent</td><td>{{ $employe?->nomComplet() ?? '—' }}@if($employe?->matricule) · matricule {{ $employe->matricule }}@endif</td></tr>
        <tr><td class="libelle">Fonction et unité</td><td>{{ collect([$employe?->poste, $employe?->departement?->nom])->filter()->join(' · ') ?: '—' }}</td></tr>
        <tr><td class="libelle">Demande en cours</td><td>{{ $conge->type->libelle() }} du {{ $conge->date_debut->format('d/m/Y') }} au {{ $conge->date_fin->format('d/m/Y') }}, soit {{ number_format($conge->nombre_jours, 1, ',', ' ') }} jour(s)</td></tr>
        <tr><td class="libelle">Établi le</td><td>{{ $genereLe->locale('fr')->isoFormat('D MMMM YYYY') }}</td></tr>
    </table>

    <p class="titre-bloc">Droits à congé annuel</p>
    <table class="grille">
        <tr>
            <th>Report des années précédentes</th>
            <td class="nombre">{{ number_format($solde['reporte'], 1, ',', ' ') }} j</td>
        </tr>
        <tr>
            <th>Droits acquis en {{ $annee }}</th>
            <td class="nombre">{{ number_format($solde['acquis'], 1, ',', ' ') }} j</td>
        </tr>
        <tr>
            <th>Congés actés à ce jour</th>
            <td class="nombre">− {{ number_format($solde['pris'], 1, ',', ' ') }} j</td>
        </tr>
        <tr class="total">
            <th>Solde restant</th>
            <td class="nombre">{{ number_format($solde['restant'], 1, ',', ' ') }} j</td>
        </tr>
        <tr>
            <th>Dont demande(s) en cours de circuit</th>
            <td class="nombre">{{ number_format($solde['restant'] - $solde['disponible'], 1, ',', ' ') }} j</td>
        </tr>
        <tr class="total">
            <th>Solde disponible après cette demande</th>
            <td class="nombre">{{ number_format($solde['disponible'], 1, ',', ' ') }} j</td>
        </tr>
    </table>

    <p class="titre-bloc">Historique des congés annuels</p>
    <table class="grille">
        <tr>
            <th>Période</th>
            <th>Jours</th>
            <th>Statut</th>
            <th>Attestation</th>
        </tr>
        @forelse($historique as $ligne)
            <tr>
                <td>du {{ $ligne->date_debut->format('d/m/Y') }} au {{ $ligne->date_fin->format('d/m/Y') }}</td>
                <td class="nombre">{{ number_format($ligne->nombre_jours, 1, ',', ' ') }}</td>
                <td>{{ $ligne->statut->libelle() }}</td>
                <td>{{ $ligne->referenceAttestation() ? 'N° '.$ligne->referenceAttestation() : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Aucun congé annuel enregistré à ce jour.</td></tr>
        @endforelse
    </table>

    <p class="note">
        Les congés se décomptent en jours travaillés : ni le weekend ni les jours fériés ne sont retirés du solde.
        Les jours non pris se reportent d'une année sur l'autre.
    </p>

    @if($parametres->pied_page_documents)
        <div class="pied">{{ $parametres->pied_page_documents }}</div>
    @endif
</body>
</html>
