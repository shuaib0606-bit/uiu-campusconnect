<x-app-layout title="Feed - UIU CampusConnect">
    {{-- The posts sit between two equal gaps: the space from the menu to
         the posts matches the space from the posts to the tiles. The empty
         columns either side grow together; the extra 2rem column makes up
         for the page padding on the left. --}}
    <div class="grid w-full gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,42rem)_2rem_minmax(0,1fr)_300px] lg:items-start lg:gap-x-0">
    <div class="grid w-full max-w-2xl gap-5 lg:col-start-2">
        {{-- Post composer --}}
        @include('feed._composer')

        {{-- Six tabs do not fit across a phone, so the row scrolls sideways
             rather than wrapping into two rows of stubs. --}}
        <div class="surface-flat segmented rail-scroll overflow-x-auto">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('feed.index', ['tab' => $key]) }}"
                   class="segmented-item whitespace-nowrap {{ $tab === $key ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>

        {{-- Infinite scroll --}}
        <div class="grid gap-5"
            x-data="ccFeed({
                endpoint: '{{ route('feed.more') }}',
                seenEndpoint: '{{ route('feed.seen') }}',
                tab: '{{ $tab }}',
                cursor: @js($nextCursor),
                hasMore: @js($hasMore),
            })">

            @if ($posts->isEmpty())
                <div class="empty-state">
                    <span class="empty-icon"><x-ui-icon name="home" class="h-6 w-6" /></span>
                    <h3>{{ $emptyState[0] }}</h3>
                    <p>{{ $emptyState[1] }}</p>
                </div>
            @else
                <div x-ref="list" class="cc-stagger grid gap-5">
                    @include('feed._posts', ['posts' => $posts])
                </div>

                {{-- Watched by an IntersectionObserver. --}}
                <div x-ref="sentinel" aria-hidden="true" class="h-px w-full"></div>

                <div x-show="loading" x-cloak class="flex items-center justify-center gap-2 py-5 text-sm text-ink-500 dark:text-ink-400" role="status">
                    <x-ui-icon name="refresh" class="h-4 w-4 animate-spin" />
                    <span>Loading more posts</span>
                </div>

                <div x-show="failed && !loading" x-cloak class="surface-flat flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <span class="text-sm text-ink-500 dark:text-ink-400">Could not load more posts.</span>
                    <button type="button" class="btn-secondary" @click="loadMore()">Try again</button>
                </div>

                <div x-show="!hasMore && !loading && !failed" x-cloak class="flex flex-col items-center gap-2 py-8 text-center">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-primary-500/10 text-primary-600 dark:text-primary-300">
                        <x-ui-icon name="check-circle" class="h-5 w-5" />
                    </span>
                    <p class="text-sm font-medium">You are all caught up</p>
                    <p class="max-w-xs text-xs text-ink-500 dark:text-ink-400">
                        @switch ($tab)
                            @case('following') That is everything from the people you follow. @break
                            @case('trending') That is everything campus has been talking about in the last two days. @break
                            @case('department') That is everything from your department. @break
                            @case('batch') That is everything from your batch. @break
                            @case('clubs') That is everything your clubs have shared. @break
                            @default Check back later, or follow more people to see more here.
                        @endswitch
                    </p>
                </div>
            @endif

            @if ($nextCursor)
                <noscript>
                    <a href="{{ route('feed.index', ['tab' => $tab, 'cursor' => $nextCursor]) }}" class="btn-secondary mx-auto">Load more posts</a>
                </noscript>
            @endif
        </div>
    </div>

    {{-- Sticky rail --}}
    <aside class="hidden lg:sticky lg:top-20 lg:col-start-5 lg:block lg:w-[300px]">
        <x-feed-rail :upcoming-events="$upcomingEvents" :suggested-clubs="$suggestedClubs" />
    </aside>
    </div>
</x-app-layout>
