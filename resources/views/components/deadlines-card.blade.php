{{--
    Feed rail: what the viewer has to hand in, turn up to or pay soon.
    Students see their assignments with a small "Submitted" under the
    title once they have handed in; teachers see how many have come in.
--}}
@props(['items' => null])

@php
    use App\Support\AcademicCalendar;
    use App\Support\Deadlines;

    $viewer = auth()->user();
    $items ??= Deadlines::for($viewer, 30);
    $shown = $items->take(5);
    $week = $items->filter(fn ($i) => $i['state'] === 'overdue' || (Deadlines::daysLeft($i) >= 0 && Deadlines::daysLeft($i) <= 6))->count();
@endphp

<a href="{{ route('deadlines.index') }}" class="rail-card group block transition hover:border-primary-300 dark:hover:border-primary-700" aria-label="Open all deadlines">
    <div class="mb-3 flex items-center justify-between gap-2">
        <h3 class="inline-flex items-center gap-1.5"><x-ui-icon name="hourglass" class="h-4 w-4 text-primary-500" />Deadlines</h3>
        @if ($week)
            <span class="rounded-full bg-red-500/10 px-2 py-0.5 text-[11px] font-semibold text-red-600 dark:text-red-300">{{ $week }} this week</span>
        @endif
    </div>

    @if ($shown->isEmpty())
        <p class="text-sm text-ink-500 dark:text-ink-400">Nothing due in the next 30 days.</p>
    @else
        <div class="space-y-3">
            @foreach ($shown as $item)
                @include('deadlines._item', ['item' => $item, 'compact' => true])
            @endforeach
        </div>
    @endif

    <p class="mt-4 text-center text-xs font-medium text-primary-600 group-hover:underline dark:text-primary-300">
        {{ $items->count() > $shown->count() ? 'See all '.$items->count().' deadlines' : 'See all deadlines' }}
    </p>
</a>
