<x-app-layout title="Groups — UIU CampusConnect">
    <div class="mx-auto max-w-5xl">
        <div class="section-heading mb-5">
            <div>
                <h1>Groups</h1>
                <p class="section-sub">{{ $groups->total() }} study & interest groups</p>
            </div>
        </div>

        @if ($groups->isEmpty())
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="users" class="h-6 w-6" /></span>
                <h3>No groups yet</h3>
                <p>Course groups and interest circles will show up here once they're created.</p>
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($groups as $group)
                    <a href="{{ route('groups.show', $group) }}" class="surface surface-hover overflow-hidden">
                        <div class="h-24 bg-gradient-to-br from-primary-400 to-primary-600" @if($group->cover_path) style="background-image:url({{ Storage::url($group->cover_path) }});background-size:cover" @endif></div>
                        <div class="p-5">
                            <h3 class="font-semibold">{{ $group->name }}</h3>
                            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">{{ $group->members_count }} member{{ $group->members_count === 1 ? '' : 's' }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-6">{{ $groups->links() }}</div>
        @endif
    </div>
</x-app-layout>
