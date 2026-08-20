@props(['title' => 'UIU CampusConnect'])
<!DOCTYPE html>
<html lang="en" class="{{ ($activeTheme ?? 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'UIU CampusConnect' }}</title>
    <link rel="icon" href="data:image/svg+xml,{{ rawurlencode('<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22><rect width=%2224%22 height=%2224%22 rx=%226%22 fill=%22%23c15f3c%22/><text x=%2212%22 y=%2216.5%22 font-family=%22Arial%22 font-size=%2212%22 fill=%22white%22 text-anchor=%22middle%22 font-weight=%22700%22>U</text></svg>') }}">
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
        <aside class="hidden lg:flex lg:w-[264px] shrink-0 flex-col border-r border-ink-100 dark:border-ink-800/70 px-4 py-6">
            <a href="{{ route('feed.index') }}" class="flex items-center gap-2.5 px-2 mb-8">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-primary-500 text-white font-bold">U</span>
                <span class="text-[15px] font-semibold tracking-tight">CampusConnect</span>
            </a>

            <nav class="flex flex-col gap-1">
                @php $r = request()->route()?->getName(); @endphp
                <a href="{{ route('feed.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'feed') ? 'is-active' : '' }}"><x-ui-icon name="home" class="h-5 w-5" />Feed</a>
                <a href="{{ route('network.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'network') ? 'is-active' : '' }}"><x-ui-icon name="users" class="h-5 w-5" />Network</a>
                <a href="{{ route('messages.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'messages') ? 'is-active' : '' }}">
                    <x-ui-icon name="message" class="h-5 w-5" />Messages
                    @if(($unreadMessages ?? 0) > 0)<span class="ml-auto rounded-full bg-primary-500 text-white text-[11px] font-semibold px-2 py-0.5">{{ $unreadMessages }}</span>@endif
                </a>
                <a href="{{ route('clubs.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'clubs') ? 'is-active' : '' }}"><x-ui-icon name="award" class="h-5 w-5" />Clubs</a>
                <a href="{{ route('events.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'events') ? 'is-active' : '' }}"><x-ui-icon name="calendar" class="h-5 w-5" />Events</a>
                <a href="{{ route('groups.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'groups') ? 'is-active' : '' }}"><x-ui-icon name="users" class="h-5 w-5" />Groups</a>
                <a href="{{ route('notices.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'notices') ? 'is-active' : '' }}"><x-ui-icon name="flag" class="h-5 w-5" />Notices</a>
                <a href="{{ route('marketplace.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'marketplace') ? 'is-active' : '' }}"><x-ui-icon name="shopping-bag" class="h-5 w-5" />Marketplace</a>
                <a href="{{ route('lost-found.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'lost-found') ? 'is-active' : '' }}"><x-ui-icon name="inbox" class="h-5 w-5" />Lost & Found</a>

                @auth
                    @if(auth()->user()->canIssueSectionAnnouncements())
                        <a href="{{ route('administration.index') }}" class="nav-link {{ str_starts_with($r ?? '', 'administration') ? 'is-active' : '' }}"><x-ui-icon name="building" class="h-5 w-5" />Administration</a>
                    @endif
                    @if(auth()->user()->isAdmin())
                        <a href="{{ url('/admin') }}" class="nav-link"><x-ui-icon name="settings" class="h-5 w-5" />Admin Panel</a>
                    @endif
                @endauth
            </nav>

            <div class="mt-auto pt-6">
                @auth
                <div class="surface-flat flex items-center gap-3 p-3">
                    <a href="{{ route('profile.show', auth()->user()) }}" class="flex items-center gap-3 min-w-0">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary-100 text-primary-700 text-sm font-semibold dark:bg-primary-900/40 dark:text-primary-200">{{ auth()->user()->avatar_initials }}</span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium">{{ auth()->user()->full_name }}</span>
                            <span class="block truncate text-xs text-ink-500 dark:text-ink-400">{{ auth()->user()->roleLabel() }}</span>
                        </span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                        @csrf
                        <button class="btn-icon" title="Sign out"><x-ui-icon name="logout" class="h-4.5 w-4.5" /></button>
                    </form>
                </div>
                @endauth
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-ink-100 dark:border-ink-800/70 bg-[#fbfaf8]/80 dark:bg-[#121110]/80 backdrop-blur-xl px-4 py-3 lg:px-8">
                <form action="{{ route('search.index') }}" method="GET" class="relative w-full max-w-md">
                    <x-ui-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-ink-400" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search" class="field pl-10 py-2 bg-ink-900/[0.03] dark:bg-white/[0.04] border-transparent focus:bg-white dark:focus:bg-[#181614]">
                </form>

                <div class="ml-auto flex items-center gap-1.5">
                    <button type="button" x-data @click="window.setTheme(window.currentTheme() === 'dark' ? 'light' : 'dark')" class="btn-icon">
                        <x-ui-icon name="sun" class="h-5 w-5 dark:hidden" />
                        <x-ui-icon name="moon" class="h-5 w-5 hidden dark:block" />
                    </button>
                    @auth
                    <a href="{{ route('notifications.index') }}" class="btn-icon relative">
                        <x-ui-icon name="bell" class="h-5 w-5" />
                        @if(($unreadNotifications ?? 0) > 0)<span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-primary-500"></span>@endif
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
                {{ $slot }}
            </main>

            <nav class="lg:hidden sticky bottom-0 z-30 flex items-center justify-around border-t border-ink-100 dark:border-ink-800/70 bg-[#fbfaf8]/95 dark:bg-[#121110]/95 backdrop-blur-xl px-2 py-2">
                <a href="{{ route('feed.index') }}" class="btn-icon"><x-ui-icon name="home" class="h-5 w-5" /></a>
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
            <div class="toast" :class="{ 'is-success': item.tone === 'success', 'is-error': item.tone === 'error' }" x-text="item.message" x-transition.opacity></div>
        </template>
    </div>
</body>
</html>
