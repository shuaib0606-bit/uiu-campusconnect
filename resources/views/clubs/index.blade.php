<x-app-layout title="Clubs - UIU CampusConnect">
    <div class="mx-auto max-w-5xl">
        <div class="section-heading mb-5">
            <div>
                <h1>University Clubs</h1>
                <p class="section-sub">Discover verified clubs, request membership, see executives, and follow club events.</p>
            </div>
        </div>

        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="stat-card items-center text-center"><span class="stat-value">{{ $clubs->count() }}</span><span class="stat-label">Active Clubs</span></div>
            <div class="stat-card items-center text-center"><span class="stat-value">{{ $clubs->sum('members_count') }}</span><span class="stat-label">Members</span></div>
            <div class="stat-card items-center text-center"><span class="stat-value">{{ $clubs->sum('events_count') }}</span><span class="stat-label">Events</span></div>
            <div class="stat-card items-center text-center"><span class="stat-value">{{ $clubs->sum('pending_requests_count') }}</span><span class="stat-label">Pending Requests</span></div>
        </div>

        <form method="GET" class="relative mb-5">
            <x-ui-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-ink-400" />
            <input type="search" name="q" value="{{ $q }}" placeholder="Search clubs by name, category, or description" class="field pl-10">
        </form>

        @if ($clubs->isEmpty())
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="award" class="h-6 w-6" /></span>
                <h3>No clubs found</h3>
                <p>Try a different search term.</p>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($clubs as $club)
                    <article class="surface surface-hover flex flex-col overflow-hidden">
                        @if ($club->cover_path)
                            <div class="h-28 w-full bg-cover bg-center" style="background-image:url('{{ Storage::url($club->cover_path) }}')"></div>
                        @endif
                        <div class="flex flex-1 flex-col p-5">
                            <div class="flex items-center gap-3">
                                @if ($club->logo_path)
                                    <img src="{{ Storage::url($club->logo_path) }}" alt="{{ $club->name }} logo" class="h-12 w-12 shrink-0 rounded-2xl object-cover">
                                @else
                                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-primary-500/10 text-primary-600 dark:text-primary-300"><x-ui-icon name="award" class="h-6 w-6" /></span>
                                @endif
                                <div class="min-w-0">
                                    <h2 class="truncate text-[15px] font-semibold"><a href="{{ route('clubs.show', $club) }}" class="hover:underline">{{ $club->name }}</a></h2>
                                    <span class="chip">{{ $club->category }}</span>
                                </div>
                            </div>

                            <p class="mt-3 flex-1 text-sm text-ink-600 dark:text-ink-300">{{ \Illuminate\Support\Str::limit($club->description, 140) }}</p>

                            <p class="mt-3 text-sm"><span class="font-semibold">{{ $club->members_count }}</span> members &middot; {{ $club->events_count }} events</p>
                            <p class="section-sub">Moderator: {{ $club->moderator->full_name ?? 'Not assigned' }}</p>
                            @if ($club->meeting_time || $club->meeting_place)
                                <p class="section-sub">{{ $club->meeting_time }}{{ $club->meeting_time && $club->meeting_place ? ' · ' : '' }}{{ $club->meeting_place }}</p>
                            @endif

                            <a href="{{ route('clubs.show', $club) }}" class="btn-primary mt-4 w-fit">Open Club</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
