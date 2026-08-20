<x-app-layout title="Search — UIU CampusConnect">
    <div class="mx-auto max-w-2xl space-y-6">
        <form method="GET" class="relative">
            <x-ui-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-ink-400" />
            <input type="search" name="q" value="{{ $q }}" autofocus class="field pl-10">
        </form>

        @if ($q !== '')
            @if ($users->isNotEmpty())
                <div><h2 class="mb-2 text-sm font-semibold text-ink-500">People</h2>
                    <div class="surface divide-y divide-ink-100 dark:divide-ink-800/70">
                        @foreach ($users as $u)
                            <a href="{{ route('profile.show', $u) }}" class="flex items-center gap-3 px-4 py-3"><span class="grid h-9 w-9 place-items-center rounded-full bg-ink-900/5 dark:bg-white/10 text-xs font-semibold">{{ $u->avatar_initials }}</span>{{ $u->full_name }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
            @if ($clubs->isNotEmpty())
                <div><h2 class="mb-2 text-sm font-semibold text-ink-500">Clubs</h2>
                    <div class="surface divide-y divide-ink-100 dark:divide-ink-800/70">
                        @foreach ($clubs as $c)<a href="{{ route('clubs.show', $c) }}" class="block px-4 py-3">{{ $c->name }}</a>@endforeach
                    </div>
                </div>
            @endif
            @if ($events->isNotEmpty())
                <div><h2 class="mb-2 text-sm font-semibold text-ink-500">Events</h2>
                    <div class="surface divide-y divide-ink-100 dark:divide-ink-800/70">
                        @foreach ($events as $e)<a href="{{ route('events.show', $e) }}" class="block px-4 py-3">{{ $e->title }}</a>@endforeach
                    </div>
                </div>
            @endif
            @if ($posts->isNotEmpty())
                <div><h2 class="mb-2 text-sm font-semibold text-ink-500">Posts</h2>
                    <div class="space-y-3">@foreach ($posts as $p)@include('feed._post', ['post' => $p])@endforeach</div>
                </div>
            @endif
            @if ($users->isEmpty() && $clubs->isEmpty() && $events->isEmpty() && $posts->isEmpty())
                <p class="text-sm text-ink-400">No results</p>
            @endif
        @endif
    </div>
</x-app-layout>
