<!DOCTYPE html>
<html lang="en" class="{{ ($activeTheme ?? 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — UIU CampusConnect</title>
    <script>(function(){var m=document.cookie.match(/(?:^|; )cc_theme=([^;]+)/);if(m&&m[1]==='dark')document.documentElement.classList.add('dark');})();</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-[#fbfaf8] px-4 dark:bg-[#121110]">
    <div class="w-full max-w-sm">
        <div class="mb-8 flex items-center justify-center gap-2.5">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-primary-500 text-white font-bold">U</span>
            <span class="text-[17px] font-semibold tracking-tight text-ink-900 dark:text-ink-100">CampusConnect</span>
        </div>

        <div class="surface p-7">
            <h1 class="mb-6 text-xl font-semibold text-ink-900 dark:text-ink-100">Sign in</h1>

            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                    {{ $errors->first() }}
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
                    <label class="mb-1.5 block text-sm font-medium">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus class="field">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Password</label>
                    <input type="password" name="password" required class="field">
                </div>
                <label class="flex items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
                    <input type="checkbox" name="remember" class="rounded border-ink-300 text-primary-600 focus:ring-primary-400">
                    Remember me
                </label>
                <button type="submit" class="btn-primary w-full py-2.5">Sign in</button>
            </form>
        </div>

        <p class="mt-6 text-center text-sm text-ink-500 dark:text-ink-400">
            <a href="{{ route('register') }}" class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">Create an account</a>
        </p>
    </div>
</body>
</html>
