<!DOCTYPE html>
<html lang="en" class="{{ ($activeTheme ?? 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>UIU CampusConnect</title>
    <script>
        (function () {
            var m = document.cookie.match(/(?:^|; )cc_theme=([^;]+)/);
            if (m && m[1] === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#fbfaf8] text-ink-900 dark:bg-[#121110] dark:text-ink-100">
    <div class="relative isolate overflow-hidden">
        <div class="pointer-events-none absolute -top-40 left-1/2 h-[560px] w-[900px] -translate-x-1/2 rounded-full bg-primary-400/20 blur-3xl"></div>

        <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-primary-500 text-white font-bold">U</span>
                <span class="text-[15px] font-semibold tracking-tight">CampusConnect</span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" x-data @click="window.setTheme(window.currentTheme() === 'dark' ? 'light' : 'dark')" class="btn-icon">
                    <x-ui-icon name="sun" class="h-5 w-5 dark:hidden" />
                    <x-ui-icon name="moon" class="h-5 w-5 hidden dark:block" />
                </button>
                <a href="{{ route('login') }}" class="btn-ghost">Sign in</a>
                <a href="{{ route('register') }}" class="btn-primary">Create account</a>
            </div>
        </header>

        <main class="mx-auto flex max-w-4xl flex-col items-center px-6 pb-24 pt-16 text-center sm:pt-24">
            <h1 class="text-4xl font-semibold tracking-tight sm:text-6xl">The campus, connected.</h1>
            <div class="mt-10 flex items-center gap-3">
                <a href="{{ route('register') }}" class="btn-primary px-7 py-3 text-[15px]">Get started</a>
                <a href="{{ route('login') }}" class="btn-secondary px-7 py-3 text-[15px]">Sign in</a>
            </div>

            <div class="mt-20 grid w-full grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ([['home','Feed'], ['users','Network'], ['award','Clubs'], ['calendar','Events']] as [$icon, $label])
                    <div class="surface flex flex-col items-center gap-3 px-4 py-7">
                        <span class="grid h-11 w-11 place-items-center rounded-2xl bg-primary-500/10 text-primary-600 dark:text-primary-300"><x-ui-icon :name="$icon" class="h-5 w-5" /></span>
                        <span class="text-sm font-medium">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </main>
    </div>
</body>
</html>
