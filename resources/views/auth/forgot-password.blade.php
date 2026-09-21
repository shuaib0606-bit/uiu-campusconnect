<!DOCTYPE html>
<html lang="en" class="{{ ($activeTheme ?? 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Account recovery - UIU CampusConnect</title>

    <script>(function(){var m=document.cookie.match(/(?:^|; )cc_theme=([^;]+)/);if(m&&m[1]==='dark')document.documentElement.classList.add('dark');})();</script>

    {{-- Same shell as the sign-in page, so the two do not look like different
         sites when somebody follows the link between them. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/landing.js'])

    <style>
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

                <section class="hidden flex-col justify-center text-white lg:flex">
                    <p class="text-[13px] font-semibold uppercase tracking-[0.18em] text-[#f2bda6] [text-shadow:0_1px_6px_rgba(0,0,0,0.55)]">
                        Account recovery
                    </p>

                    <h1 class="mt-4 text-[44px] font-semibold leading-[1.05] tracking-tight drop-shadow-lg">
                        Locked out?<br>We can help.
                    </h1>
                </section>

                <section class="flex items-center justify-center">
                    <div class="w-full max-w-sm">
                        <div class="mb-6 text-center text-white lg:hidden">
                            <h1 class="text-2xl font-semibold tracking-tight drop-shadow-lg">Locked out?</h1>
                        </div>

                        <div class="rounded-2xl border border-white/20 bg-[var(--surface-page)]/85 p-7 shadow-2xl shadow-black/30 backdrop-blur-xl">
                            <h2 class="text-xl font-semibold">Request a reset</h2>
                            <p class="section-sub mt-1.5">
                                An administrator will check who you are and hand you a temporary password.
                            </p>

                            @if (session('success'))
                                <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300">
                                    {{ session('success') }}
                                </div>
                            @endif

                            @if ($errors->any())
                                <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                                    {{ $errors->first() }}
                                </div>
                            @endif

                            <form method="POST" action="{{ route('password.request.store') }}" class="mt-5 space-y-4">
                                @csrf

                                <label class="block">
                                    <span class="mb-1.5 block text-sm font-medium">University email or ID</span>
                                    <input name="identifier" required autofocus value="{{ old('identifier') }}"
                                        autocomplete="username"
                                        placeholder="011233001 or you@bscse.uiu.ac.bd" class="field">
                                    <span class="mt-1 block text-xs text-ink-500 dark:text-ink-400">Student ID, employee ID, or UIU email.</span>
                                </label>

                                <label class="block">
                                    <span class="mb-1.5 block text-sm font-medium">
                                        Anything that helps us verify you
                                        <span class="font-normal text-ink-400">(optional)</span>
                                    </span>
                                    <textarea name="note" rows="3" maxlength="500" class="field resize-none"
                                        placeholder="Department, batch, and how we can reach you.">{{ old('note') }}</textarea>
                                </label>

                                <button type="submit" class="btn-primary w-full py-2.5">Send request</button>
                            </form>

                            <p class="mt-5 text-center text-sm">
                                <a href="{{ route('login') }}" class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">Back to sign in</a>
                            </p>
                        </div>

                        <p class="mt-5 text-center text-xs text-white/75 drop-shadow">
                            An administrator will never ask for your password.
                        </p>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
