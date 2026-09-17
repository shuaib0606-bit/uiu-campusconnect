@php
    $pill = 'inline-flex h-9 min-w-9 items-center justify-center rounded-full px-3 text-sm font-medium transition';
    $idle = 'text-ink-600 hover:bg-ink-900/[0.06] hover:text-ink-900 dark:text-ink-300 dark:hover:bg-white/[0.08] dark:hover:text-white';
    $muted = 'cursor-not-allowed text-ink-300 dark:text-ink-600';
    $active = 'bg-primary-500 text-white shadow-[0_6px_16px_-8px_var(--color-primary-500)]';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-ink-500 dark:text-ink-400">
            Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }}
        </p>

        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="{{ $pill }} {{ $muted }}" aria-disabled="true">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $pill }} {{ $idle }}">Previous</a>
            @endif

            <span class="hidden items-center gap-1 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="px-1.5 text-sm text-ink-400 dark:text-ink-500">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="{{ $pill }} {{ $active }}" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="{{ $pill }} {{ $idle }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </span>

            <span class="{{ $pill }} text-ink-500 dark:text-ink-400 sm:hidden">
                {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $pill }} {{ $idle }}">Next</a>
            @else
                <span class="{{ $pill }} {{ $muted }}" aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@endif
