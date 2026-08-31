<x-app-layout title="Events - UIU CampusConnect">
    <div class="mx-auto max-w-4xl">
        <div class="surface mb-5 flex flex-wrap items-center justify-between gap-4 p-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-300">Campus activities</p>
                <h1 class="mt-1 text-[22px] font-semibold tracking-tight">Events</h1>
                <p class="section-sub mt-1 max-w-md">Find workshops, club activities, academic sessions, and university-wide events in one clean place.</p>
            </div>
            @if ($canCreateEvent)
                <a href="#create-event" class="btn-primary shrink-0">Create Event</a>
            @endif
        </div>

        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="stat-card items-center text-center"><span class="stat-value">{{ $events->count() }}</span><span class="stat-label">Published events</span></div>
            <div class="stat-card items-center text-center"><span class="stat-value">{{ $events->whereNotNull('club_id')->count() }}</span><span class="stat-label">Club events</span></div>
            <div class="stat-card items-center text-center"><span class="stat-value">{{ $events->sum('registrants_count') }}</span><span class="stat-label">Registrations</span></div>
            <div class="stat-card items-center text-center"><span class="stat-value">{{ $events->sum('interested_count') }}</span><span class="stat-label">Interested</span></div>
        </div>

        <div class="surface-flat mb-5 flex flex-wrap gap-1.5 p-2">
            <a href="{{ route('events.index') }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $type === '' ? 'bg-primary-500 text-white' : 'text-ink-600 dark:text-ink-300' }}">All</a>
            @foreach ($categories as $cat)
                <a href="{{ route('events.index', ['type' => $cat]) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $type === $cat ? 'bg-primary-500 text-white' : 'text-ink-600 dark:text-ink-300' }}">{{ $cat }}</a>
            @endforeach
        </div>

        @if ($canCreateEvent)
            <details id="create-event" class="surface mb-5 p-6">
                <summary class="cursor-pointer text-[15px] font-semibold">Create a campus event</summary>
                <form method="POST" action="{{ route('events.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-2">
                        <label class="block"><span class="mb-1 block text-xs font-medium">Title</span><input name="title" placeholder="Example: AI Workshop" required class="field"></label>
                        <label class="block"><span class="mb-1 block text-xs font-medium">Category</span><input name="category" placeholder="Workshop / Career / Club Activity" required class="field"></label>
                    </div>
                    <label class="block"><span class="mb-1 block text-xs font-medium">Description</span><textarea name="description" rows="3" required placeholder="Briefly explain what participants will get from this event." class="field"></textarea></label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="block"><span class="mb-1 block text-xs font-medium">Start</span><input type="datetime-local" name="start_datetime" required class="field"></label>
                        <label class="block"><span class="mb-1 block text-xs font-medium">End</span><input type="datetime-local" name="end_datetime" required class="field"></label>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="block"><span class="mb-1 block text-xs font-medium">Location</span><input name="location" placeholder="Auditorium / Room / Online" required class="field"></label>
                        <label class="block"><span class="mb-1 block text-xs font-medium">Capacity</span><input name="capacity" type="number" value="80" min="1" class="field"></label>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="block"><span class="mb-1 block text-xs font-medium">Registration link</span><input name="registration_url" placeholder="https://..." class="field"></label>
                        <label class="block"><span class="mb-1 block text-xs font-medium">Event poster</span><input type="file" name="poster" accept=".jpg,.jpeg,.png,.webp" class="field !py-1.5"></label>
                    </div>
                    <label class="block"><span class="mb-1 block text-xs font-medium">Post as</span>
                        <select name="club_id" class="field">
                            <option value="">University-level event</option>
                            @foreach ($clubs as $club)
                                <option value="{{ $club->id }}">{{ $club->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="btn-primary w-full">Publish Event</button>
                </form>
            </details>
        @endif

        @if ($events->isEmpty())
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="calendar" class="h-6 w-6" /></span>
                <h3>No events found</h3>
                <p>Try another category or check again later.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($events as $event)
                    @php
                        $month = $event->start_datetime->format('M');
                        $day = $event->start_datetime->format('d');
                        $dateLabel = $event->start_datetime->format('D, M j');
                        $timeLabel = $event->start_datetime->format('g:i A');
                        $cap = min(100, (int) round(($event->registrants_count / max(1, $event->capacity)) * 100));
                    @endphp
                    <article class="surface flex flex-col gap-4 p-5 sm:flex-row">
                        <a href="{{ route('events.show', $event) }}" class="block h-32 w-full shrink-0 overflow-hidden rounded-xl sm:h-auto sm:w-40">
                            @if ($event->poster_path)
                                <img src="{{ Storage::url($event->poster_path) }}" alt="Event poster for {{ $event->title }}" class="h-full w-full object-cover">
                            @else
                                <span class="flex h-full w-full flex-col items-center justify-center gap-1.5 bg-primary-500/10 text-primary-600 dark:text-primary-300">
                                    <x-ui-icon name="calendar" class="h-6 w-6" /><span class="text-xs font-medium">Campus Event</span>
                                </span>
                            @endif
                        </a>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start gap-3">
                                <div class="flex w-11 shrink-0 flex-col items-center rounded-xl bg-ink-900/[0.04] dark:bg-white/[0.06] py-1.5">
                                    <span class="text-[10px] font-semibold uppercase text-ink-400">{{ $month }}</span>
                                    <span class="text-base font-bold">{{ $day }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap gap-1.5">
                                        <span class="chip">{{ $event->category }}</span>
                                        @if ($event->club)<span class="chip !bg-blue-500/10 !text-blue-700 dark:!text-blue-300">{{ $event->club->name }}</span>@endif
                                    </div>
                                    <h2 class="mt-1 truncate text-[15px] font-semibold"><a href="{{ route('events.show', $event) }}" class="hover:underline">{{ $event->title }}</a></h2>
                                    <p class="section-sub">Hosted by {{ $event->club->name ?? $event->organizer->full_name }}</p>
                                </div>
                            </div>

                            <p class="mt-2 text-sm text-ink-600 dark:text-ink-300">{{ \Illuminate\Support\Str::limit(strip_tags($event->description), 170) }}</p>

                            <div class="mt-2 flex flex-wrap gap-4 text-xs text-ink-500 dark:text-ink-400">
                                <span class="inline-flex items-center gap-1"><x-ui-icon name="calendar" class="h-3.5 w-3.5" />{{ $dateLabel }}{{ $timeLabel ? ' · '.$timeLabel : '' }}</span>
                                <span class="inline-flex items-center gap-1"><x-ui-icon name="map-pin" class="h-3.5 w-3.5" />{{ $event->location }}</span>
                            </div>

                            <div class="mt-2">
                                <div class="flex justify-between text-xs text-ink-500 dark:text-ink-400"><span>{{ $event->registrants_count }} registered</span><span>{{ $event->capacity }} seats</span></div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-ink-900/[0.06] dark:bg-white/[0.08]"><span class="block h-full rounded-full bg-primary-500" style="width: {{ $cap }}%"></span></div>
                            </div>

                            <div class="mt-2 flex gap-3 text-xs text-ink-500 dark:text-ink-400">
                                <span>{{ $event->interested_count }} interested</span>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="{{ route('events.show', $event) }}" class="btn-secondary !px-3 !py-1.5 text-xs">Details</a>
                                @auth
                                    <form method="POST" action="{{ route('events.register', $event) }}"><button type="submit" class="btn-primary !px-3 !py-1.5 text-xs">Register</button></form>
                                    <form method="POST" action="{{ route('events.interest', $event) }}"><button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs">Interested</button></form>
                                @endauth
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
