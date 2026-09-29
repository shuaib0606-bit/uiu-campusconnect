{{--
    One deadline. Used by the feed card (compact: no links inside, since the
    whole card is a link) and by the deadlines page.
--}}
@php
    $isCal = $item['kind'] === 'calendar';
    $look = match ($item['kind']) {
        'assignment' => ['icon' => 'clipboard', 'chip' => 'bg-primary-500/10 text-primary-600 dark:text-primary-300'],
        'event' => ['icon' => 'calendar-star', 'chip' => 'bg-pink-500/10 text-pink-600 dark:text-pink-300'],
        default => \App\Support\AcademicCalendar::type($item['type']),
    };
    $days = \App\Support\Deadlines::daysLeft($item);
    $soon = $item['state'] === 'overdue' || ($item['state'] === null && $days >= 0 && $days <= 2) || $item['state'] === 'now';
    $done = in_array($item['state'], ['submitted', 'late'], true);
    $compact = $compact ?? false;
    $tag = ! $compact && $item['url'] ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($tag === 'a') href="{{ $item['url'] }}" @endif class="flex items-start gap-2.5 {{ $compact ? '' : 'rounded-xl p-3 transition hover:bg-ink-900/[0.03] dark:hover:bg-white/[0.04]' }}">
    <span class="grid {{ $compact ? 'h-8 w-8' : 'h-10 w-10' }} shrink-0 place-items-center rounded-lg {{ $look['chip'] }}">
        <x-ui-icon :name="$look['icon']" class="h-4 w-4" />
    </span>

    <div class="min-w-0 flex-1">
        <p class="{{ $compact ? 'text-[13px]' : 'text-[15px]' }} font-medium leading-snug text-ink-800 dark:text-ink-100">{{ $item['title'] }}</p>

        {{-- The hand-in state sits right under the title. --}}
        @if ($item['state'] === 'submitted')
            <span class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"><x-ui-icon name="check-circle" class="h-3 w-3" />Submitted</span>
        @elseif ($item['state'] === 'late')
            <span class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-semibold text-amber-600 dark:text-amber-400"><x-ui-icon name="check-circle" class="h-3 w-3" />Submitted late</span>
        @elseif ($item['state'] === 'overdue')
            <span class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-semibold text-red-600 dark:text-red-400"><x-ui-icon name="alert-triangle" class="h-3 w-3" />Not submitted - late work still accepted</span>
        @elseif ($item['handed_in'] !== null)
            <span class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-semibold {{ $item['class_size'] && $item['handed_in'] >= $item['class_size'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-ink-500 dark:text-ink-400' }}">
                <x-ui-icon name="clipboard" class="h-3 w-3" />{{ $item['handed_in'] }}{{ $item['class_size'] ? ' of '.$item['class_size'] : '' }} handed in{{ $item['state'] === 'closed' ? ' · closed' : '' }}
            </span>
        @endif

        <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-ink-500 dark:text-ink-400">
            <span @class([
                'whitespace-nowrap',
                'font-semibold text-red-600 dark:text-red-400' => $soon && ! $done,
            ])>{{ \App\Support\Deadlines::when($item) }}</span>
            @if ($item['context'])
                <span class="text-ink-300 dark:text-ink-600">&middot;</span>
                <span class="truncate">{{ $item['context'] }}</span>
            @endif
        </div>

        @if (! $compact && $item['note'])
            <p class="mt-1 text-xs text-ink-400">{{ $item['note'] }}</p>
        @endif
    </div>
</{{ $tag }}>
