@if ($paginator->hasPages())
<nav class="pagination" aria-label="Pagini">
    @if (! $paginator->onFirstPage())<a href="{{ $paginator->previousPageUrl() }}" rel="prev"><x-site.icon name="chevron-left" :size="18" /><span class="sr-only">Pagina anterioară</span></a>@endif
    @foreach ($elements as $element)
        @if (is_string($element))<span aria-hidden="true">…</span>@endif
        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())<span aria-current="page">{{ $page }}</span>@else<a href="{{ $url }}">{{ $page }}</a>@endif
            @endforeach
        @endif
    @endforeach
    @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next"><x-site.icon name="chevron-right" :size="18" /><span class="sr-only">Pagina următoare</span></a>@endif
</nav>
@endif
