@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p class="text-tertiaire">
            {{ $paginator->firstItem() }} à {{ $paginator->lastItem() }} sur {{ $paginator->total() }}
        </p>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="bouton-secondaire opacity-50" aria-disabled="true">Précédent</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="bouton-secondaire">Précédent</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-secondaire" aria-hidden="true">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="rounded-lg bg-accent-soft px-3 py-1.5 font-semibold text-accent" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="rounded-lg px-3 py-1.5 font-medium text-secondaire hover:bg-fond hover:text-ink" aria-label="Page {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="bouton-secondaire">Suivant</a>
            @else
                <span class="bouton-secondaire opacity-50" aria-disabled="true">Suivant</span>
            @endif
        </div>
    </nav>
@endif
