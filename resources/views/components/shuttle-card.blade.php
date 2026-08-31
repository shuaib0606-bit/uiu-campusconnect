@php
    use App\Support\ShuttleSchedule;

    // The whole timetable goes to the client so the pickers can filter locally.
    $routes = ShuttleSchedule::allRoutes();

    $payload = collect($routes)->map(fn ($r) => [
        'key' => $r['key'],
        'name' => $r['name'],
        'provisional' => $r['provisional'],
        'stops' => $r['stops'],
        'departures' => collect($r['departures'])->map(fn ($d) => [
            'ts' => $d['timestamp'],
            'dir' => $d['direction'],
            'label' => $d['label'],
            'from' => $d['from'],
            'to' => $d['to'],
            'time' => $d['depart']->format('g:i A'),
            'today' => $d['is_today'],
            'window' => $d['window_until'],
        ])->values(),
    ])->values();

    $hasAny = collect($routes)->contains(fn ($r) => ! empty($r['departures']));
@endphp

<div class="rail-card" @if ($hasAny) x-data="ccShuttle(@js($payload))" @endif>
    <div class="mb-3 flex items-center justify-between gap-2">
        <h3 class="inline-flex items-center gap-1.5"><x-ui-icon name="bus" class="h-4 w-4 text-primary-500" />Next shuttle</h3>
        <span class="text-[11px] text-ink-400">Timetable</span>
    </div>

    @if (! $hasAny)
        <p class="text-sm text-ink-500 dark:text-ink-400">No further departures scheduled.</p>
    @else
        <label class="sr-only" for="cc-shuttle-route">Shuttle route</label>
        <select id="cc-shuttle-route" x-model="routeKey" class="field mb-2 !py-1.5 text-[13px]">
            <template x-for="r in all" :key="r.key">
                <option :value="r.key" x-text="r.name"></option>
            </template>
        </select>

        <div class="mb-3 flex gap-1">
            <button type="button" class="filter-pill !px-2.5 !py-1 !text-xs" :class="filter === 'all' && 'is-active'" @click="filter = 'all'">All</button>
            <button type="button" class="filter-pill !px-2.5 !py-1 !text-xs" :class="filter === 'inbound' && 'is-active'" @click="filter = 'inbound'">To campus</button>
            <button type="button" class="filter-pill !px-2.5 !py-1 !text-xs" :class="filter === 'outbound' && 'is-active'" @click="filter = 'outbound'">From campus</button>
        </div>

        <div x-show="route && route.provisional" x-cloak
            class="mb-3 flex items-start gap-1.5 rounded-lg bg-amber-500/10 px-2.5 py-2 text-[11px] leading-snug text-amber-700 dark:text-amber-300">
            <x-ui-icon name="alert-triangle" class="mt-px h-3.5 w-3.5 shrink-0" />
            <span><span class="font-semibold">Unofficial times.</span> UIU has not published a schedule for this route yet - treat these as indicative only.</span>
        </div>

        <template x-if="current">
            <div>
                <div class="rounded-xl bg-primary-500/[0.07] p-3.5 ring-1 ring-primary-500/15">
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-2xl font-bold tracking-tight text-primary-600 dark:text-primary-300" x-text="display"></span>
                        <span class="text-xs font-medium text-ink-500 dark:text-ink-400">until departure</span>
                    </div>
                    <p class="mt-1.5 flex items-center gap-1.5 text-[13px] font-medium">
                        <span x-text="current.from"></span>
                        <x-ui-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-ink-400" />
                        <span x-text="current.to"></span>
                    </p>
                    <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">
                        <span x-text="current.time"></span>
                        <span x-show="current.window" x-cloak x-text="'- ' + current.window + ' window'"></span>
                        <span x-show="!current.today" x-cloak> · tomorrow</span>
                    </p>
                </div>

                <template x-if="rest.length">
                    <div class="mt-3 space-y-1.5 border-t border-ink-100 pt-3 dark:border-ink-800/70">
                        <template x-for="d in rest" :key="d.ts">
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <span class="truncate text-ink-600 dark:text-ink-300" x-text="d.label"></span>
                                <span class="shrink-0 font-medium tabular-nums" x-text="d.time"></span>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="!current">
            <p class="text-sm text-ink-500 dark:text-ink-400">No more departures in this direction today.</p>
        </template>

        <p class="mt-3 border-t border-ink-100 pt-2.5 text-[11px] leading-relaxed text-ink-400 dark:border-ink-800/70">
            <span x-text="route ? route.stops.join(' · ') : ''"></span><br>
            Published schedule, not live tracking.
        </p>
    @endif
</div>
