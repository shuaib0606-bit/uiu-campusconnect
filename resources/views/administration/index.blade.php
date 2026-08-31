@php $latest = $sections->first(); @endphp
<x-app-layout title="Administration - UIU CampusConnect">
    <div class="mx-auto max-w-2xl">
        <div class="surface mb-5 flex flex-wrap items-center justify-between gap-6 p-6">
            <div class="max-w-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-300">University administration</p>
                <h1 class="mt-1 text-[19px] font-semibold">Academic section updates</h1>
                <p class="section-sub mt-1">Find official course section, seat, room and schedule updates from the registrar or authorised university office.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('notices.index') }}" class="btn-primary">View notices</a>
                    @if ($canManage)<a href="#publish-section" class="btn-secondary">Publish update</a>@endif
                </div>
            </div>
            <div class="grid shrink-0 grid-cols-3 gap-2 sm:grid-cols-1">
                <div class="stat-card items-center text-center sm:items-start sm:text-left"><span class="stat-value">{{ $sections->count() }}</span><span class="stat-label">Total updates</span></div>
                <div class="stat-card items-center text-center sm:items-start sm:text-left"><span class="stat-value text-base">{{ $latest ? trim($latest->course_code.' '.$latest->section_name) : 'None yet' }}</span><span class="stat-label">Latest section</span></div>
                <div class="stat-card items-center text-center sm:items-start sm:text-left"><span class="stat-value text-base">Official only</span><span class="stat-label">Office rule</span></div>
            </div>
        </div>

        @if ($canManage)
            <details id="publish-section" class="surface mb-6 p-6">
                <summary class="cursor-pointer text-[15px] font-semibold">Publish a section update</summary>
                <form action="{{ route('administration.store') }}" method="POST" class="mt-4 grid grid-cols-2 gap-3">
                    @csrf
                    <input name="course_code" placeholder="Course code" required class="field">
                    <input name="section_name" placeholder="Section" required class="field">
                    <input name="schedule_pattern" placeholder="Schedule (e.g. Sun-Wed)" required class="field col-span-2">
                    <input type="time" name="start_time" required class="field">
                    <input type="time" name="end_time" required class="field">
                    <input name="room" placeholder="Room" required class="field">
                    <input type="number" name="seat_limit" placeholder="Seats" required class="field">
                    <button type="submit" class="btn-primary col-span-2">Publish update</button>
                </form>
            </details>
        @endif

        <h2 class="mb-2 text-sm font-semibold text-ink-500 dark:text-ink-400">Recent section announcements</h2>
        <div class="space-y-3">
            @forelse ($sections as $section)
                <div class="surface flex gap-4 p-5">
                    <div class="flex w-14 shrink-0 flex-col items-center rounded-xl bg-ink-900/[0.04] py-2 dark:bg-white/[0.06]">
                        <strong class="text-sm">{{ strtoupper(substr($section->course_code, 0, 3)) }}</strong>
                        <span class="text-xs text-ink-400">{{ $section->section_name }}</span>
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="chip">Registrar update</span>
                            <span class="section-sub">{{ $section->created_at->format('M j, Y · g:i A') }}</span>
                        </div>
                        <h3 class="mt-1 text-[15px] font-semibold">{{ $section->title ?: $section->course_code.' section update' }}</h3>
                        <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-500 dark:text-ink-400">
                            <span class="inline-flex items-center gap-1"><x-ui-icon name="calendar" class="h-3.5 w-3.5" />{{ $section->schedule_pattern }} · {{ $section->start_time }}-{{ $section->end_time }}</span>
                            <span class="inline-flex items-center gap-1"><x-ui-icon name="map-pin" class="h-3.5 w-3.5" />{{ $section->room }}</span>
                            <span class="inline-flex items-center gap-1"><x-ui-icon name="users" class="h-3.5 w-3.5" />{{ $section->seat_limit }} seats</span>
                        </div>
                        <p class="section-sub mt-1.5">Issued by {{ $section->issuer->full_name }}</p>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <span class="empty-icon"><x-ui-icon name="building" class="h-6 w-6" /></span>
                    <h3>No administration updates yet</h3>
                    <p>New section or schedule updates will appear here when they are published.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
