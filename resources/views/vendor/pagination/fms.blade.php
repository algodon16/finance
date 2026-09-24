{{-- Shared pagination footer.
     Rendered for every LengthAwarePaginator via Paginator::defaultView().
     Left: result count. Right: compact rectangular buttons
     [Previous] [1] [2] ... [Next]. No large standalone arrow icons. --}}
<div class="fms-pager">
    <p class="fms-pager-count">
        @if ($paginator->total() > 0)
            Showing {{ $paginator->firstItem() ?? 0 }} to {{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} results
        @else
            No results found
        @endif
    </p>
    @if ($paginator->hasPages())
    <nav class="fms-pager-links" aria-label="Pagination">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="fms-page-btn fms-page-prev is-disabled" aria-disabled="true"><span class="fms-page-arrow" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" focusable="false"><path d="M10 3 5 8l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>Previous</span></span>
        @else
            <a class="fms-page-btn fms-page-prev" href="{{ $paginator->previousPageUrl() }}" rel="prev"><span class="fms-page-arrow" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" focusable="false"><path d="M10 3 5 8l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>Previous</span></a>
        @endif

        {{-- Page numbers --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="fms-page-btn is-ellipsis" aria-hidden="true">&hellip;</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="fms-page-btn is-active" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="fms-page-btn" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a class="fms-page-btn fms-page-next" href="{{ $paginator->nextPageUrl() }}" rel="next"><span>Next</span><span class="fms-page-arrow" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" focusable="false"><path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span></a>
        @else
            <span class="fms-page-btn fms-page-next is-disabled" aria-disabled="true"><span>Next</span><span class="fms-page-arrow" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" focusable="false"><path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span></span>
        @endif
    </nav>
    @endif
</div>
