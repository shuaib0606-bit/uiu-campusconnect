@php
    use App\Support\AcademicCalendar;

    $rows = AcademicCalendar::upcoming(limit: 4);
    $trimester = AcademicCalendar::trimester();

    $tone = [
        'exam' => ['icon' => 'file-text', 'class' => 'bg-red-500/10 text-red-600 dark:text-red-300'],
        'payment' => ['icon' => 'tag', 'class' => 'bg-amber-500/10 text-amber-600 dark:text-amber-300'],
        'holiday' => ['icon' => 'calendar', 'class' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300'],
        'academic' => ['icon' => 'award', 'class' => 'bg-primary-500/10 text-primary-600 dark:text-primary-300'],
    ];
@endphp

<div class="rail-card">
    <div class="mb-3 flex items-center justify-between gap-2">
        <h3 class="inline-flex items-center gap-1.5"><x-ui-icon name="clock" class="h-4 w-4 text-primary-500" />Upcoming deadlines</h3>
        @if ($trimester)<span class="text-[11px] text-ink-400">{{ $trimester }}</span>@endif
    </div>

    @if (empty($rows))
        <p class="text-sm text-ink-500 dark:text-ink-400">Nothing scheduled for the rest of the trimester.</p>
    @else
        <div class="space-y-2.5">
            @foreach ($rows as $row)
                @php $t = $tone[$row['type']] ?? $tone['academic']; @endphp
                @php $urgent = $row['in_progress'] || $row['days_away'] <= 3; @endphp
                <div class="flex items-start gap-2.5">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg {{ $t['class'] }}">
                        <x-ui-icon :name="$t['icon']" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] font-medium leading-snug">{{ $row['label'] }}</p>

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
</div>
