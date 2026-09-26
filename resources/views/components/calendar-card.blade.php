{{--
    Feed rail: the next few dates on the viewer's own academic calendar.
    The whole card opens the full calendar, where every program's calendar
    can be looked at and a different one chosen for this card.
--}}
@php
    use App\Support\AcademicCalendar;

    $viewer = auth()->user();
    $program = AcademicCalendar::programFor($viewer);
    $rows = AcademicCalendar::upcoming(4, $viewer);
    $term = AcademicCalendar::trimester($viewer);
@endphp

<a href="{{ route('calendar.index') }}" class="rail-card group block transition hover:border-primary-300 dark:hover:border-primary-700" aria-label="Open the full academic calendar">
    <div class="mb-3 flex items-start justify-between gap-2">
        <div class="min-w-0">
            <h3 class="inline-flex items-center gap-1.5"><x-ui-icon name="calendar" class="h-4 w-4 text-primary-500" />Academic calendar</h3>
            <p class="mt-0.5 truncate text-[11px] text-ink-400">{{ trim($term.' · '.AcademicCalendar::PROGRAMS[$program], ' ·') }}</p>
        </div>
        <x-ui-icon name="chevron-right" class="mt-0.5 h-4 w-4 shrink-0 text-ink-300 transition group-hover:translate-x-0.5 group-hover:text-primary-500" />
    </div>

    @if (empty($rows))
        <p class="text-sm text-ink-500 dark:text-ink-400">Nothing left on this calendar. The next one appears here once UIU publishes it.</p>
    @else
        <div class="space-y-2.5">
            @foreach ($rows as $row)
                @php
                    $t = AcademicCalendar::type($row['type']);
                    $urgent = $row['in_progress'] || $row['days_away'] <= 3;
                @endphp
                <div class="flex items-start gap-2.5">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg {{ $t['chip'] }}">
                        <x-ui-icon :name="$t['icon']" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] font-medium leading-snug text-ink-800 dark:text-ink-100">{{ $row['label'] }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-ink-500 dark:text-ink-400">
                            <span class="whitespace-nowrap">{{ $row['start']->format('M j') }}@if ($row['is_range']) &ndash; {{ $row['end']->format('M j') }}@endif</span>
                            <span class="text-ink-300 dark:text-ink-600">&middot;</span>
                            <span @class([
                                'whitespace-nowrap rounded-full px-1.5 py-0.5 text-[11px] font-semibold',
                                'bg-red-500/10 text-red-600 dark:text-red-300' => $urgent,
                                'bg-ink-900/[0.05] text-ink-600 dark:bg-white/[0.07] dark:text-ink-300' => ! $urgent,
                            ])>{{ AcademicCalendar::relative($row) }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <p class="mt-4 text-center text-xs font-medium text-primary-600 group-hover:underline dark:text-primary-300">See the whole calendar</p>
</a>
