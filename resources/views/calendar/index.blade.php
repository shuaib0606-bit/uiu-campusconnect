{{--
    The full academic calendar of one program's term.

    Colour says what kind of date it is (exam, fee, holiday...), and the
    row's treatment says where it sits in time: passed dates are faded with
    a tick, whatever is running today is outlined, and the next one is
    marked "Next". A line marks today. The filter chips and "Hide past" are
    Alpine only - the whole term is already on the page.
--}}
@php
    use App\Support\AcademicCalendar;

    $present = $months->flatten(1)->pluck('type')->unique()->values();
    $total = $months->flatten(1)->count();
    $passed = $months->flatten(1)->where('state', 'past')->count();
    $todayShown = false;
    $progress = null;

    if ($calendar && $calendar->starts_on && $calendar->ends_on) {
        $span = max(1, $calendar->starts_on->diffInDays($calendar->ends_on));
        $progress = (int) round(min(100, max(0, $calendar->starts_on->diffInDays($today, false) / $span * 100)));
    }
@endphp

<x-app-layout title="Academic calendar - UIU CampusConnect">
    <div class="mx-auto max-w-4xl" x-data="{ hidden: [], hidePast: false, toggle(t) { this.hidden = this.hidden.includes(t) ? this.hidden.filter((x) => x !== t) : [...this.hidden, t] } }">

        {{-- Header: which program, which term, and whether this is the one on your feed. --}}
        <div class="surface mb-4 p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-[19px] font-semibold">Academic calendar</h1>
                    <p class="section-sub">
                        {{ $calendar ? $calendar->label().' · '.$programs[$program] : $programs[$program] }}
                    </p>
                </div>

                @if ($terms->count() > 1)
                    <form method="GET" action="{{ route('calendar.index') }}" class="flex items-center gap-2">
                        <input type="hidden" name="program" value="{{ $program }}">
                        <label for="term" class="text-xs font-semibold text-ink-400">Term</label>
                        <select id="term" name="term" class="field w-auto !py-1.5 text-sm" onchange="this.form.submit()">
                            @foreach ($terms as $t)
                                <option value="{{ $t->slug }}" @selected($calendar?->is($t))>{{ $t->label() }}{{ $t->isRunning() ? ' (now)' : '' }}</option>
                            @endforeach
                        </select>
                        <noscript><button class="btn-secondary !py-1.5 text-sm">Show</button></noscript>
                    </form>
                @endif
            </div>

            <nav class="surface-flat segmented mt-4" aria-label="Program">
                @foreach ($programs as $key => $label)
                    <a href="{{ route('calendar.index', ['program' => $key]) }}" class="segmented-item {{ $program === $key ? 'is-active' : '' }}" @if ($program === $key) aria-current="page" @endif>
                        {{ $label }}
                        @if ($mine === $key)<span class="ml-1 rounded-full bg-primary-500/10 px-1.5 py-0.5 text-[10px] font-bold text-primary-600 dark:text-primary-300">Yours</span>@endif
                    </a>
                @endforeach
            </nav>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-ink-500 dark:text-ink-400">
                <span>{{ $hints[$program] }}</span>
                @if ($program !== $mine)
                    <form method="POST" action="{{ route('calendar.program') }}">
                        @csrf
                        <input type="hidden" name="program" value="{{ $program }}">
                        <button class="font-bold text-primary-600 hover:underline dark:text-primary-300">Show this calendar on my feed</button>
                    </form>
                @elseif ($picked)
                    <form method="POST" action="{{ route('calendar.program') }}" class="flex items-center gap-1">
                        @csrf
                        <input type="hidden" name="program" value="auto">
                        <span>You chose this calendar for your feed.</span>
                        <button class="font-bold text-primary-600 hover:underline dark:text-primary-300">Use my department's instead</button>
                    </form>
                @else
                    <span>This is the calendar on your feed, based on your department.</span>
                @endif
            </div>
        </div>

        @if (! $calendar)
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="calendar-days" class="h-6 w-6" /></span>
                <h3>No calendar yet</h3>
                <p>UIU's {{ strtolower($programs[$program]) }} calendar has not been imported. An administrator can run <code>php artisan uiu:import-calendars</code>.</p>
            </div>
        @else
            @if ($awaitingNext)
                <div class="surface-flat mb-4 flex items-start gap-2 border-l-4 border-l-amber-500 px-4 py-3 text-sm">
                    <x-ui-icon name="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                    <span>This term is over and UIU has not published the next {{ strtolower($programs[$program]) }} calendar yet. It will appear here once it is out.</span>
                </div>
            @endif

            {{-- Where the term is up to. --}}
            <div class="surface mb-4 p-5">
                <div class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                    <span class="font-semibold">{{ $calendar->starts_on->format('M j, Y') }} &ndash; {{ $calendar->ends_on->format('M j, Y') }}</span>
                    <span class="text-ink-500 dark:text-ink-400">{{ $passed }} of {{ $total }} dates passed</span>
                </div>
                <div class="relative mt-3 h-2 rounded-full bg-ink-900/[0.06] dark:bg-white/[0.08]">
                    <div class="h-2 rounded-full bg-primary-500" style="width: {{ $progress }}%"></div>
                    @if ($progress > 0 && $progress < 100)
                        <span class="absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-primary-600 shadow dark:border-ink-900" style="left: {{ $progress }}%" title="Today"></span>
                    @endif
                </div>

                {{-- Legend doubles as a filter. --}}
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @foreach ($types as $key => $t)
                        @continue(! $present->contains($key))
                        <button type="button" @click="toggle('{{ $key }}')" class="chip hover:!bg-ink-900/[0.08]" :class="hidden.includes('{{ $key }}') && 'opacity-40 line-through'" :aria-pressed="!hidden.includes('{{ $key }}')">
                            <span class="h-2 w-2 rounded-full {{ $t['dot'] }}"></span>{{ $t['label'] }}
                        </button>
                    @endforeach
                    <span class="flex-1"></span>
                    <label class="inline-flex cursor-pointer items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
                        <input type="checkbox" x-model="hidePast" class="rounded border-ink-300 text-primary-600 focus:ring-primary-500">Hide past dates
                    </label>
                </div>
            </div>

            {{-- The dates, month by month. --}}
            @foreach ($months as $ym => $rows)
                @php $month = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $ym.'-01'); @endphp

                {{-- Today falls in a month with nothing on it: mark it between the months. --}}
                @if (! $todayShown && ! $month->isSameMonth($today) && $rows->contains(fn ($r) => $r['state'] !== 'past'))
                    @php $todayShown = true; @endphp
                    @include('calendar._today', ['between' => true])
                @endif
                <section class="surface mb-4 p-3" x-show="!hidePast || {{ $rows->contains(fn ($r) => $r['state'] !== 'past') ? 'true' : 'false' }}">
                    <h2 class="px-3 pb-2 pt-2 text-sm font-bold {{ $month->isSameMonth($today) ? 'text-primary-600 dark:text-primary-300' : 'text-ink-500 dark:text-ink-400' }}">
                        {{ $month->format('F Y') }}
                    </h2>

                    <div class="space-y-1">
                        @foreach ($rows as $row)
                            @php
                                $t = AcademicCalendar::type($row['type']);
                                $isNext = $row['id'] === $nextId;
                            @endphp

                            @if (! $todayShown && $row['state'] !== 'past')
                                @php $todayShown = true; @endphp
                                @include('calendar._today')
                            @endif

                            <div x-show="!hidden.includes('{{ $row['type'] }}') && (!hidePast || '{{ $row['state'] }}' !== 'past')"
                                 @class([
                                     'flex items-stretch gap-3 rounded-xl p-2.5 transition',
                                     'opacity-55' => $row['state'] === 'past',
                                     'bg-primary-500/[0.06] ring-1 ring-primary-500/40' => $row['state'] === 'now',
                                     'bg-ink-900/[0.03] dark:bg-white/[0.04]' => $isNext,
                                 ])>
                                {{-- Date block --}}
                                <div class="w-16 shrink-0 text-center">
                                    <p class="text-[17px] font-black leading-tight">{{ $row['start']->format('j') }}@if ($row['is_range'])<span class="text-ink-400">&ndash;</span>{{ $row['end']->format($row['end']->isSameMonth($row['start']) ? 'j' : 'M j') }}@endif</p>
                                    <p class="text-[11px] font-medium uppercase text-ink-400">{{ $row['start']->format('D') }}@if ($row['is_range'])&ndash;{{ $row['end']->format('D') }}@endif</p>
                                </div>

                                <span class="w-1 shrink-0 rounded-full {{ $t['dot'] }}"></span>

                                <div class="min-w-0 flex-1 py-0.5">
                                    <p class="text-[14px] leading-snug {{ $row['important'] ? 'font-semibold' : 'font-medium' }} {{ $row['state'] === 'past' ? 'text-ink-500 dark:text-ink-400' : '' }}">{{ $row['label'] }}</p>
                                    @if ($row['note'])
                                        <p class="mt-0.5 text-xs text-ink-400">{{ $row['note'] }}</p>
                                    @endif
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $t['chip'] }}">{{ $t['one'] }}</span>
                                        @if ($row['state'] === 'past')
                                            <span class="inline-flex items-center gap-1 text-[11px] font-medium text-ink-400"><x-ui-icon name="check" class="h-3 w-3" />Done</span>
                                        @elseif ($row['state'] === 'now')
                                            <span class="rounded-full bg-primary-500 px-2 py-0.5 text-[11px] font-bold text-white">{{ $row['is_range'] ? 'Happening now' : 'Today' }}</span>
                                        @else
                                            @if ($isNext)<span class="rounded-full bg-ink-900 px-2 py-0.5 text-[11px] font-bold text-white dark:bg-white dark:text-ink-900">Next</span>@endif
                                            <span class="text-[11px] font-medium text-ink-500 dark:text-ink-400">{{ AcademicCalendar::relative($row) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            @if (! $todayShown && $calendar->ends_on->lessThan($today))
                <p class="mb-4 text-center text-xs font-semibold text-ink-400">Every date on this calendar has passed.</p>
            @endif

            @if ($calendar->noteParagraphs())
                <details class="surface mb-4 p-5">
                    <summary class="cursor-pointer text-sm font-semibold">Notes from UIU</summary>
                    <div class="mt-3 space-y-2 text-sm leading-relaxed text-ink-600 dark:text-ink-300">
                        @foreach ($calendar->noteParagraphs() as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>
                </details>
            @endif

            @if ($calendar->imported_at)
                <p class="mb-6 text-center text-xs text-ink-400">Last updated {{ $calendar->imported_at->format('M j, Y') }}</p>
            @endif
        @endif
    </div>
</x-app-layout>
