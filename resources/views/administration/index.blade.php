<x-app-layout title="Administration — UIU CampusConnect">
    <div class="mx-auto max-w-2xl" x-data="{ open: false }">
        @if ($canManage)
            <div class="mb-5 flex justify-end">
                <button type="button" class="btn-primary" @click="open = true"><x-ui-icon name="plus" class="h-4 w-4" />Publish update</button>
            </div>
            <div x-show="open" x-cloak class="surface mb-6 p-6">
                <form action="{{ route('administration.store') }}" method="POST" class="grid grid-cols-2 gap-3">
                    @csrf
                    <input name="course_code" placeholder="Course code" required class="field">
                    <input name="section_name" placeholder="Section" required class="field">
                    <input name="schedule_pattern" placeholder="Schedule (e.g. Sun-Wed)" required class="field col-span-2">
                    <input type="time" name="start_time" required class="field">
                    <input type="time" name="end_time" required class="field">
                    <input name="room" placeholder="Room" required class="field">
                    <input type="number" name="seat_limit" placeholder="Seats" required class="field">
                    <button type="submit" class="btn-primary col-span-2">Publish</button>
                </form>
            </div>
        @endif

        <div class="space-y-3">
            @forelse ($sections as $section)
                <div class="surface p-5">
                    <div class="flex items-center justify-between">
                        <span class="chip">{{ $section->course_code }} {{ $section->section_name }}</span>
                        <span class="text-xs text-ink-400">{{ $section->created_at->format('M j, Y') }}</span>
                    </div>
                    <p class="mt-2 text-sm">{{ $section->schedule_pattern }} · {{ $section->start_time }}–{{ $section->end_time }} · {{ $section->room }} · {{ $section->seat_limit }} seats</p>
                    <p class="mt-2 text-xs text-ink-400">{{ $section->issuer->full_name }}</p>
                </div>
            @empty
                <p class="text-sm text-ink-400">No updates yet</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
