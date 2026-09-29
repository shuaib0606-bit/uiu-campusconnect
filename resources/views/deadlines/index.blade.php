<x-app-layout title="Deadlines - UIU CampusConnect">
    <div class="mx-auto max-w-3xl">
        <div class="surface mb-5 flex flex-wrap items-center justify-between gap-3 p-6">
            <div>
                <h1 class="text-[19px] font-semibold">Deadlines</h1>
                <p class="section-sub">
                    {{ $teacher
                        ? 'Work you have set and how much has come in, grade submission dates, exams, and events you registered for.'
                        : 'Assignments from your classes, exams, fee instalments and admin cut-offs from your academic calendar, and events you registered for.' }}
                </p>
            </div>
            <a href="{{ route('calendar.index') }}" class="btn-secondary"><x-ui-icon name="calendar-days" class="h-4 w-4" />Academic calendar</a>
        </div>

        @forelse ($groups as $label => $items)
            <section class="surface mb-4 p-3">
                <h2 class="px-3 pb-1 pt-2 text-xs font-bold uppercase tracking-wide {{ $label === 'Overdue' ? 'text-red-600 dark:text-red-400' : 'text-ink-400' }}">{{ $label }} <span class="font-medium">&middot; {{ $items->count() }}</span></h2>
                <div class="space-y-1">
                    @foreach ($items as $item)
                        @include('deadlines._item', ['item' => $item])
                    @endforeach
                </div>
            </section>
        @empty
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="check-circle" class="h-6 w-6" /></span>
                <h3>Nothing due</h3>
                <p>Nothing in the next 60 days. Assignments your teachers set will show up here with their deadlines.</p>
            </div>
        @endforelse
    </div>
</x-app-layout>
