<x-app-layout title="Password — UIU CampusConnect">
    <div class="mx-auto max-w-md surface p-6">
        <h1 class="mb-6 text-lg font-semibold">Change password</h1>
        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">{{ $errors->first() }}</div>
        @endif
        <form action="{{ route('settings.password.update') }}" method="POST" class="space-y-4">
            @csrf @method('PUT')
            <div><label class="mb-1.5 block text-sm font-medium">Current password</label><input type="password" name="current_password" required class="field"></div>
            <div><label class="mb-1.5 block text-sm font-medium">New password</label><input type="password" name="password" required class="field"></div>
            <div><label class="mb-1.5 block text-sm font-medium">Confirm new password</label><input type="password" name="password_confirmation" required class="field"></div>
            <button type="submit" class="btn-primary w-full py-2.5">Update password</button>
        </form>
    </div>
</x-app-layout>
