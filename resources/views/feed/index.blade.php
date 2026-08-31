<x-app-layout title="Feed - UIU CampusConnect">
    <div class="mx-auto grid max-w-5xl gap-5 lg:grid-cols-[1fr_300px] lg:items-start">
    <div class="grid max-w-2xl gap-5">
        @if ($latestNotice)
            <a href="{{ route('notices.index') }}" class="surface flex items-center gap-3 px-5 py-3.5 hover:border-primary-300 transition">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-primary-500/10 text-primary-600 dark:text-primary-300"><x-ui-icon name="flag" class="h-4 w-4" /></span>
                <span class="truncate text-sm font-medium">{{ $latestNotice->title }}</span>
                <x-ui-icon name="chevron-right" class="ml-auto h-4 w-4 text-ink-400 shrink-0" />
            </a>
        @endif

        {{-- Post composer --}}
        @include('feed._composer')

        <div class="surface-flat segmented">
            <a href="{{ route('feed.index', ['tab' => 'for-you']) }}" class="segmented-item {{ $tab === 'for-you' ? 'is-active' : '' }}">For You</a>
            <a href="{{ route('feed.index', ['tab' => 'following']) }}" class="segmented-item {{ $tab === 'following' ? 'is-active' : '' }}">Following</a>
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
                    <h3>{{ $tab === 'following' ? 'No posts from people you follow' : 'Nothing here yet' }}</h3>
                    <p>{{ $tab === 'following' ? 'Follow more people to see their posts here.' : 'Be the first to share something with campus.' }}</p>
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
                        {{ $tab === 'following' ? 'That is everything from the people you follow.' : 'Check back later, or follow more people to see more here.' }}
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
    <aside class="hidden rail-scroll lg:sticky lg:top-20 lg:flex lg:max-h-[calc(100vh-6rem)] lg:flex-col lg:gap-5 lg:overflow-y-auto lg:pr-1">
        <x-shuttle-card />
        <x-deadlines-card />

        @if ($upcomingEvents->isNotEmpty())
            <div class="rail-card">
                <h3 class="mb-3">Upcoming events</h3>
                <div class="space-y-3">
                    @foreach ($upcomingEvents as $event)
                        <a href="{{ route('events.show', $event) }}" class="flex items-start gap-3 group">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-300"><x-ui-icon name="calendar" class="h-4 w-4" /></span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium group-hover:underline">{{ $event->title }}</span>
                                <span class="block text-xs text-ink-500 dark:text-ink-400">{{ $event->start_datetime->format('M j, g:i A') }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
                <a href="{{ route('events.index') }}" class="mt-4 block text-center text-xs font-medium text-primary-600 dark:text-primary-300 hover:underline">View all events</a>
            </div>
        @endif

        @if ($suggestedClubs->isNotEmpty())
            <div class="rail-card">
                <h3 class="mb-3">Clubs for you</h3>
                <div class="space-y-3">
                    @foreach ($suggestedClubs as $club)
                        <a href="{{ route('clubs.show', $club) }}" class="flex items-center gap-3 group">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-300"><x-ui-icon name="award" class="h-4 w-4" /></span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium group-hover:underline">{{ $club->name }}</span>
                                <span class="block text-xs text-ink-500 dark:text-ink-400">{{ $club->members_count }} member{{ $club->members_count === 1 ? '' : 's' }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
                <a href="{{ route('clubs.index') }}" class="mt-4 block text-center text-xs font-medium text-primary-600 dark:text-primary-300 hover:underline">Browse clubs</a>
            </div>
        @endif
    </aside>
    </div>
</x-app-layout>
