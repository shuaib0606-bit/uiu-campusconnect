<x-app-layout title="Groups - UIU CampusConnect">
    <div class="mx-auto max-w-5xl">
        <div class="section-heading mb-5">
            <div>
                <h1>Course &amp; Community Groups</h1>
                <p class="section-sub">Create CSE 101, batch, final-year project, programming help, thesis, and club resource spaces.</p>
            </div>
        </div>

        @auth
            <details class="surface mb-5 p-6">
                <summary class="cursor-pointer text-[15px] font-semibold">Create a group</summary>
                <form method="POST" action="{{ route('groups.store') }}" class="mt-4 space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-2">
                        <label class="block"><span class="mb-1 block text-xs font-medium">Name</span><input name="name" placeholder="CSE 101 / BBA Batch 23" required class="field"></label>
                        <label class="block"><span class="mb-1 block text-xs font-medium">Type</span>
                            <select name="group_type" class="field">
                                <option value="course">Course</option>
                                <option value="batch">Batch</option>
                                <option value="project">Project</option>
                                <option value="club">Club</option>
                                <option value="community">Community</option>
                            </select>
                        </label>
                    </div>
                    <label class="block"><span class="mb-1 block text-xs font-medium">Department</span><input name="department" placeholder="Department (optional)" class="field"></label>
                    <label class="block"><span class="mb-1 block text-xs font-medium">Description</span><textarea name="description" rows="2" placeholder="What is this group for?" required class="field"></textarea></label>
                    <button type="submit" class="btn-primary w-full">Create Group</button>
                </form>
            </details>
        @endauth

        <form method="GET" class="mb-5 flex flex-wrap gap-2">
            <div class="relative flex-1 min-w-[200px]">
                <x-ui-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-ink-400" />
                <input type="search" name="q" value="{{ $q }}" placeholder="Search groups" class="field pl-10">
            </div>
            <select name="type" class="field w-auto">
                <option value="">All types</option>
                @foreach (['course', 'batch', 'project', 'club', 'community'] as $option)
                    <option value="{{ $option }}" @selected($type === $option)>{{ ucfirst($option) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-secondary">Search</button>
        </form>

        @if ($groups->isEmpty())
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="users" class="h-6 w-6" /></span>
                <h3>No groups found</h3>
                <p>Try a different search or type filter, or be the first to create one.</p>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($groups as $group)
                    <article class="surface surface-hover flex flex-col p-5">
                        <span class="chip w-fit">{{ ucfirst($group->group_type) }}</span>
                        <h2 class="mt-2 text-[15px] font-semibold"><a href="{{ route('groups.show', $group) }}" class="hover:underline">{{ $group->name }}</a></h2>
                        <p class="mt-1 flex-1 text-sm text-ink-600 dark:text-ink-300">{{ \Illuminate\Support\Str::limit($group->description, 140) }}</p>
                        <p class="section-sub mt-3">{{ $group->department ?: 'All departments' }} &middot; {{ $group->members_count }} members &middot; {{ $group->files_count }} files &middot; Owner {{ $group->owner->full_name }}</p>
                        <a href="{{ route('groups.show', $group) }}" class="btn-primary mt-4 w-fit">Open Group</a>
                    </article>
                @endforeach
            </div>
            <div class="mt-6">{{ $groups->links() }}</div>
        @endif
    </div>
</x-app-layout>
