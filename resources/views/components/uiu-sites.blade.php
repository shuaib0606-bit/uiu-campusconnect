{{--
    "UIU Official Sites": the university's own websites, each explained,
    opened from the sidebar. The React sidebar has the same dialog
    (Components/UiuSites.jsx); both read App\Support\UiuSites.

    Tones are written out in full so Tailwind finds every class.
--}}
@php
    $sites = \App\Support\UiuSites::all(auth()->user());
    $tones = [
        'sky' => ['icon' => 'bg-sky-500/12 text-sky-600 dark:text-sky-300', 'button' => 'bg-sky-600 hover:bg-sky-700', 'dot' => 'bg-sky-500'],
        'emerald' => ['icon' => 'bg-emerald-500/12 text-emerald-600 dark:text-emerald-300', 'button' => 'bg-emerald-600 hover:bg-emerald-700', 'dot' => 'bg-emerald-500'],
        'rose' => ['icon' => 'bg-rose-500/12 text-rose-600 dark:text-rose-300', 'button' => 'bg-rose-600 hover:bg-rose-700', 'dot' => 'bg-rose-500'],
    ];
@endphp

<div x-data="{ open: false }" @keydown.escape.window="open = false">
    <button type="button" @click="open = true" class="nav-link w-full" aria-haspopup="dialog">
        <x-ui-icon name="globe" class="h-5 w-5" />
        <span class="flex-1 text-left">UIU Official Sites</span>
        <x-ui-icon name="chevron-right" class="h-4 w-4 text-ink-400" />
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" aria-labelledby="uiu-sites-title">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40 backdrop-blur-[2px]" @click="open = false"></div>

            <div x-show="open" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="opacity-0 scale-95 translate-y-2" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="relative max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-3xl border p-6 shadow-2xl sm:p-8"
                 style="background: var(--surface-page); border-color: var(--hairline)">

                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <h2 id="uiu-sites-title" class="text-xl font-bold tracking-tight">UIU official sites</h2>
                        <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">The university's own systems. Each opens in a new tab and uses your UIU sign-in, not your CampusConnect one.</p>
                    </div>
                    <button type="button" class="btn-icon shrink-0" @click="open = false" aria-label="Close"><x-ui-icon name="x" class="h-5 w-5" /></button>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    @foreach ($sites as $site)
                        @php $t = $tones[$site['tone']]; @endphp
                        <a href="{{ $site['url'] }}" target="_blank" rel="noopener noreferrer"
                           class="group flex flex-col rounded-2xl border p-5 transition hover:-translate-y-0.5 hover:shadow-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                           style="background: var(--surface-card); border-color: var(--hairline)">
                            <span class="grid h-12 w-12 place-items-center rounded-2xl {{ $t['icon'] }}">
                                <x-ui-icon :name="$site['icon']" class="h-6 w-6" />
                            </span>

                            <span class="mt-4 text-[17px] font-bold">{{ $site['name'] }}</span>
                            <span class="text-xs font-medium text-ink-400">{{ $site['host'] }}</span>
                            <span class="mt-2 text-sm font-semibold text-ink-700 dark:text-ink-200">{{ $site['tagline'] }}</span>

                            <ul class="mt-3 space-y-1.5 text-[13px] text-ink-600 dark:text-ink-300">
                                @foreach ($site['uses'] as $use)
                                    <li class="flex items-start gap-2"><span class="mt-[7px] h-1.5 w-1.5 shrink-0 rounded-full {{ $t['dot'] }}"></span>{{ $use }}</li>
                                @endforeach
                            </ul>

                            @if ($site['hint'])
                                <span class="mt-4 flex items-start gap-1.5 rounded-xl bg-ink-900/[0.04] px-3 py-2 text-xs text-ink-600 dark:bg-white/[0.05] dark:text-ink-300">
                                    <x-ui-icon name="calendar" class="mt-px h-3.5 w-3.5 shrink-0" />
                                    <span>{{ $site['hint'] }}</span>
                                </span>
                            @endif

                            <span class="mt-auto pt-5">
                                <span class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition {{ $t['button'] }}">
                                    Open {{ $site['name'] }} <x-ui-icon name="external" class="h-4 w-4" />
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>

                <p class="mt-6 flex items-start gap-2 text-xs text-ink-400">
                    <x-ui-icon name="lock" class="mt-px h-3.5 w-3.5 shrink-0" />
                    CampusConnect is not connected to these sites and never asks for your UIU password. Only type it on the sites themselves.
                </p>
            </div>
        </div>
    </template>
</div>
