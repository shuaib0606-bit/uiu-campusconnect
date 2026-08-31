<x-app-layout title="Notifications - UIU CampusConnect">
    <div class="mx-auto max-w-2xl">
        <div class="surface mb-5 flex flex-wrap items-center justify-between gap-6 p-6">
            <div class="max-w-sm">
                <p class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-300"><x-ui-icon name="bell" class="h-4 w-4" />Notification center</p>
                <h1 class="mt-1 text-[19px] font-semibold">Updates that need your attention</h1>
                <p class="section-sub mt-1">Reactions, comments, mentions, connections and university notices - open one to jump straight to it.</p>
            </div>
            <div class="grid shrink-0 grid-cols-2 gap-3">
                <div class="stat-card items-center text-center"><span class="stat-value">{{ number_format($counts['unread']) }}</span><span class="stat-label">Unread</span></div>
                <div class="stat-card items-center text-center"><span class="stat-value">{{ number_format($counts['total']) }}</span><span class="stat-label">Total</span></div>
            </div>
        </div>

        <div class="surface-flat mb-5 flex flex-wrap items-center justify-between gap-3 p-2">
            <div class="flex flex-wrap gap-1.5">
                @foreach ($filters as $key => $meta)
                    <a href="{{ route('notifications.index', $key === 'all' ? [] : ['filter' => $key]) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors {{ $filter === $key ? 'bg-primary-500 text-white' : 'text-ink-600 hover:bg-ink-900/[0.04] dark:text-ink-300 dark:hover:bg-white/[0.06]' }}">
                        {{ $meta['label'] }}
                        @if ($key === 'unread' && $counts['unread'] > 0)
                            <span class="rounded-full px-1.5 text-[11px] font-semibold {{ $filter === $key ? 'bg-white/25' : 'bg-primary-500/15 text-primary-600 dark:text-primary-300' }}">{{ $counts['unread'] > 99 ? '99+' : $counts['unread'] }}</span>
                        @elseif ($key === 'mentions' && $counts['mentions'] > 0)
                            <span class="rounded-full px-1.5 text-[11px] font-semibold {{ $filter === $key ? 'bg-white/25' : 'bg-primary-500/15 text-primary-600 dark:text-primary-300' }}">{{ $counts['mentions'] > 99 ? '99+' : $counts['mentions'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
            @if ($counts['unread'] > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs">
                        <x-ui-icon name="check" class="h-3.5 w-3.5" />Mark all read
                    </button>
                </form>
            @endif
        </div>

        @if ($isEmpty)
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="bell" class="h-6 w-6" /></span>
                <h3>Nothing here yet</h3>
                <p>When someone reacts to your post, replies to you, mentions you, follows you, or the university publishes a notice, it lands here.</p>
            </div>
        @endif

        @foreach ($groups as $label => $group)
            <h2 class="mb-2 mt-6 text-sm font-semibold text-ink-500 first:mt-0 dark:text-ink-400">{{ $label }}</h2>
            <div class="surface cc-stagger divide-y divide-ink-100 overflow-hidden dark:divide-ink-800/70">
                @foreach ($group as $n)
                    <a href="{{ route('notifications.go', $n) }}"
                       class="group relative flex items-center gap-3 px-5 py-4 transition-colors {{ $n->is_read ? '' : 'bg-primary-500/[0.06]' }} hover:bg-ink-900/[0.03] dark:hover:bg-white/[0.04]">

                        <span class="relative shrink-0">
                            <span class="grid h-10 w-10 place-items-center rounded-full bg-ink-900/5 text-xs font-semibold dark:bg-white/10">
                                {{ $n->actor?->avatar_initials ?? 'UIU' }}
                            </span>
                            <span class="absolute -bottom-0.5 -right-0.5 grid h-5 w-5 place-items-center rounded-full bg-primary-500 text-white ring-2 ring-[var(--surface-card)]">
                                <x-ui-icon :name="$n->iconName()" class="h-3 w-3" />
                            </span>
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block text-sm {{ $n->is_read ? 'text-ink-600 dark:text-ink-300' : 'font-medium text-ink-900 dark:text-white' }}">{{ $n->message }}</span>
                            <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-ink-400 dark:text-ink-500">
                                <span>{{ $n->created_at?->diffForHumans() }}</span>
                                @if ($n->isAggregated())
                                    <span class="chip !px-2 !py-0 !text-[11px]">{{ $n->group_count }} people</span>
                                @endif
                            </span>
                        </span>

                        @unless ($n->is_read)
                            <span class="h-2 w-2 shrink-0 rounded-full bg-primary-500" aria-label="Unread"></span>
                        @endunless
                        <x-ui-icon name="chevron-right" class="h-4 w-4 shrink-0 text-ink-300 opacity-0 transition-opacity group-hover:opacity-100 dark:text-ink-600" />
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>
</x-app-layout>
