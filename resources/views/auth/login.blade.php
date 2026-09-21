<!DOCTYPE html>
<html lang="en" class="{{ ($activeTheme ?? 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in - UIU CampusConnect</title>

    <script>(function(){var m=document.cookie.match(/(?:^|; )cc_theme=([^;]+)/);if(m&&m[1]==='dark')document.documentElement.classList.add('dark');})();</script>

    {{-- The same scene as the landing page, in its own chunk, loaded after
         paint. The form is usable before any of it arrives. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/landing.js'])

    <style>
        /* Stand-in for the scene: shown until the canvas has a frame, and
           left in place for good on a device with no WebGL or with reduced
           motion asked for. */
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
<body class="cc-stage relative isolate min-h-[100svh] overflow-x-hidden text-ink-900 dark:text-ink-50">

    <canvas id="cc-landing-canvas" class="fixed inset-0 -z-10 h-full w-full"></canvas>

    {{-- Legibility scrim. The scene is a background, not the subject. --}}
    <div class="pointer-events-none fixed inset-0 -z-10 bg-gradient-to-b from-black/35 via-black/25 to-black/60"></div>

    <div class="relative z-10 flex min-h-[100svh] flex-col">

        <header class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-6">
            <a href="{{ route('welcome') }}" class="flex items-center gap-2.5 text-white">
                <img src="{{ asset('img/logo.png') }}" alt="" width="48" height="48" class="h-12 w-12 shrink-0 rounded-xl object-cover shadow-lg shadow-black/20">
                <span class="text-base font-semibold tracking-tight drop-shadow">CampusConnect</span>
            </a>

            <button type="button" x-data
                @click="window.setTheme(window.currentTheme() === 'dark' ? 'light' : 'dark')"
                class="grid h-9 w-9 place-items-center rounded-xl bg-white/15 text-white backdrop-blur transition hover:bg-white/25"
                aria-label="Toggle theme">
                <x-ui-icon name="sun" class="h-5 w-5 dark:hidden" />
                <x-ui-icon name="moon" class="hidden h-5 w-5 dark:block" />
            </button>
        </header>

        <main class="mx-auto flex w-full max-w-6xl flex-1 items-center px-6 pb-12">
            <div class="grid w-full gap-10 lg:grid-cols-2 lg:gap-16">

                {{-- Hidden on small screens, where the form is the only thing
                     worth the space. --}}
                <section class="hidden flex-col justify-center text-white lg:flex">
                    {{-- The mark carries this panel. It is the first thing a
                         student sees on the way in, so it is sized to be read
                         from across a room, not squinted at. --}}
                    <img src="{{ asset('img/logo.png') }}" alt="UIU CampusConnect"
                        width="112" height="112"
                        class="mb-6 h-28 w-28 rounded-2xl object-cover shadow-2xl shadow-black/30">

                    <p class="text-[13px] font-semibold uppercase tracking-[0.18em] text-[#f2bda6] [text-shadow:0_1px_6px_rgba(0,0,0,0.55)]">
                        United International University
                    </p>

                    <h1 class="mt-4 text-[44px] font-semibold leading-[1.05] tracking-tight drop-shadow-lg">
                        The campus,<br>connected.
                    </h1>
                </section>

                {{-- Right: the form. --}}
                <section class="flex items-center justify-center">
                    <div class="w-full max-w-sm">
                        {{-- Small screens get the wordmark here instead of the
                             panel above. --}}
                        <div class="mb-6 flex flex-col items-center text-center text-white lg:hidden">
                            <img src="{{ asset('img/logo.png') }}" alt="UIU CampusConnect"
                                width="80" height="80"
                                class="mb-5 h-20 w-20 rounded-2xl object-cover shadow-2xl shadow-black/30">

                            <h1 class="text-2xl font-semibold tracking-tight drop-shadow-lg">The campus, connected.</h1>
                        </div>

                        <div class="rounded-2xl border border-white/20 bg-[var(--surface-page)]/85 p-7 shadow-2xl shadow-black/30 backdrop-blur-xl">
                            <h2 class="mb-6 text-xl font-semibold">Sign in</h2>

                            @if ($errors->any())
                                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                                    {{ $errors->first() }}
                                </div>
                            @endif
                            @if (session('error'))
                                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                                    {{ session('error') }}
                                </div>
                            @endif
                            @if (session('success'))
                                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300">
                                    {{ session('success') }}
                                </div>
                            @endif

                            <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
                                @csrf
                                <div>
                                    <label for="cc-email" class="mb-1.5 block text-sm font-medium">Email</label>
                                    <input id="cc-email" type="email" name="email" value="{{ old('email') }}"
                                        autocomplete="username" required autofocus class="field">
                                </div>
                                <div>
                                    <label for="cc-password" class="mb-1.5 block text-sm font-medium">Password</label>
                                    <input id="cc-password" type="password" name="password"
                                        autocomplete="current-password" required class="field">
                                </div>
                                <label class="flex items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
                                    <input type="checkbox" name="remember" class="rounded border-ink-300 text-primary-600 focus:ring-primary-400">
                                    Remember me
                                </label>
                                <button type="submit" class="btn-primary w-full py-2.5">Sign in</button>
                            </form>

                            <p class="mt-5 text-center text-sm">
                                <a href="{{ route('password.request') }}" class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">Forgot your password?</a>
                            </p>
                        </div>

                        <p class="mt-5 text-center text-xs text-white/75 drop-shadow">
                            Accounts are issued by the university. Contact the admin office if you cannot sign in.
                        </p>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
