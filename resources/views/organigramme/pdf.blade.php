<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Organigramme</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #111827; }
        h1 { font-size: 14pt; margin: 0 0 2mm; font-family: 'DejaVu Sans', sans-serif; }
        .sous-titre { color: #6b7280; font-size: 8pt; margin: 0; }
        .entete-entreprise { width: 100%; border-collapse: collapse; border-bottom: 1px solid #1A2226; margin-bottom: 6mm; }
        .entete-entreprise td { vertical-align: top; padding: 0 0 3mm; }
        .entete-gauche { width: 55%; }
        .entete-droite { text-align: right; }
        .entete-logo { max-height: 14mm; max-width: 40mm; margin-bottom: 1.5mm; }
        .entete-nom { font-size: 11pt; font-weight: bold; }
        .entete-coordonnees { color: #5C6660; font-size: 8pt; white-space: pre-line; }
        .etiquette { font-size: 7pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .noeud { margin-left: 7mm; border-left: 1px solid #d1d5db; padding-left: 3mm; margin-top: 2mm; page-break-inside: avoid; }
        .noeud.racine { margin-left: 0; border-left: 0; padding-left: 0; margin-top: 4mm; }
        .carte { border: 1px solid #d1d5db; padding: 1.5mm 3mm; display: inline-block; min-width: 55mm; }
        .carte.vacant { border-color: #fde68a; }
        .nom-departement { font-weight: bold; }
        .responsable { color: #4b5563; }
        .vacant-texte { color: #92400e; }
        .employes { margin: 1mm 0 0 7mm; padding: 0; list-style: none; }
        .employes li { color: #374151; }
        .poste { color: #9ca3af; font-size: 8pt; }
        .effectif { color: #9ca3af; font-size: 8pt; font-weight: normal; }
        .badge-type { display: inline-block; border: 1px solid; font-size: 6.5pt; font-weight: normal; letter-spacing: 0.05em; padding: 0.3mm 1.5mm; margin: 0 1mm; vertical-align: middle; }
        .etiquette-dg { font-size: 7pt; color: #1C3C8F; margin-bottom: 0.5mm; }
        .pied-document { position: fixed; bottom: -6mm; left: 0; right: 0; text-align: center; font-size: 7pt; color: #6b7280; }
        .sans-departement { margin-top: 6mm; border-top: 1px solid #d1d5db; padding-top: 3mm; }
    </style>
</head>
<body>
    @php($parametres ??= \App\Models\ParametresApplication::courants())
    <table class="entete-entreprise">
        <tr>
            <td class="entete-gauche">
                @if($parametres->logoDataUri())
                    <img src="{{ $parametres->logoDataUri() }}" alt="" class="entete-logo">
                @endif
                <div class="entete-nom">{{ $parametres->nomPourDocuments() }}</div>
                @if($parametres->adresse_entreprise)<div class="entete-coordonnees">{{ $parametres->adresse_entreprise }}</div>@endif
                @if($parametres->telephone_entreprise || $parametres->email_entreprise)
                    <div class="entete-coordonnees">{{ implode(' · ', array_filter([$parametres->telephone_entreprise, $parametres->email_entreprise])) }}</div>
                @endif
            </td>
            <td class="entete-droite">
                <h1>Organigramme</h1>
                <div class="sous-titre">Généré le {{ $genereLe->format('d/m/Y à H:i') }} · {{ $organigramme->effectifTotal() }} personne(s) active(s)</div>
            </td>
        </tr>
    </table>


    @foreach($organigramme->racines as $racine)
        @include('organigramme.partials.noeud-pdf', ['noeud' => $racine, 'niveau' => 0])
    @endforeach

    @if($organigramme->employesSansDepartement->isNotEmpty())
        <div class="sans-departement">
            <div class="nom-departement">Employés sans département</div>
            <ul class="employes">
                @foreach($organigramme->employesSansDepartement as $employe)
                    <li>{{ $employe->nomComplet() }} <span class="poste">{{ $employe->poste }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($parametres->pied_page_documents)
        <div class="pied-document">{{ $parametres->pied_page_documents }}</div>
    @endif
</body>
</html>
