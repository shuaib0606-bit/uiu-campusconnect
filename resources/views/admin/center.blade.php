<x-app-layout title="Admin Center - UIU CampusConnect">
    <div class="mx-auto max-w-5xl">
        <div class="surface mb-5 flex flex-wrap items-center justify-between gap-4 bg-gradient-to-br from-primary-600 to-primary-800 p-6 text-white">
            <div class="max-w-xl">
                <p class="text-xs font-semibold uppercase tracking-wide text-white/80">Master administration</p>
                <h1 class="mt-1 text-[22px] font-semibold">Admin Control Center</h1>
                <p class="mt-1 text-sm text-white/85">Manage approvals, reports, clubs, notices, events, and user access from one clean dashboard.</p>
            </div>
            <a href="{{ route('settings.password') }}" class="btn-secondary !bg-white/15 !text-white hover:!bg-white/25">Account settings</a>
        </div>

        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="stat-card items-center text-center"><span class="stat-value">{{ $stats['pending_users'] }}</span><span class="stat-label">Pending users</span></div>
            <div class="stat-card items-center text-center"><span class="stat-value">{{ $stats['pending_apps'] }}</span><span class="stat-label">Connection applications</span></div>
            <div class="stat-card items-center text-center"><span class="stat-value">{{ $stats['reports'] }}</span><span class="stat-label">Open reports</span></div>
            <div class="stat-card items-center text-center"><span class="stat-value">{{ $stats['active_users'] }}</span><span class="stat-label">Active users</span></div>
        </div>

        <div class="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['pill' => 'Account recovery', 'title' => 'Password Requests', 'desc' => 'Review lock-out requests, verify identity, and issue a one-time temporary password.', 'href' => route('password-requests.index'), 'cta' => 'Open requests', 'icon' => 'lock'],
                ['pill' => 'User validation', 'title' => 'User Approvals', 'desc' => 'Approve new signups, view student UIU ID cards, reject fake accounts, suspend users.', 'href' => route('user-approvals.index'), 'cta' => 'Open approvals', 'icon' => 'user-plus'],
                ['pill' => 'Safety', 'title' => 'Moderation Center', 'desc' => 'Review post, comment, and profile reports; remove content or suspend users.', 'href' => route('moderation.index'), 'cta' => 'Open moderation', 'icon' => 'shield'],
                ['pill' => 'Faculty access', 'title' => 'Connection Applications', 'desc' => 'Approve student requests before they can connect with faculty or officials.', 'href' => route('connect-requests.index'), 'cta' => 'Open applications', 'icon' => 'handshake'],
                ['pill' => 'Campus', 'title' => 'Clubs', 'desc' => 'Open clubs, assign moderators, review club membership and panel roles.', 'href' => route('clubs.index'), 'cta' => 'Manage clubs', 'icon' => 'award'],
                ['pill' => 'Events', 'title' => 'Events', 'desc' => 'Review campus and club events across the university.', 'href' => route('events.index'), 'cta' => 'Open events', 'icon' => 'calendar'],
                ['pill' => 'Announcements', 'title' => 'Notices', 'desc' => 'Publish or review official notices and administration announcements.', 'href' => route('notices.index'), 'cta' => 'Open notices', 'icon' => 'megaphone'],
            ] as $card)
                <article class="surface surface-hover flex flex-col p-5">
                    <span class="chip w-fit">{{ $card['pill'] }}</span>
                    <h2 class="mt-2 inline-flex items-center gap-1.5 text-[15px] font-semibold"><x-ui-icon :name="$card['icon']" class="h-4 w-4 text-primary-600 dark:text-primary-300" />{{ $card['title'] }}</h2>
                    <p class="mt-1 flex-1 text-sm text-ink-600 dark:text-ink-300">{{ $card['desc'] }}</p>
                    <a href="{{ $card['href'] }}" class="btn-primary mt-3 w-fit">{{ $card['cta'] }}</a>
                </article>
            @endforeach
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="surface p-6">
                <h2 class="mb-3 text-[15px] font-semibold">Recent users</h2>
                @forelse ($recentUsers as $u)
                    <div class="flex items-center justify-between gap-3 border-b border-ink-100 py-2.5 last:border-0 dark:border-ink-800/70">
                        <div class="min-w-0">
                            <a href="{{ route('profile.show', $u) }}" class="truncate text-sm font-semibold hover:underline">{{ $u->full_name }}</a>
                            <p class="section-sub truncate">{{ $u->email }} &middot; {{ ucfirst($u->role) }} &middot; {{ $u->status }}</p>
                        </div>
                        <a href="{{ route('user-approvals.index', ['tab' => 'all']) }}" class="btn-secondary shrink-0 !px-3 !py-1.5 text-xs">Review</a>
                    </div>
                @empty
                    <p class="text-sm text-ink-400">No user records found.</p>
                @endforelse
            </div>

            <div class="surface p-6">
                <h2 class="mb-3 text-[15px] font-semibold">System summary</h2>
                <div class="space-y-2.5 text-sm">
                    <div class="flex items-center justify-between border-b border-ink-100 pb-2.5 dark:border-ink-800/70"><span>Suspended users</span><span class="font-semibold">{{ $stats['suspended_users'] }}</span></div>
                    <div class="flex items-center justify-between border-b border-ink-100 pb-2.5 dark:border-ink-800/70"><span>Active clubs</span><span class="font-semibold">{{ $stats['clubs'] }}</span></div>
                    <div class="flex items-center justify-between border-b border-ink-100 pb-2.5 dark:border-ink-800/70"><span>Published events</span><span class="font-semibold">{{ $stats['events'] }}</span></div>
                    <div class="flex items-center justify-between"><span>Published notices</span><span class="font-semibold">{{ $stats['notices'] }}</span></div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
