<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Demande de congé — {{ $conge->employe?->nomComplet() }}</title>
    <style>
        @page { margin: 16mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5pt; color: #111827; line-height: 1.5; }
        .entete { text-align: center; border-bottom: 1px solid #111827; padding-bottom: 3mm; }
        .entete .ligne { font-size: 9pt; }
        .entete .ligne.principale { font-weight: bold; font-size: 10pt; }
        .entete img { max-height: 18mm; max-width: 40mm; margin-bottom: 2mm; }
        h1 { font-size: 13pt; text-align: center; text-decoration: underline; margin: 10mm 0 8mm; }
        table { width: 100%; border-collapse: collapse; }
        .identite td { padding: 1.6mm 0; border-bottom: 1px dotted #9ca3af; }
        .identite td.libelle { width: 45mm; color: #4b5563; border-bottom: 0; }
        .bloc { margin-top: 6mm; }
        .titre-bloc { font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin-bottom: 2mm; }
        .solde td { border: 1px solid #d1d5db; padding: 2mm; text-align: center; }
        .solde th { border: 1px solid #d1d5db; padding: 2mm; background: #f3f4f6; font-size: 8.5pt; font-weight: normal; color: #4b5563; }
        .signatures { margin-top: 12mm; }
        .signatures td { width: 33%; vertical-align: top; padding-right: 4mm; }
        .cadre { border: 1px solid #9ca3af; height: 32mm; padding: 2mm; }
        .cadre .role { font-size: 8.5pt; color: #4b5563; }
        .cadre .nom { font-size: 9pt; font-weight: bold; margin-top: 1mm; }
        .mention { margin-top: 6mm; font-size: 8.5pt; color: #4b5563; }
        .pied { position: fixed; bottom: 0; left: 0; right: 0; border-top: 1px solid #e5e7eb; padding-top: 2mm; color: #6b7280; font-size: 7.5pt; text-align: center; white-space: pre-line; }
    </style>
</head>
<body>
    @php
        $employe = $conge->employe;
        $chef = $employe?->departement?->responsable;
    @endphp

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

    <h1>DEMANDE DE CONGÉ</h1>

    <div class="bloc">
        <p class="titre-bloc">Agent demandeur</p>
        <table class="identite">
            <tr><td class="libelle">Nom et prénom</td><td>{{ $employe?->nomComplet() ?? '—' }}</td></tr>
            <tr><td class="libelle">Matricule</td><td>{{ $employe?->matricule ?? '—' }}</td></tr>
            <tr><td class="libelle">Fonction</td><td>{{ $employe?->poste ?? '—' }}</td></tr>
            <tr><td class="libelle">Unité</td><td>{{ $employe?->departement?->nom ?? '—' }}</td></tr>
        </table>
    </div>

    <div class="bloc">
        <p class="titre-bloc">Congé demandé</p>
        <table class="identite">
            <tr><td class="libelle">Nature</td><td>{{ $conge->type->libelle() }}</td></tr>
            <tr><td class="libelle">Du</td><td>{{ $conge->date_debut->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</td></tr>
            <tr><td class="libelle">Au</td><td>{{ $conge->date_fin->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</td></tr>
            <tr><td class="libelle">Jours décomptés</td><td>{{ number_format($conge->nombre_jours, 1, ',', ' ') }} jour(s) ouvrés</td></tr>
            <tr><td class="libelle">Reprise du travail</td><td>{{ $reprise->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</td></tr>
            @if($conge->motif)
                <tr><td class="libelle">Motif</td><td>{{ $conge->motif }}</td></tr>
            @endif
        </table>
    </div>

    <div class="bloc">
        <p class="titre-bloc">Situation des droits à congé</p>
        <table class="solde">
            <tr>
                <th>Report des années précédentes</th>
                <th>Acquis en {{ $conge->date_debut->year }}</th>
                <th>Disponible avant la demande</th>
                <th>Jours demandés</th>
            </tr>
            <tr>
                <td>{{ number_format($solde['reporte'], 1, ',', ' ') }} j</td>
                <td>{{ number_format($solde['acquis'], 1, ',', ' ') }} j</td>
                <td>{{ number_format($solde['disponible'] + (float) $conge->nombre_jours, 1, ',', ' ') }} j</td>
                <td>{{ number_format($conge->nombre_jours, 1, ',', ' ') }} j</td>
            </tr>
        </table>
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div class="cadre">
                    <span class="role">Signature de l'agent</span>
                    <div class="nom">{{ $employe?->nomComplet() }}</div>
                </div>
            </td>
            <td>
                <div class="cadre">
                    <span class="role">Visa du chef hiérarchique</span>
                    <div class="nom">{{ $chef?->nomComplet() ?? '' }}</div>
                </div>
            </td>
            <td>
                <div class="cadre">
                    <span class="role">Avis du service du personnel</span>
                </div>
            </td>
        </tr>
    </table>

    <p class="mention">
        Formulaire remis le {{ $conge->created_at?->locale('fr')->isoFormat('D MMMM YYYY') }}.
        Une fois visé par le chef hiérarchique, il est rapporté au service du personnel,
        qui établit l'attestation de congé soumise à la signature du Directeur général.
    </p>

    @if($parametres->pied_page_documents)
        <div class="pied">{{ $parametres->pied_page_documents }}</div>
    @endif
</body>
</html>
