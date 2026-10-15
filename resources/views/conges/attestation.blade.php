<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Attestation de congé — {{ $conge->employe?->nomComplet() }}</title>
    <style>
        @page { margin: 18mm 20mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; color: #111827; line-height: 1.6; }
        .entete { text-align: center; border-bottom: 1px solid #111827; padding-bottom: 4mm; }
        .entete .ligne { font-size: 9.5pt; }
        .entete .ligne.principale { font-weight: bold; font-size: 10.5pt; }
        .entete img { max-height: 20mm; max-width: 45mm; margin-bottom: 2mm; }
        .references { margin-top: 6mm; font-size: 10pt; }
        .references .fonction { margin-top: 4mm; font-weight: bold; }
        h1 { font-size: 13pt; text-align: center; text-decoration: underline; margin: 14mm 0 12mm; letter-spacing: 0.02em; }
        p { margin: 0 0 5mm; text-align: justify; }
        .nom { font-weight: bold; }
        .signature { margin-top: 18mm; text-align: right; }
        .signature .nom-signataire { font-weight: bold; text-transform: uppercase; }
        .signature .espace { height: 22mm; }
        .pied { position: fixed; bottom: 0; left: 0; right: 0; border-top: 1px solid #e5e7eb; padding-top: 2mm; color: #6b7280; font-size: 7.5pt; text-align: center; white-space: pre-line; }
    </style>
</head>
<body>
    @php
        $employe = $conge->employe;
        $civilite = $employe?->civilite();
        $heureReprise = ltrim(str_replace(':', 'h', $parametres->heureDebutTravail()), '0');
        $signataire = $parametres->signataire_nom ?: '…';
        $fonctionSignataire = $parametres->signataire_fonction ?: 'Directeur Général';
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

    <div class="references">
        <div>Réf : N° {{ $conge->referenceAttestation() }}</div>
        <div>Date : {{ $conge->date_attestation?->locale('fr')->isoFormat('DD MMMM YYYY') }}</div>
        <div class="fonction">Le {{ $fonctionSignataire }}</div>
    </div>

    <h1>ATTESTATION DE CONGÉ</h1>

    <p>
        Je soussigné <span class="nom">{{ $signataire }}</span>, {{ $fonctionSignataire }}
        de {{ $parametres->nomPourDocuments() }}, atteste que
        {{ $civilite ? $civilite.' ' : '' }}<span class="nom">{{ $employe?->nomComplet() ?? '—' }}</span>,
        employé{{ $employe?->sexe === 'F' ? 'e' : '' }} à {{ $parametres->nomPourDocuments() }}
        @if($employe?->poste)
            en qualité de <span class="nom">{{ $employe->poste }}</span>
        @endif
        @if($employe?->departement)
            ({{ $employe->departement->nom }})
        @endif
        bénéficie de son {{ $conge->type->libelleAttestation() }}, et ce, à compter du
        <span class="nom">{{ $conge->date_debut->locale('fr')->isoFormat('DD MMMM YYYY') }}</span>.
    </p>

    <p>
        La Reprise du Travail est fixée au
        <span class="nom">{{ $reprise->locale('fr')->isoFormat('DD MMMM YYYY') }}</span> à {{ $heureReprise }}.
    </p>

    <p>Cette attestation est établie pour servir et valoir ce que de droit.</p>

    <div class="signature">
        <div>{{ $fonctionSignataire }}</div>
        <div class="espace"></div>
        <div class="nom-signataire">{{ $signataire }}</div>
    </div>

    @if($parametres->pied_page_documents)
        <div class="pied">{{ $parametres->pied_page_documents }}</div>
    @endif
</body>
</html>
