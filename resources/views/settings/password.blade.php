<x-app-layout title="Account settings - UIU CampusConnect">
    <div class="mx-auto max-w-3xl">
        <div class="surface mb-5 flex flex-wrap items-center justify-between gap-4 p-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-300">Account security</p>
                <h1 class="mt-1 text-[19px] font-semibold">Account settings</h1>
                <p class="section-sub mt-1">Manage your password and basic security settings for UIU CampusConnect.</p>
            </div>
            <a href="{{ route('profile.show', auth()->user()) }}" class="btn-secondary"><x-ui-icon name="user" class="h-4 w-4" />View profile</a>
        </div>

        @if ($forced)
            <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300">You must set a new password before continuing.</div>
        @endif

        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <section class="surface p-6">
                <h2 class="mb-1 text-[15px] font-semibold">Change password</h2>
                <p class="section-sub mb-4">Use a unique password that you do not use on other websites.</p>
                <form action="{{ route('settings.password.update') }}" method="POST" class="space-y-3">
                    @csrf @method('PUT')
                    <label class="block"><span class="mb-1 block text-xs font-medium">Current password</span><input type="password" name="current_password" autocomplete="current-password" required class="field"></label>
                    <label class="block"><span class="mb-1 block text-xs font-medium">New password</span><input type="password" name="password" autocomplete="new-password" minlength="6" required placeholder="Minimum 6 characters" class="field"></label>
                    <label class="block"><span class="mb-1 block text-xs font-medium">Confirm new password</span><input type="password" name="password_confirmation" autocomplete="new-password" minlength="6" required class="field"></label>
                    <button type="submit" class="btn-primary w-full"><x-ui-icon name="shield" class="h-4 w-4" />Update password</button>
                </form>
            </section>

            <aside class="surface p-6">
                <h2 class="mb-3 text-[15px] font-semibold">Security status</h2>
                <ul class="space-y-3 text-sm">
                    <li class="flex items-center justify-between gap-3 border-b border-ink-100 pb-2.5 dark:border-ink-800/70"><span class="font-medium">Email</span><span class="truncate text-ink-500 dark:text-ink-400">{{ auth()->user()->email }}</span></li>
                    <li class="flex items-center justify-between gap-3 border-b border-ink-100 pb-2.5 dark:border-ink-800/70"><span class="font-medium">Role</span><span class="text-ink-500 dark:text-ink-400">{{ auth()->user()->roleLabel() }}</span></li>
                    <li class="flex items-center justify-between gap-3 border-b border-ink-100 pb-2.5 dark:border-ink-800/70"><span class="font-medium">Last login</span><span class="text-ink-500 dark:text-ink-400">{{ optional(auth()->user()->last_login_at)->format('M j, Y g:i A') ?? 'Not recorded yet' }}</span></li>
                    <li class="flex items-center justify-between gap-3"><span class="font-medium">Password changed</span><span class="text-ink-500 dark:text-ink-400">{{ optional(auth()->user()->password_changed_at)->format('M j, Y g:i A') ?? 'Not recorded yet' }}</span></li>
                </ul>
                <p class="section-sub mt-4">Forgot-password and email-verification support is prepared, but email delivery should be configured before enabling public reset links.</p>
            </aside>
        </div>
    </div>
</x-app-layout>
