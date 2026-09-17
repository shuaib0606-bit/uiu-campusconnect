<x-app-layout title="Search - UIU CampusConnect">
    <div class="mx-auto max-w-4xl">
        <div class="section-heading mb-5">
            <div>
                <h1>Global Search</h1>
                <p class="section-sub">Search people, posts, clubs, events, notices, groups, and marketplace items.</p>
            </div>
        </div>

        <form method="GET" class="surface mb-6 flex flex-wrap gap-2 p-4">
            <div class="relative min-w-[220px] flex-1">
                <x-ui-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-ink-400" />
                <input type="search" name="q" value="{{ $q }}" placeholder="Search UIU CampusConnect" required autofocus class="field pl-10">
            </div>
            <select name="filter" class="field w-auto">
                @foreach (\App\Http\Controllers\SearchController::FILTERS as $opt)
                    <option value="{{ $opt }}" @selected($filter === $opt)>{{ ucfirst($opt) }}</option>
                @endforeach
            </select>
            <input name="department" value="{{ $department }}" placeholder="Department filter" class="field w-auto">
            <button type="submit" class="btn-primary">Search</button>
        </form>

        @if ($q === '')
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="search" class="h-6 w-6" /></span>
                <h3>Type something to search</h3>
                <p>Results from across the platform will appear here.</p>
            </div>
        @else
            @php
                $hasAny = $users->isNotEmpty() || $posts->isNotEmpty() || $clubs->isNotEmpty() || $events->isNotEmpty() || $notices->isNotEmpty() || $groups->isNotEmpty() || $marketplace->isNotEmpty();
            @endphp

            @if (! $hasAny)
                <div class="empty-state">
                    <span class="empty-icon"><x-ui-icon name="question-mark-circle" class="h-6 w-6" /></span>
                    <h3>No results</h3>
                    <p>Try a different keyword or filter.</p>
                </div>
            @else
                <div class="grid gap-5 sm:grid-cols-2">
                    @if ($users->isNotEmpty())
                        <div class="surface p-5">
                            <h2 class="mb-3 text-[15px] font-semibold">People</h2>
                            <div class="space-y-2">
                                @foreach ($users as $u)
                                    <a href="{{ route('profile.show', $u) }}" class="flex items-center gap-3 rounded-xl px-2 py-2 hover:bg-ink-900/[0.03] dark:hover:bg-white/[0.04]">
                                        <x-user-avatar :user="$u" size="h-9 w-9" text="text-xs" />
                                        <span class="min-w-0"><span class="block truncate text-sm font-semibold">{{ $u->full_name }}</span><span class="block truncate text-xs text-ink-400">{{ $u->department ?: ucfirst($u->role) }}</span></span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($clubs->isNotEmpty())
                        <div class="surface p-5">
                            <h2 class="mb-3 text-[15px] font-semibold">Clubs</h2>
                            <div class="space-y-2">
                                @foreach ($clubs as $c)
                                    <a href="{{ route('clubs.show', $c) }}" class="block rounded-xl px-2 py-2 hover:bg-ink-900/[0.03] dark:hover:bg-white/[0.04]"><span class="block text-sm font-semibold">{{ $c->name }}</span><span class="block text-xs text-ink-400">{{ $c->category }}</span></a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($events->isNotEmpty())
                        <div class="surface p-5">
                            <h2 class="mb-3 text-[15px] font-semibold">Events</h2>
                            <div class="space-y-2">
                                @foreach ($events as $e)
                                    <a href="{{ route('events.show', $e) }}" class="block rounded-xl px-2 py-2 hover:bg-ink-900/[0.03] dark:hover:bg-white/[0.04]"><span class="block text-sm font-semibold">{{ $e->title }}</span><span class="block text-xs text-ink-400">{{ $e->start_datetime->format('M j, Y') }} &middot; {{ $e->location }}</span></a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($notices->isNotEmpty())
                        <div class="surface p-5">
                            <h2 class="mb-3 text-[15px] font-semibold">Notices</h2>
                            <div class="space-y-2">
                                @foreach ($notices as $n)
                                    <div class="rounded-xl px-2 py-2"><span class="block text-sm font-semibold">{{ $n->title }}</span><span class="block text-xs text-ink-400">{{ ucfirst($n->notice_type) }} &middot; {{ $n->created_at->format('M j, Y') }}</span></div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($groups->isNotEmpty())
                        <div class="surface p-5">
                            <h2 class="mb-3 text-[15px] font-semibold">Groups</h2>
                            <div class="space-y-2">
                                @foreach ($groups as $g)
                                    <a href="{{ route('groups.show', $g) }}" class="block rounded-xl px-2 py-2 hover:bg-ink-900/[0.03] dark:hover:bg-white/[0.04]"><span class="block text-sm font-semibold">{{ $g->name }}</span><span class="block text-xs text-ink-400">{{ ucfirst($g->group_type) }}</span></a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($marketplace->isNotEmpty())
                        <div class="surface p-5">
                            <h2 class="mb-3 text-[15px] font-semibold">Marketplace</h2>
                            <div class="space-y-2">
                                @foreach ($marketplace as $m)
                                    <a href="{{ route('marketplace.index') }}" class="block rounded-xl px-2 py-2 hover:bg-ink-900/[0.03] dark:hover:bg-white/[0.04]"><span class="block text-sm font-semibold">{{ $m->title }}</span><span class="block text-xs text-ink-400">{{ ucfirst(str_replace('_', ' ', $m->category)) }}</span></a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($posts->isNotEmpty())
                        <div class="surface p-5 sm:col-span-2">
                            <h2 class="mb-3 text-[15px] font-semibold">Posts</h2>
                            <div class="space-y-3">
                                @foreach ($posts as $p)
                                    @include('feed._post', ['post' => $p])
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
