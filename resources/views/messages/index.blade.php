<x-app-layout title="Messages — UIU CampusConnect">
    <div class="mx-auto max-w-2xl">
        <div class="section-heading mb-5">
            <div>
                <h1>Messages</h1>
                <p class="section-sub">{{ count($threads) }} conversation{{ count($threads) === 1 ? '' : 's' }}</p>
            </div>
        </div>

        @forelse ($threads as $t)
            @if ($loop->first)<div class="surface divide-y divide-ink-100 dark:divide-ink-800/70 overflow-hidden">@endif
            <a href="{{ route('messages.show', $t['user']) }}" class="flex items-center gap-3 px-5 py-4 transition-colors hover:bg-ink-900/[0.02] dark:hover:bg-white/[0.03]">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-primary-100 text-primary-700 text-sm font-semibold dark:bg-primary-900/40 dark:text-primary-200">{{ $t['user']->avatar_initials }}</span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between">
                        <span class="truncate text-sm font-semibold">{{ $t['user']->full_name }}</span>
                        <span class="shrink-0 text-xs text-ink-400">{{ $t['last']->created_at->diffForHumans(null, true) }}</span>
                    </div>
                    <p class="truncate text-sm text-ink-500 dark:text-ink-400">{{ $t['last']->body ?: 'Attachment' }}</p>
                </div>
                @if ($t['unread'] > 0)<span class="rounded-full bg-primary-500 text-white text-[11px] font-semibold px-2 py-0.5">{{ $t['unread'] }}</span>@endif
            </a>
            @if ($loop->last)</div>@endif
        @empty
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="message" class="h-6 w-6" /></span>
                <h3>No conversations yet</h3>
                <p>Messages you send or receive from your connections will show up here.</p>
            </div>
        @endforelse
    </div>
</x-app-layout>
