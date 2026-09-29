{{--
    The feed's right-hand column: one tile per module - a big icon, its
    name and the single fact worth knowing at a glance. Clicking a tile
    slides the full module in from the right, so the feed itself stays calm.

    The panels are rendered with the page and only shown on click, so
    opening one is instant and works the same as the old rail did.
--}}
@props(['upcomingEvents', 'suggestedClubs'])

@php
    use App\Support\AcademicCalendar;
    use App\Support\Deadlines;
    use App\Support\ShuttleSchedule;

    $viewer = auth()->user();

    // Shuttle: the next few departures of every route; the tile picks the
    // route this person chose in the shuttle panel (kept in the browser).
    $shuttle = collect(ShuttleSchedule::allRoutes(4))->map(fn ($r) => [
        'key' => $r['key'],
        'name' => \Illuminate\Support\Str::before($r['name'], ' —'),
        'next' => collect($r['departures'])->map(fn ($d) => [
            'ts' => $d['timestamp'],
            'until' => $d['until_timestamp'],
            'label' => $d['label'],
        ])->values(),
    ])->values();

    // Deadlines: worked out once and handed to the panel as well.
    $deadlines = Deadlines::for($viewer, 30);
    $thisWeek = $deadlines->filter(fn ($i) => $i['state'] === 'overdue' || (Deadlines::daysLeft($i) >= 0 && Deadlines::daysLeft($i) <= 6));
    $overdue = $deadlines->where('state', 'overdue')->count();
    $nextDue = $deadlines->first(fn ($i) => ! in_array($i['state'], ['submitted', 'late', 'past', 'closed'], true));

    $calendarNext = AcademicCalendar::upcoming(1, $viewer)[0] ?? null;
    $nextEvent = $upcomingEvents->first();

    $tiles = [
        'shuttle' => ['title' => 'Shuttle', 'icon' => 'bus', 'tone' => 'bg-sky-500/12 text-sky-600 dark:text-sky-300'],
        'deadlines' => ['title' => 'Deadlines', 'icon' => 'hourglass', 'tone' => 'bg-orange-500/12 text-orange-600 dark:text-orange-300'],
        'calendar' => ['title' => 'Calendar', 'icon' => 'calendar', 'tone' => 'bg-primary-500/12 text-primary-600 dark:text-primary-300'],
        'events' => ['title' => 'Events', 'icon' => 'ticket', 'tone' => 'bg-pink-500/12 text-pink-600 dark:text-pink-300'],
        'clubs' => ['title' => 'Clubs', 'icon' => 'award', 'tone' => 'bg-amber-500/12 text-amber-600 dark:text-amber-300'],
    ];
@endphp

