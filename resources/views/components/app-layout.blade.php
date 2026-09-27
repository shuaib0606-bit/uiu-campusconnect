@props(['title' => 'UIU CampusConnect'])
<!DOCTYPE html>
<html lang="en" class="{{ ($activeTheme ?? 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'UIU CampusConnect' }}</title>
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('img/logo-64.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('img/logo-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
    <script>
        (function () {
            var m = document.cookie.match(/(?:^|; )cc_theme=([^;]+)/);
            if (m && m[1] === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <div class="flex min-h-screen">
        <aside class="rail-scroll hidden shrink-0 flex-col border-r border-ink-100 px-4 py-6 dark:border-ink-800/70 lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-[264px] lg:overflow-y-auto">
            @auth
            <a href="{{ route('profile.show', auth()->user()) }}" class="flex flex-col items-center text-center gap-3 px-2 mb-8">
                <span class="relative">
                    <x-user-avatar :user="auth()->user()" size="h-20 w-20" text="text-2xl" />
                    <span class="absolute -left-1 -top-1 grid h-6 w-6 place-items-center rounded-lg bg-primary-500 text-white ring-[3px] ring-[var(--surface-page)]">
                        <x-ui-icon name="home" class="h-3.5 w-3.5" />
                    </span>
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-[15px] font-semibold tracking-tight">{{ auth()->user()->full_name }}</span>
                    <span class="block text-xs text-ink-500 dark:text-ink-400">{{ auth()->user()->handle() }}</span>
                </span>
            </a>
            @else
            <a href="{{ route('feed.index') }}" class="flex items-center gap-2.5 px-2 mb-8">
                <img src="{{ asset('img/logo.png') }}" alt="" width="36" height="36" class="h-9 w-9 shrink-0 rounded-xl object-cover">
                <span class="text-[15px] font-semibold tracking-tight">CampusConnect</span>
            </a>
            @endauth

            <nav class="flex flex-col gap-1">
                @php $r = request()->route()?->getName(); @endphp
                <a href="{{ route('feed.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'feed') ? 'is-active' : '' }}"><x-ui-icon name="home" class="h-5 w-5" />Feed</a>
                @auth
                    {{-- The React layout carries this link too. Both sidebars
                         have to list it, or it appears on half the site. --}}
                    <a href="{{ route('assistant.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'assistant') ? 'is-active' : '' }}"><x-ui-icon name="lightbulb" class="h-5 w-5" />Assistant</a>
                @endauth
                <a href="{{ route('network.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'network') ? 'is-active' : '' }}"><x-ui-icon name="users" class="h-5 w-5" />Network</a>
                @auth
                    <a href="{{ route('connect-requests.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'connect-requests') ? 'is-active' : '' }}">
                        <x-ui-icon name="handshake" class="h-5 w-5" />Applications
                        @if(($pendingApplications ?? 0) > 0)<span class="ml-auto rounded-full bg-primary-500 text-white text-[11px] font-semibold px-2 py-0.5">{{ $pendingApplications }}</span>@endif
                    </a>
                @endauth
                <a href="{{ route('messages.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'messages') ? 'is-active' : '' }}">
                    <x-ui-icon name="message" class="h-5 w-5" />Messages
                    @if(($unreadMessages ?? 0) > 0)<span class="ml-auto rounded-full bg-primary-500 text-white text-[11px] font-semibold px-2 py-0.5">{{ $unreadMessages }}</span>@endif
                </a>
                <a href="{{ route('clubs.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'clubs') ? 'is-active' : '' }}"><x-ui-icon name="award" class="h-5 w-5" />Clubs</a>
                <a href="{{ route('events.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'events') ? 'is-active' : '' }}"><x-ui-icon name="calendar" class="h-5 w-5" />Events</a>
                <a href="{{ route('calendar.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'calendar') ? 'is-active' : '' }}"><x-ui-icon name="calendar" class="h-5 w-5" />Academic calendar</a>
                <a href="{{ route('groups.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'groups') ? 'is-active' : '' }}">
                    <x-ui-icon name="users" class="h-5 w-5" />Groups
                    {{-- Unopened course and club announcements. --}}
                    @if(($unreadGroupNotices ?? 0) > 0)<span class="ml-auto rounded-full bg-primary-500 text-white text-[11px] font-semibold px-2 py-0.5">{{ $unreadGroupNotices }}</span>@endif
                </a>
                <a href="{{ route('notices.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'notices') ? 'is-active' : '' }}"><x-ui-icon name="flag" class="h-5 w-5" />Notices</a>
                <a href="{{ route('marketplace.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'marketplace') ? 'is-active' : '' }}"><x-ui-icon name="shopping-bag" class="h-5 w-5" />Marketplace</a>
                <a href="{{ route('lost-found.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'lost-found') ? 'is-active' : '' }}"><x-ui-icon name="inbox" class="h-5 w-5" />Lost & Found</a>

                {{-- The university's own systems, each explained, in one dialog. --}}
                @auth
                    <x-uiu-sites />
                @endauth

                @auth
                    @if(auth()->user()->canIssueSectionAnnouncements())
                        <a href="{{ route('administration.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'administration') ? 'is-active' : '' }}"><x-ui-icon name="building" class="h-5 w-5" />Administration</a>
                    @endif
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin-center.index') }}" class="nav-link {{ $r === 'admin-center.index' ? 'is-active' : '' }}"><x-ui-icon name="shield" class="h-5 w-5" />Admin Center</a>
                        <a href="{{ route('user-approvals.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'user-approvals') ? 'is-active' : '' }}"><x-ui-icon name="user-plus" class="h-5 w-5" />User Approvals</a>
                        <a href="{{ route('moderation.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'moderation') ? 'is-active' : '' }}"><x-ui-icon name="flag" class="h-5 w-5" />Moderation</a>
                        <a href="{{ url('/admin') }}" class="nav-link"><x-ui-icon name="settings" class="h-5 w-5" />Filament Admin</a>
                    @endif
                @endauth
            </nav>

            <div class="mt-auto pt-6">
                @auth
                <div class="flex items-center gap-1 px-1">
                    <a href="{{ route('profile.edit') }}" class="btn-icon" title="Settings"><x-ui-icon name="settings" class="h-4.5 w-4.5" /></a>
                    <span class="ml-auto truncate text-xs text-ink-500 dark:text-ink-400">{{ auth()->user()->roleLabel() }}</span>
                </div>
                @endauth
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-ink-100 dark:border-ink-800/70 bg-[var(--surface-page)]/80 backdrop-blur-xl px-4 py-3 lg:px-8">
                <div class="flex min-w-0 flex-1 justify-center">
                    <form action="{{ route('search.index') }}" method="GET" class="relative w-full max-w-md">
                        <x-ui-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-ink-400" />
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search" class="field pl-10 py-2 bg-ink-900/[0.03] dark:bg-white/[0.04] border-transparent focus:bg-[var(--surface-sunken)]">
                    </form>
                </div>

                <div class="flex shrink-0 items-center gap-1.5">
                    <button type="button" x-data @click="window.setTheme(window.currentTheme() === 'dark' ? 'light' : 'dark')" class="btn-icon active:rotate-180">
                        <x-ui-icon name="sun" class="h-5 w-5 dark:hidden" />
                        <x-ui-icon name="moon" class="h-5 w-5 hidden dark:block" />
                    </button>
                    @auth
                    <form method="POST" action="{{ route('logout') }}" class="contents">
                        @csrf
                        <button type="submit" class="btn-icon" title="Sign out" aria-label="Sign out">
                            <x-ui-icon name="logout" class="h-5 w-5" />
                        </button>
                    </form>
                    <a href="{{ route('notifications.index') }}" class="btn-icon relative">
                        <x-ui-icon name="bell" class="h-5 w-5" />
                        @if(($unreadNotifications ?? 0) > 0)
                            <span class="absolute right-1.5 top-1.5 flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary-400 opacity-75"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-primary-500"></span>
                            </span>
                        @endif
                    </a>
                    @endauth
                </div>
            </header>

            @if (session('success') || session('error') || session('warning'))
                <div class="px-4 pt-4 lg:px-8">
                    @if (session('success'))
                        <div class="surface-flat flex items-center gap-2 border-l-4 border-l-emerald-500 px-4 py-3 text-sm">{{ session('success') }}</div>
                    @elseif (session('warning'))
                        <div class="surface-flat flex items-center gap-2 border-l-4 border-l-amber-500 px-4 py-3 text-sm">{{ session('warning') }}</div>
                    @else
                        <div class="surface-flat flex items-center gap-2 border-l-4 border-l-red-500 px-4 py-3 text-sm">{{ session('error') }}</div>
                    @endif
                </div>
            @endif

            <main class="flex-1 px-4 py-6 lg:px-8">
                <div class="animate-page-in">
                    {{ $slot }}
                </div>
            </main>

            <nav class="lg:hidden sticky bottom-0 z-30 flex items-center justify-around border-t border-ink-100 dark:border-ink-800/70 bg-[var(--surface-page)]/95 backdrop-blur-xl px-2 py-2">
                <a href="{{ route('feed.index') }}" class="btn-icon"><x-ui-icon name="home" class="h-5 w-5" /></a>
                @auth
                    <a href="{{ route('assistant.index') }}" class="btn-icon"><x-ui-icon name="lightbulb" class="h-5 w-5" /></a>
                @endauth
                <a href="{{ route('network.index') }}" class="btn-icon"><x-ui-icon name="users" class="h-5 w-5" /></a>
                <a href="{{ route('messages.index') }}" class="btn-icon"><x-ui-icon name="message" class="h-5 w-5" /></a>
                <a href="{{ route('events.index') }}" class="btn-icon"><x-ui-icon name="calendar" class="h-5 w-5" /></a>
                <a href="{{ route('profile.me') }}" class="btn-icon"><x-ui-icon name="users" class="h-5 w-5" /></a>
            </nav>
        </div>
    </div>

    <div class="toast-stack" x-data="{ items: [] }" x-init="window.addEventListener('cc-toast', (e) => {
            const id = Date.now() + Math.random();
            items.push({ id, message: e.detail.message, tone: e.detail.tone || 'default' });
            setTimeout(() => { items = items.filter(t => t.id !== id) }, 3200);
        })">
        <template x-for="item in items" :key="item.id">
            <div class="toast" :class="{ 'is-success': item.tone === 'success', 'is-error': item.tone === 'error' }" x-text="item.message"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 -translate-y-2 scale-95"></div>
        </template>
    </div>

    <x-messenger-widget />

    @stack('scripts')
</body>
</html>
