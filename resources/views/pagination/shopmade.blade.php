@if ($paginator->hasPages())
    <nav class="flex flex-wrap items-center justify-start gap-3 px-4 py-3 text-label text-ink/55">
        <span class="tabular-nums">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
        </span>
        <span>·</span>
        @if ($paginator->onFirstPage())
            <span>Prev</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}">Prev</a>
        @endif
        <span>·</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}">Next</a>
        @else
            <span>Next</span>
        @endif
    </nav>
@endif
