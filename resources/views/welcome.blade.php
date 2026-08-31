<!DOCTYPE html>
<html lang="en" class="{{ ($activeTheme ?? 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>UIU CampusConnect</title>

    <script>
        (function () {
            var m = document.cookie.match(/(?:^|; )cc_theme=([^;]+)/);
            if (m && m[1] === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/landing.js'])

    <style>
        /* Gradient stand-in for the scene, shown until the canvas renders. */
        .cc-stage {
            background: linear-gradient(180deg, #7fb0e4 0%, #bcd8ef 46%, #7a9a52 46%, #4f7436 100%);
        }

        .dark .cc-stage {
            background: linear-gradient(180deg, #05070f 0%, #16203a 46%, #16241a 46%, #0c150c 100%);
        }

        #cc-landing-canvas {
            opacity: 0;
            transition: opacity 900ms ease-out;
        }

        .cc-stage[data-fallback="true"] #cc-landing-canvas {
            display: none;
        }

        @media (prefers-reduced-motion: reduce) {
            #cc-landing-canvas { transition: none; }
        }
    </style>
</head>
<body class="bg-[var(--surface-page)] text-ink-900 dark:text-ink-50">

    {{-- Hero --}}
    <section class="cc-stage relative isolate flex min-h-[100svh] flex-col overflow-hidden">

        <canvas id="cc-landing-canvas" class="absolute inset-0 -z-10 h-full w-full"></canvas>

        {{-- Legibility scrim --}}
        <div class="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-b from-black/25 via-transparent to-black/55"></div>

        <header class="relative z-10 mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-2.5 text-white">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-primary-500 font-bold text-white shadow-lg shadow-black/20">U</span>
                <span class="text-[15px] font-semibold tracking-tight drop-shadow">CampusConnect</span>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" x-data
                    @click="window.setTheme(window.currentTheme() === 'dark' ? 'light' : 'dark')"
                    class="grid h-10 w-10 place-items-center rounded-xl border border-white/20 bg-white/10 text-white backdrop-blur transition hover:bg-white/20"
                    aria-label="Toggle day and night">
                    <x-ui-icon name="sun" class="h-5 w-5 dark:hidden" />
                    <x-ui-icon name="moon" class="hidden h-5 w-5 dark:block" />
                </button>

                <a href="{{ route('login') }}"
                    class="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/20">
                    Sign in
                </a>
            </div>
        </header>

        <div class="relative z-10 mx-auto flex w-full max-w-6xl flex-1 flex-col justify-end px-6 pb-20 sm:pb-28">
            <p class="text-[13px] font-semibold uppercase tracking-[0.18em] text-[#f2bda6] [text-shadow:0_1px_6px_rgba(0,0,0,0.55)]">
                United International University
            </p>

            <h1 class="mt-4 max-w-3xl text-5xl font-semibold leading-[1.05] tracking-tight text-white drop-shadow-lg sm:text-7xl">
                The campus,<br>connected.
            </h1>

            <p class="mt-6 max-w-xl text-base leading-relaxed text-white/80 drop-shadow sm:text-lg">
                Notices, clubs, events, the marketplace and everyone you study with - in one place.
            </p>

            <div class="mt-9 flex flex-wrap items-center gap-3">
                <a href="{{ route('login') }}"
                    class="rounded-xl bg-primary-500 px-7 py-3.5 text-[15px] font-semibold text-white shadow-lg shadow-primary-900/30 transition hover:bg-primary-600">
                    Sign in
                </a>
            </div>
        </div>

    </section>

</body>
</html>