<div x-data="{ panel: null }"
     x-effect="document.documentElement.classList.toggle('overflow-hidden', panel !== null)"
     @keydown.escape.window="panel = null">

    <div class="grid grid-cols-2 gap-3">
        {{-- Shuttle: counts down live, for the route this person rides. --}}
        <button type="button" @click="panel = 'shuttle'" class="rail-tile"
            x-data="{
                routes: @js($shuttle),
                key: (() => { try { return localStorage.getItem('cc.shuttle.route') } catch (e) { return null } })(),
                now: Math.floor(Date.now() / 1000),
                init() { setInterval(() => this.now = Math.floor(Date.now() / 1000), 15000) },
                get route() { return this.routes.find((r) => r.key === this.key) || this.routes[0] },
                get next() { return this.route ? this.route.next.find((d) => (d.until || d.ts) > this.now) : null },
                get fact() {
                    if (!this.next) return 'No more today';
                    if (this.next.ts <= this.now) return 'Running now';
                    const m = Math.round((this.next.ts - this.now) / 60);
                    return m < 60 ? 'Next in ' + m + ' min' : 'Next in ' + Math.floor(m / 60) + 'h ' + (m % 60) + 'm';
                },
            }"
            @shuttle-route.window="key = $event.detail">
            <span class="rail-tile-icon {{ $tiles['shuttle']['tone'] }}"><x-ui-icon name="bus" class="h-6 w-6" /></span>
            <span class="rail-tile-title">Shuttle</span>
            <span class="rail-tile-fact" x-text="fact">Timetable</span>
            <span class="rail-tile-sub" x-text="route ? route.name : ''"></span>
        </button>

        <button type="button" @click="panel = 'deadlines'" class="rail-tile">
            <span class="rail-tile-icon {{ $tiles['deadlines']['tone'] }}"><x-ui-icon name="hourglass" class="h-6 w-6" /></span>
            <span class="rail-tile-title">Deadlines</span>
            <span @class(['rail-tile-fact', '!text-red-600 dark:!text-red-400' => $overdue > 0])>
                @if ($overdue > 0)
                    {{ $overdue }} overdue
                @elseif ($thisWeek->isNotEmpty())
                    {{ $thisWeek->count() }} this week
                @elseif ($deadlines->isNotEmpty())
                    Nothing this week
                @else
                    All clear
                @endif
            </span>
            <span class="rail-tile-sub">{{ $nextDue ? \Illuminate\Support\Str::limit($nextDue['title'], 28) : 'Next 30 days' }}</span>
        </button>

        <button type="button" @click="panel = 'calendar'" class="rail-tile">
            <span class="rail-tile-icon {{ $tiles['calendar']['tone'] }}"><x-ui-icon name="calendar" class="h-6 w-6" /></span>
            <span class="rail-tile-title">Calendar</span>
            <span class="rail-tile-fact">{{ $calendarNext ? AcademicCalendar::relative($calendarNext) : 'Nothing left' }}</span>
            <span class="rail-tile-sub">{{ $calendarNext ? \Illuminate\Support\Str::limit($calendarNext['label'], 28) : AcademicCalendar::PROGRAMS[AcademicCalendar::programFor($viewer)] }}</span>
        </button>

        <button type="button" @click="panel = 'events'" class="rail-tile">
            <span class="rail-tile-icon {{ $tiles['events']['tone'] }}"><x-ui-icon name="ticket" class="h-6 w-6" /></span>
            <span class="rail-tile-title">Events</span>
            <span class="rail-tile-fact">{{ $upcomingEvents->isEmpty() ? 'None coming up' : $upcomingEvents->count().' coming up' }}</span>
            <span class="rail-tile-sub">{{ $nextEvent ? $nextEvent->start_datetime->format('D, M j') : 'Browse events' }}</span>
        </button>

        <button type="button" @click="panel = 'clubs'" class="rail-tile">
            <span class="rail-tile-icon {{ $tiles['clubs']['tone'] }}"><x-ui-icon name="award" class="h-6 w-6" /></span>
            <span class="rail-tile-title">Clubs</span>
            <span class="rail-tile-fact">{{ $suggestedClubs->isEmpty() ? 'Browse clubs' : $suggestedClubs->count().' for you' }}</span>
            <span class="rail-tile-sub">{{ $suggestedClubs->first()?->name ? \Illuminate\Support\Str::limit($suggestedClubs->first()->name, 28) : 'Find your people' }}</span>
        </button>

        <button type="button" @click="panel = 'tools'" class="rail-tile">
            <span class="rail-tile-icon bg-violet-500/12 text-violet-600 dark:text-violet-300"><x-ui-icon name="calculator" class="h-6 w-6" /></span>
            <span class="rail-tile-title">Tools</span>
            <span class="rail-tile-fact">CGPA &amp; more</span>
            <span class="rail-tile-sub">Retake, tuition, grades</span>
        </button>
    </div>

    {{-- The side panel. Moved to <body> so no animated ancestor can pin it. --}}
    <template x-teleport="body">
    <div x-show="panel !== null" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true">
        <div x-show="panel !== null" x-transition.opacity class="absolute inset-0 bg-black/30 backdrop-blur-[1px]" @click="panel = null"></div>

        <div x-show="panel !== null"
             x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
             class="absolute inset-y-0 right-0 flex w-full max-w-[400px] flex-col border-l shadow-2xl"
             style="background: var(--surface-page); border-color: var(--hairline)">
            <div class="flex items-center justify-between gap-2 px-5 pb-2 pt-4">
                <span class="text-xs font-semibold uppercase tracking-wider text-ink-400"
                      x-text="{ shuttle: 'Shuttle', deadlines: 'Deadlines', calendar: 'Academic calendar', events: 'Events', clubs: 'Clubs', tools: 'Student tools' }[panel] || ''"></span>
                <button type="button" class="btn-icon" @click="panel = null" aria-label="Close"><x-ui-icon name="x" class="h-5 w-5" /></button>
            </div>

            <div class="flex-1 space-y-4 overflow-y-auto px-5 pb-6">
                <div x-show="panel === 'shuttle'"><x-shuttle-card /></div>
                <div x-show="panel === 'deadlines'"><x-deadlines-card :items="$deadlines" /></div>
                <div x-show="panel === 'calendar'"><x-calendar-card /></div>
                <div x-show="panel === 'tools'"><x-tools-card /></div>

                <div x-show="panel === 'events'" class="rail-card">
                    <h3 class="mb-3">Upcoming events</h3>
                    @if ($upcomingEvents->isEmpty())
                        <p class="text-sm text-ink-500 dark:text-ink-400">Nothing coming up right now.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($upcomingEvents as $event)
                                <a href="{{ route('events.show', $event) }}" class="group flex items-start gap-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-pink-500/10 text-pink-600 dark:text-pink-300"><x-ui-icon name="ticket" class="h-4 w-4" /></span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium group-hover:underline">{{ $event->title }}</span>
                                        <span class="block text-xs text-ink-500 dark:text-ink-400">{{ $event->start_datetime->format('D, M j, g:i A') }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <a href="{{ route('events.index') }}" class="mt-4 block text-center text-xs font-medium text-primary-600 hover:underline dark:text-primary-300">View all events</a>
                </div>

                <div x-show="panel === 'clubs'" class="rail-card">
                    <h3 class="mb-3">Clubs for you</h3>
                    @if ($suggestedClubs->isEmpty())
                        <p class="text-sm text-ink-500 dark:text-ink-400">You are in every club we would suggest.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($suggestedClubs as $club)
                                <a href="{{ route('clubs.show', $club) }}" class="group flex items-center gap-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-300"><x-ui-icon name="award" class="h-4 w-4" /></span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium group-hover:underline">{{ $club->name }}</span>
                                        <span class="block text-xs text-ink-500 dark:text-ink-400">{{ $club->members_count }} member{{ $club->members_count === 1 ? '' : 's' }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <a href="{{ route('clubs.index') }}" class="mt-4 block text-center text-xs font-medium text-primary-600 hover:underline dark:text-primary-300">Browse clubs</a>
                </div>
            </div>
        </div>
    </div>
</template>
</div>
