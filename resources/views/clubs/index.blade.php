<x-app-layout title="Clubs — UIU CampusConnect">
    <div class="mx-auto max-w-5xl">
        <div class="section-heading mb-5">
            <div>
                <h1>Clubs</h1>
                <p class="section-sub">{{ $clubs->total() }} active clubs on campus</p>
            </div>
        </div>

        @if ($clubs->isEmpty())
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="award" class="h-6 w-6" /></span>
                <h3>No clubs yet</h3>
                <p>Once clubs are set up, they will appear here for students to browse and join.</p>
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($clubs as $club)
                    <a href="{{ route('clubs.show', $club) }}" class="surface surface-hover overflow-hidden">
                        <div class="h-24 bg-gradient-to-br from-primary-400 to-primary-600" @if($club->cover_path) style="background-image:url({{ Storage::url($club->cover_path) }});background-size:cover" @endif></div>
                        <div class="p-5">
                            <h3 class="font-semibold">{{ $club->name }}</h3>
                            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">{{ $club->category }} · {{ $club->members_count }} member{{ $club->members_count === 1 ? '' : 's' }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-6">{{ $clubs->links() }}</div>
        @endif
    </div>
</x-app-layout>
