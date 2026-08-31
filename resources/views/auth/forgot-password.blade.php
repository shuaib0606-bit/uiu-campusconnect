<!DOCTYPE html>
<html lang="en" class="{{ ($activeTheme ?? 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account recovery - UIU CampusConnect</title>
    <script>(function(){var m=document.cookie.match(/(?:^|; )cc_theme=([^;]+)/);if(m&&m[1]==='dark')document.documentElement.classList.add('dark');})();</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-[var(--surface-page)] px-4 py-10">
    <div class="w-full max-w-sm">
        <div class="mb-8 flex items-center justify-center gap-2.5">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-primary-500 text-white font-bold">U</span>
            <span class="text-[17px] font-semibold tracking-tight text-ink-900 dark:text-ink-100">CampusConnect</span>
        </div>

        <div class="surface p-7">
            <h1 class="text-xl font-semibold text-ink-900 dark:text-ink-100">Can't sign in?</h1>
            <p class="section-sub mt-1.5">
                Passwords are reset by the CampusConnect admin office, not automatically.
                Send a request below and an administrator will verify who you are and
                issue a temporary password.
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
                        placeholder="011233001 or you@bscse.uiu.ac.bd" class="field">
                    <span class="mt-1 block text-xs text-ink-500 dark:text-ink-400">Your student ID, employee ID, or UIU email address.</span>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium">Anything that helps us verify you <span class="font-normal text-ink-400">(optional)</span></span>
                    <textarea name="note" rows="3" maxlength="500" class="field resize-none"
                        placeholder="Department, batch, and how we can reach you.">{{ old('note') }}</textarea>
                </label>

                <button type="submit" class="btn-primary w-full">Send request</button>
            </form>

            <p class="mt-5 border-t border-ink-100 pt-4 text-center text-sm dark:border-ink-800/70">
                <a href="{{ route('login') }}" class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">Back to sign in</a>
            </p>
        </div>

        <p class="mt-5 text-center text-xs text-ink-500 dark:text-ink-400">
            An administrator will never ask for your password. A temporary one is only
            ever handed to you directly, and you will be asked to change it immediately.
        </p>
    </div>
</body>
</html>
