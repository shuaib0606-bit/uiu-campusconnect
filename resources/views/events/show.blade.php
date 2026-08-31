<x-app-layout title="{{ $event->title }} - UIU CampusConnect">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('events.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-900 dark:hover:text-white"><x-ui-icon name="chevron-right" class="h-4 w-4 rotate-180" />Back to Events</a>

        <div class="surface mb-5 overflow-hidden">
            @if ($event->poster_path)
                <img src="{{ Storage::url($event->poster_path) }}" alt="Event poster" class="max-h-80 w-full object-cover">
            @endif
            <div class="p-6">
                <h1 class="text-[22px] font-semibold tracking-tight">{{ $event->title }}</h1>
                <p class="section-sub mt-1">{{ $event->category }}{{ $event->club ? ' · '.$event->club->name : '' }}</p>
                <p class="mt-4 whitespace-pre-line text-sm text-ink-600 dark:text-ink-300">{{ $event->description }}</p>

                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="stat-card items-center text-center"><span class="stat-value">{{ $event->registrants_count }}/{{ $event->capacity }}</span><span class="stat-label">Registered</span></div>
                    <div class="stat-card items-center text-center"><span class="stat-value">{{ $event->interested_count }}</span><span class="stat-label">Interested</span></div>
                    <div class="stat-card items-center text-center"><span class="stat-value">{{ $checkinsCount }}</span><span class="stat-label">Checked in</span></div>
                    <div class="stat-card items-center text-center"><span class="stat-value">{{ $event->capacity - $event->registrants_count > 0 ? $event->capacity - $event->registrants_count : 0 }}</span><span class="stat-label">Seats left</span></div>
                </div>

                <div class="mt-5 space-y-1.5 text-sm">
                    <p><span class="font-semibold">Time:</span> {{ $event->start_datetime->format('M j, Y g:i A') }} to {{ $event->end_datetime->format('M j, Y g:i A') }}</p>
                    <p><span class="font-semibold">Venue:</span> {{ $event->location }}</p>
                    <p><span class="font-semibold">Organizer:</span> {{ $event->organizer->full_name }}</p>
                </div>

                @if ($event->registration_url)
                    <a href="{{ $event->registration_url }}" target="_blank" rel="noopener" class="btn-secondary mt-4 inline-flex">Open registration link</a>
                @endif

                @auth
                    <div class="mt-5 flex flex-wrap gap-2 border-t border-ink-100 dark:border-ink-800/70 pt-5">
                        <form method="POST" action="{{ route('events.register', $event) }}">@csrf<button type="submit" class="btn-primary">Register</button></form>
                        <form method="POST" action="{{ route('events.interest', $event) }}">@csrf<button type="submit" class="btn-secondary">Interested</button></form>
                    </div>
                @endauth
            </div>
        </div>

        @auth
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="surface p-6">
                    <h2 class="mb-2 text-[15px] font-semibold">Event Check-in</h2>
                    <p class="section-sub mb-3">Registered? Check in once you're at the venue.</p>
                    <form method="POST" action="{{ route('events.checkin', $event) }}">
                        @csrf
                        <button type="submit" class="btn-primary w-full">Check in</button>
                    </form>
                </div>
                <div class="surface p-6">
                    <h2 class="mb-3 text-[15px] font-semibold">Attendee List</h2>
                    <div class="space-y-3">
                        @forelse ($attendees as $attendee)
                            <div class="flex items-center gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary-100 text-primary-700 text-xs font-semibold dark:bg-primary-900/40 dark:text-primary-200">{{ $attendee->avatar_initials }}</span>
                                <div class="min-w-0">
                                    <a href="{{ route('profile.show', $attendee) }}" class="text-sm font-semibold hover:underline">{{ $attendee->full_name }}</a>
                                    <p class="section-sub truncate">{{ $attendee->department ?: ucfirst($attendee->role) }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-ink-400">No registrations yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endauth
    </div>
</x-app-layout>
