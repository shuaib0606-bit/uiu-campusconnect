<x-app-layout title="Messages - UIU CampusConnect">
    <div class="mx-auto max-w-lg">
        <div class="surface flex flex-col items-center gap-3 px-8 py-16 text-center">
            <span class="empty-icon"><x-ui-icon name="message" class="h-6 w-6" /></span>
            <h1 class="text-[17px] font-semibold">Private messaging is disabled for developer admin</h1>
            <p class="max-w-sm text-sm text-ink-500 dark:text-ink-400">This protects user privacy. Admin can moderate public posts only.</p>
            <a href="{{ url('/admin') }}" class="btn-primary mt-2">Open Admin Dashboard</a>
        </div>
    </div>
</x-app-layout>
