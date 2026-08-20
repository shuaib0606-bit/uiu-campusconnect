<x-app-layout title="Events — UIU CampusConnect">
    <div class="mx-auto max-w-5xl" x-data="{ open: false }">
        <div class="section-heading mb-5">
            <div>
                <h1>Events</h1>
                <p class="section-sub">{{ $events->total() }} upcoming on campus</p>
            </div>
            <button type="button" class="btn-primary" @click="open = true"><x-ui-icon name="plus" class="h-4 w-4" />New event</button>
        </div>

        <div x-show="open" x-cloak class="surface mb-6 p-6">
            <form action="{{ route('events.store') }}" method="POST" enctype="multipart/form-data" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <input name="title" placeholder="Title" required class="field sm:col-span-2">
                <textarea name="description" placeholder="Description" required class="field sm:col-span-2" rows="3"></textarea>
                <input name="category" placeholder="Category" required class="field">
                <input name="location" placeholder="Location" required class="field">
                <input type="datetime-local" name="start_datetime" required class="field">
                <input type="datetime-local" name="end_datetime" required class="field">
                <input type="number" name="capacity" placeholder="Capacity" value="100" class="field">
                <input type="file" name="poster" accept="image/*" class="field">
                <button type="submit" class="btn-primary sm:col-span-2">Publish</button>
            </form>
        </div>

        @if ($events->isEmpty())
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="calendar" class="h-6 w-6" /></span>
                <h3>No events yet</h3>
                <p>Be the first to publish one, or check back once clubs and faculty start posting.</p>
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($events as $event)
                    <a href="{{ route('events.show', $event) }}" class="surface surface-hover overflow-hidden">
                        <div class="h-28 bg-gradient-to-br from-primary-400 to-primary-600" @if($event->poster_path) style="background-image:url({{ Storage::url($event->poster_path) }});background-size:cover" @endif></div>
                        <div class="p-5">
                            <p class="text-xs font-medium text-primary-600 dark:text-primary-300">{{ $event->start_datetime->format('M j · g:i A') }}</p>
                            <h3 class="mt-1 font-semibold">{{ $event->title }}</h3>
                            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">{{ $event->location }} · {{ $event->registrants_count }} going</p>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-6">{{ $events->links() }}</div>
        @endif
    </div>
</x-app-layout>
