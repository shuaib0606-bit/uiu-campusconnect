<!DOCTYPE html>
<html lang="en" class="{{ ($activeTheme ?? 'light') === 'dark' ? 'dark' : '' }}" x-data="{ role: 'student' }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create account — UIU CampusConnect</title>
    <script>(function(){var m=document.cookie.match(/(?:^|; )cc_theme=([^;]+)/);if(m&&m[1]==='dark')document.documentElement.classList.add('dark');})();</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-[#fbfaf8] px-4 py-10 dark:bg-[#121110]">
    <div class="w-full max-w-lg">
        <div class="mb-8 flex items-center justify-center gap-2.5">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-primary-500 text-white font-bold">U</span>
            <span class="text-[17px] font-semibold tracking-tight text-ink-900 dark:text-ink-100">CampusConnect</span>
        </div>

        <div class="surface p-7">
            <h1 class="mb-6 text-xl font-semibold text-ink-900 dark:text-ink-100">Create account</h1>

            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                    <ul class="list-disc pl-4">
                        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf

                <div class="grid grid-cols-3 gap-2">
                    @foreach ([['student','Student'],['faculty','Faculty'],['official','Official']] as [$val, $label])
                        <label class="relative">
                            <input type="radio" name="role" value="{{ $val }}" x-model="role" class="peer sr-only" {{ $val === 'student' ? 'checked' : '' }}>
                            <span class="flex cursor-pointer items-center justify-center rounded-xl border border-ink-200 dark:border-ink-800 py-2.5 text-sm font-medium peer-checked:border-primary-500 peer-checked:bg-primary-500/10 peer-checked:text-primary-700 dark:peer-checked:text-primary-300 transition">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Full name</label>
                        <input name="full_name" value="{{ old('full_name') }}" required class="field">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">University email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required class="field" placeholder="you@uiu.ac.bd">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium" x-text="role === 'student' ? 'Department' : 'Department / Office'">Department</label>
                        <input name="department" value="{{ old('department') }}" class="field">
                    </div>
                    <template x-if="role === 'student'">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">Student ID</label>
                            <input name="student_id" value="{{ old('student_id') }}" class="field">
                        </div>
                    </template>
                    <template x-if="role !== 'student'">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">Employee / Office ID</label>
                            <input name="employee_id" value="{{ old('employee_id') }}" class="field">
                        </div>
                    </template>
                </div>

                <template x-if="role !== 'student'">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Designation</label>
                        <input name="designation" value="{{ old('designation') }}" class="field">
                    </div>
                </template>

                <template x-if="role === 'student'">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Year</label>
                        <select name="year" class="field">
                            <option value="">Not selected</option>
                            <option>1st Year</option>
                            <option>2nd Year</option>
                            <option>3rd Year</option>
                            <option>4th Year</option>
                        </select>
                    </div>
                </template>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Password</label>
                        <input type="password" name="password" required class="field">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Confirm password</label>
                        <input type="password" name="password_confirmation" required class="field">
                    </div>
                </div>

                <button type="submit" class="btn-primary w-full py-2.5">Create account</button>
            </form>
        </div>

        <p class="mt-6 text-center text-sm text-ink-500 dark:text-ink-400">
            <a href="{{ route('login') }}" class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">Sign in instead</a>
        </p>
    </div>
</body>
</html>
