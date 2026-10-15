<div class="noeud {{ $niveau === 0 ? 'racine' : '' }}">
    <div class="carte {{ $noeud->posteVacant() ? 'vacant' : '' }}" @if($noeud->estDirectionGenerale) style="border-top: 2px solid #1C3C8F;" @endif>
        @if($noeud->estDirectionGenerale && mb_strtolower($noeud->departement->nom) !== 'direction générale')<div class="etiquette-dg">Direction générale</div>@endif
        @php($couleurs = $noeud->type->couleursPdf())
        <div class="nom-departement">
            {{ $noeud->departement->nom }}
            <span class="badge-type" style="border-color: {{ $couleurs['bordure'] }}; background-color: {{ $couleurs['fond'] }}; color: {{ $couleurs['texte'] }};">{{ mb_strtoupper($noeud->type->libelle()) }}</span>
            <span class="effectif">({{ $noeud->effectifTotal() }} pers.)</span>
        </div>
        @if($noeud->responsable)
            <div class="responsable">{{ $noeud->responsable->nomComplet() }} <span class="poste">· {{ $noeud->responsable->poste }}</span></div>
        @else
            <div class="vacant-texte">Poste vacant</div>
        @endif
    </div>

    @if($noeud->employes->isNotEmpty())
        <ul class="employes">
            @foreach($noeud->employes as $employe)
                <li>{{ $employe->nomComplet() }} <span class="poste">{{ $employe->poste }}</span></li>
            @endforeach
        </ul>
    @endif

    @foreach($noeud->enfants as $enfant)
        @include('organigramme.partials.noeud-pdf', ['noeud' => $enfant, 'niveau' => $niveau + 1])
    @endforeach
</div>
