<x-app-layout title="Notifications — UIU CampusConnect">
    <div class="mx-auto max-w-xl">
        <div class="section-heading mb-5">
            <div>
                <h1>Notifications</h1>
            </div>
        </div>

        @forelse ($notifications as $n)
            @if ($loop->first)<div class="surface divide-y divide-ink-100 dark:divide-ink-800/70 overflow-hidden">@endif
            <div class="flex items-center gap-3 px-5 py-4 {{ $n->is_read ? '' : 'bg-primary-500/5' }}">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-ink-900/5 dark:bg-white/10 text-xs font-semibold">{{ $n->actor?->avatar_initials ?? 'U' }}</span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm">{{ $n->message }}</p>
                    <p class="text-xs text-ink-400">{{ $n->created_at->diffForHumans() }}</p>
                </div>
            </div>
            @if ($loop->last)</div>@endif
        @empty
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="bell" class="h-6 w-6" /></span>
                <h3>Nothing yet</h3>
                <p>Reactions, comments, and connection requests will show up here.</p>
            </div>
        @endforelse

        <div class="mt-6">{{ $notifications->links() }}</div>
    </div>
</x-app-layout>
