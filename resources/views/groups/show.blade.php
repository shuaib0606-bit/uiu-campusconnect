@php
    $section = $group->isCourse() ? $group->section : null;
    $trimester = $section?->trimester;
    $roleOf = fn ($member) => ucfirst($member->pivot->role ?? 'member');

    $noticeTone = [
        'urgent' => 'border-l-red-500 bg-red-500/[0.07] text-red-700 dark:text-red-300',
        'warning' => 'border-l-amber-500 bg-amber-500/[0.07] text-amber-800 dark:text-amber-300',
        'muted' => 'border-l-ink-400 bg-ink-900/[0.04] text-ink-600 dark:bg-white/[0.05] dark:text-ink-300',
    ];
@endphp
<x-app-layout :title="$group->name.' - UIU CampusConnect'">
    <div class="mx-auto max-w-5xl" x-data="{ panel: @js(in_array(request('panel'), ['materials', 'chat', 'assignments', 'notices'], true) ? request('panel') : null), publishing: null }">
        <a href="{{ route('groups.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-ink-500 transition hover:text-ink-900 dark:text-ink-400 dark:hover:text-white">
            <x-ui-icon name="chevron-right" class="h-4 w-4 rotate-180" />
            Back to Groups
        </a>

        {{-- Header --}}
        <div class="surface mb-5 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-1.5">
                        @if ($group->isCourse())
                            <span class="chip !bg-primary-500/10 !text-primary-700 dark:!text-primary-300">Course</span>
                            @if ($trimester)<span class="chip">{{ $trimester->name }}</span>@endif
                        @elseif ($group->isClub())
                            <span class="chip !bg-primary-500/10 !text-primary-700 dark:!text-primary-300">Club</span>
                        @else
                            <span class="chip">{{ ucfirst($group->group_type) }}</span>
                        @endif

                        @if ($group->isArchived())
                            <span class="chip">Archived</span>
                        @elseif ($group->isPrivate() && $group->isOpen())
                            <span class="chip !bg-amber-500/10 !text-amber-700 dark:!text-amber-300">
                                <x-ui-icon name="lock" class="h-3 w-3" />Private
                            </span>
                        @endif

                        @if ($group->posting_mode === \App\Models\CommunityGroup::POSTING_STAFF && ! $group->isOpen())
                            <span class="chip">Announcements only</span>
                        @endif
                    </div>

                    <h1 class="mt-1.5 text-[22px] font-semibold tracking-tight">{{ $group->name }}</h1>

                    <p class="section-sub">
                        @if ($section)
                            {{ $section->scheduleLine() }}
                            @if ($group->owner) &middot; <a href="{{ route('profile.show', $group->owner) }}" class="hover:underline">{{ $group->owner->full_name }}</a>@endif
                        @elseif ($group->owner)
                            {{ $group->department ?: 'All departments' }} &middot;
                            Owner <a href="{{ route('profile.show', $group->owner) }}" class="hover:underline">{{ $group->owner->full_name }}</a>
                        @endif
                    </p>
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    @if ($canSeeInside)
                        {{-- The three-dot menu: everything that is not the wall. --}}
                        <button type="button" @click="panel = panel === 'materials' ? null : 'materials'"
                            class="btn-secondary !px-3 !py-1.5 text-xs" :class="panel === 'materials' && 'ring-2 ring-primary-500'">
                            <x-ui-icon name="paperclip" class="h-3.5 w-3.5" />Materials
                            @if ($materials->isNotEmpty())<span class="opacity-70">{{ $materials->count() }}</span>@endif
                        </button>
                        {{-- Chat belongs to the teacher's communication
                             switch. Staff always see it, since they decide
                             whether it is on; students see it only once it
                             has been turned on, rather than being shown a
                             room they are not allowed to speak in. --}}
                        @if ($chatVisible)
                            <button type="button" @click="panel = panel === 'chat' ? null : 'chat'"
                                class="btn-secondary !px-3 !py-1.5 text-xs" :class="panel === 'chat' && 'ring-2 ring-primary-500'">
                                <x-ui-icon name="message" class="h-3.5 w-3.5" />Chat
                            </button>
                        @endif
                        <button type="button" @click="panel = panel === 'assignments' ? null : 'assignments'"
                            class="btn-secondary !px-3 !py-1.5 text-xs" :class="panel === 'assignments' && 'ring-2 ring-primary-500'">
                            <x-ui-icon name="file-text" class="h-3.5 w-3.5" />Assignments
                            @if ($assignments->isNotEmpty())<span class="opacity-70">{{ $assignments->count() }}</span>@endif
                        </button>
                        <button type="button" @click="panel = panel === 'notices' ? null : 'notices'"
                            class="btn-secondary !px-3 !py-1.5 text-xs" :class="panel === 'notices' && 'ring-2 ring-primary-500'">
                            <x-ui-icon name="flag" class="h-3.5 w-3.5" />Notices
                            @if ($notices->isNotEmpty())<span class="opacity-70">{{ $notices->count() }}</span>@endif
                        </button>
                    @endif

                    @if ($canManage)
                        <button type="button" @click="panel = panel === 'manage' ? null : 'manage'" class="btn-secondary !px-3 !py-1.5 text-xs">
                            <x-ui-icon name="settings" class="h-3.5 w-3.5" />Manage
                        </button>
                    @endif

                    @auth
                        @if ($group->isOpen() && ! $group->isArchived())
                            @if ($isMember && ! $group->isOwnedBy(auth()->user()))
                                <form method="POST" action="{{ route('groups.leave', $group) }}">
                                    @csrf
                                    <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs">Leave group</button>
                                </form>
                            @elseif ($isPending)
                                <span class="chip !bg-amber-500/10 !text-amber-700 dark:!text-amber-300">Request pending</span>
                            @elseif (! $isMember)
                                <form method="POST" action="{{ route('groups.join', $group) }}">
                                    @csrf
                                    <button type="submit" class="btn-primary">
                                        {{ $group->isPrivate() ? 'Ask to join' : 'Join group' }}
                                    </button>
                                </form>
                            @endif
                        @endif
                    @endauth
                </div>
            </div>

            @if (! $group->isCourse())
                <p class="mt-4 whitespace-pre-line text-sm text-ink-600 dark:text-ink-300">{{ $group->description }}</p>
            @endif

            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-500 dark:text-ink-400">
                <span class="inline-flex items-center gap-1"><x-ui-icon name="users" class="h-3.5 w-3.5" />{{ $memberCount }} members</span>
                @if ($canSeeInside)
                    <span class="inline-flex items-center gap-1"><x-ui-icon name="message" class="h-3.5 w-3.5" />{{ $posts->count() }} posts</span>
                @endif
            </div>
        </div>

        {{-- End-of-trimester countdown, then the archived note. --}}
        @if ($notice)
            <div class="surface-flat mb-5 flex items-start gap-2 border-l-4 px-4 py-3 text-sm {{ $noticeTone[$notice['tone']] ?? $noticeTone['muted'] }}">
                <x-ui-icon name="{{ $notice['tone'] === 'muted' ? 'inbox' : 'alert-triangle' }}" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>{{ $notice['text'] }}</span>
            </div>
        @endif

        @if (! $canSeeInside)
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="lock" class="h-6 w-6" /></span>
                <h3>{{ $group->isCourse() ? 'This is a class group' : 'This group is private' }}</h3>
                <p>
                    @if ($group->isCourse())
                        Only the people enrolled in this section and the course teacher can see inside.
                    @elseif ($group->isClub())
                        Join the club to see its group.
                    @elseif ($isPending)
                        Your request is with the group owner. Posts, members and files appear once it is accepted.
                    @else
                        Posts, members and files are visible to members only. Ask to join to see inside.
                    @endif
                </p>
            </div>
        @else
            {{-- Materials --}}
            <div x-show="panel === 'materials'" x-cloak class="surface mb-5 p-6">
                @include('groups._resource_list', [
                    'heading' => 'Materials',
                    'blurb' => 'Slides, readings and anything else shared for this group.',
                    'items' => $materials,
                    'canAdd' => $canUpload,
                ])
            </div>

            {{-- Chat. Rendered only while its panel is open, so the socket
                 is not connected and no polling starts on a page where
                 nobody asked for a conversation. --}}
            @if ($chatVisible)
                <template x-if="panel === 'chat'">
                    <div class="surface mb-5 p-6">
                        @include('groups._chat')
                    </div>
                </template>
            @endif

            {{-- Assignments. `setting` is local to this panel: it opens the
                 form a teacher uses to set new work. --}}
            <div x-show="panel === 'assignments'" x-cloak class="surface mb-5 p-6" x-data="{ setting: false }">
                @include('groups._assignments')
            </div>

            {{-- Notices. These are real notices: the same record also appears
                 on the university notice board, but only for the people in
                 this group. --}}
            <div x-show="panel === 'notices'" x-cloak class="surface mb-5 p-6">
                <div class="section-heading mb-4">
                    <div>
                        <h2 class="text-[15px] font-semibold">Notices</h2>
                        <p class="section-sub">
                            {{ $group->isCourse()
                                ? 'Announcements from the course teacher. They also appear on your notice board.'
                                : 'Announcements from the people running this group. They also appear on members\' notice boards.' }}
                        </p>
                    </div>
                </div>

                @if ($canPublish)
                    <form method="POST" action="{{ route('groups.notices.store', $group) }}"
                        class="mb-5 space-y-2 rounded-xl bg-ink-900/[0.03] p-3.5 dark:bg-white/[0.04]">
                        @csrf
                        <input name="title" required maxlength="190" class="field !py-1.5 text-sm"
                            placeholder="Heading, e.g. Quiz 2 moved to Sunday">
                        <textarea name="body" rows="3" required maxlength="4000" class="field text-sm"
                            placeholder="What do they need to know?"></textarea>
                        <label class="flex items-center gap-2 text-xs text-ink-600 dark:text-ink-300">
                            <input type="checkbox" name="is_urgent" value="1" class="rounded">
                            Mark as urgent
                        </label>
                        <button type="submit" class="btn-primary !py-1.5 text-xs">Post notice</button>
                    </form>
                @endif

                <div class="space-y-2">
                    @forelse ($notices as $item)
                        <div class="rounded-xl border border-ink-100 px-4 py-3 dark:border-ink-800">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="inline-flex flex-wrap items-center gap-1.5 text-sm font-semibold">
                                        <a href="{{ route('notices.show', $item) }}" class="hover:underline">{{ $item->title }}</a>
                                        @if ($item->isImportant())
                                            <span class="chip !bg-red-500/10 !text-red-600 dark:!text-red-300">Urgent</span>
                                        @endif
                                    </p>
                                    <p class="section-sub truncate">
                                        {{ $item->issuer->full_name ?? 'University' }} &middot; {{ $item->created_at->format('M j, Y g:i A') }}
                                    </p>
                                </div>
                                @if (! $group->isArchived() && ($canManage || auth()->id() === (int) $item->issuer_id))
                                    <form method="POST" action="{{ route('groups.notices.delete', [$group, $item]) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-ghost shrink-0 !px-2.5 !py-1 text-xs">Remove</button>
                                    </form>
                                @endif
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm text-ink-600 dark:text-ink-300">{{ $item->body }}</p>
                        </div>
                    @empty
                        <div class="empty-state !py-8">
                            <span class="empty-icon"><x-ui-icon name="flag" class="h-5 w-5" /></span>
                            <h3>Nothing here yet</h3>
                            <p>Announcements posted for this group will appear here and on members' notice boards.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Manage --}}
            @if ($canManage)
                <div x-show="panel === 'manage'" x-cloak class="surface mb-5 p-6" x-data="{ confirmDelete: false }">
                    <h2 class="text-[15px] font-semibold">Manage this group</h2>

                    @unless ($group->isArchived())
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-ink-900/[0.03] p-3.5 dark:bg-white/[0.04]">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold">Student communication</p>
                                <p class="section-sub">
                                    {{ $group->posting_mode === \App\Models\CommunityGroup::POSTING_EVERYONE
                                        ? 'Members can post on the wall and use the class chat.'
                                        : 'Only you can post. Students cannot see the chat at all while this is off.' }}
                                </p>
                            </div>
                            <form method="POST" action="{{ route('groups.posting-mode', $group) }}">
                                @csrf
                                <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs whitespace-nowrap">
                                    {{ $group->posting_mode === \App\Models\CommunityGroup::POSTING_EVERYONE ? 'Turn off' : 'Turn on' }}
                                </button>
                            </form>
                        </div>
                    @endunless

                    @if ($group->isOpen() && ! $group->isArchived())
                        <form method="POST" action="{{ route('groups.update', $group) }}" class="mt-4 space-y-3">
                            @csrf @method('PUT')
                            <label class="block">
                                <span class="mb-1 block text-xs font-medium">Description</span>
                                <textarea name="description" rows="3" required maxlength="2000" class="field">{{ old('description', $group->description) }}</textarea>
                            </label>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <label class="block">
                                    <span class="mb-1 block text-xs font-medium">Department</span>
                                    <input name="department" maxlength="120" value="{{ old('department', $group->department) }}" class="field">
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-xs font-medium">Type</span>
                                    <select name="group_type" class="field">
                                        @foreach (['course' => 'Course', 'batch' => 'Batch', 'project' => 'Project', 'club' => 'Club', 'community' => 'Community'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('group_type', $group->group_type) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-xs font-medium">Who can see inside</span>
                                    <select name="visibility" class="field">
                                        <option value="public" @selected(old('visibility', $group->visibility) !== 'private')>Anyone at UIU</option>
                                        <option value="private" @selected(old('visibility', $group->visibility) === 'private')>Members only</option>
                                    </select>
                                </label>
                            </div>
                            <button type="submit" class="btn-primary">Save changes</button>
                        </form>

                        <div class="mt-4 border-t border-ink-100 pt-4 dark:border-ink-800/70">
                            <button type="button" x-show="!confirmDelete" @click="confirmDelete = true" class="btn-secondary !px-3 !py-1.5 text-xs !text-red-600 dark:!text-red-300">
                                Delete this group
                            </button>
                            <div x-show="confirmDelete" x-cloak class="rounded-xl bg-red-500/10 p-3">
                                <p class="text-sm text-red-700 dark:text-red-300">
                                    Deleting removes every post, file and membership in this group. It cannot be undone.
                                </p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('groups.destroy', $group) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-danger !px-3 !py-1.5 text-xs">Yes, delete it</button>
                                    </form>
                                    <button type="button" @click="confirmDelete = false" class="btn-ghost !px-3 !py-1.5 text-xs">Cancel</button>
                                </div>
                            </div>
                        </div>
                    @elseif ($group->isCourse())
                        <p class="section-sub mt-4">
                            The name, schedule and class list come from the registrar's roster, so they are not edited here.
                            Removing somebody below also marks them as having dropped the section.
                        </p>
                    @elseif ($group->isClub())
                        <p class="section-sub mt-4">
                            Members follow the club itself. Add and remove people on the club page and this group keeps up.
                        </p>
                    @endif
                </div>
            @endif

            <div class="grid gap-5 lg:grid-cols-[1fr_320px]">
                {{-- The wall --}}
                <div class="min-w-0 space-y-4">
                    @if ($canPost)
                        @include('feed._composer', ['group' => $group])
                    @elseif ($group->isArchived())
                        <div class="surface-flat flex items-center gap-2 px-4 py-3 text-sm text-ink-500 dark:text-ink-400">
                            <x-ui-icon name="inbox" class="h-4 w-4 shrink-0" />
                            This group is archived, so nothing new can be posted.
                        </div>
                    @elseif ($isMember)
                        <div class="surface-flat flex items-center gap-2 px-4 py-3 text-sm text-ink-500 dark:text-ink-400">
                            <x-ui-icon name="lock" class="h-4 w-4 shrink-0" />
                            {{ $group->isCourse()
                                ? 'Only the course teacher can post here. They can turn on student posting if they want a discussion.'
                                : 'Only the people running this group can post here.' }}
                        </div>
                    @endif

                    @forelse ($posts as $post)
                        @include('feed._post', ['post' => $post])
                    @empty
                        <div class="empty-state">
                            <span class="empty-icon"><x-ui-icon name="message" class="h-6 w-6" /></span>
                            <h3>Nothing posted yet</h3>
                            <p>
                                {{ $group->isCourse()
                                    ? 'Announcements from the course teacher will appear here.'
                                    : 'Ask a question, share a deadline, or start the conversation.' }}
                            </p>
                        </div>
                    @endforelse
                </div>

                {{-- Sidebar --}}
                <div class="space-y-5">
                    @if ($pending->isNotEmpty())
                        <div class="surface p-5">
                            <h2 class="mb-3 text-[15px] font-semibold">Requests to join ({{ $pending->count() }})</h2>
                            <div class="space-y-3">
                                @foreach ($pending as $person)
                                    <div class="flex items-center gap-3">
                                        <x-user-avatar :user="$person" size="h-9 w-9" text="text-xs" />
                                        <div class="min-w-0 flex-1">
                                            <a href="{{ route('profile.show', $person) }}" class="truncate text-sm font-semibold hover:underline">{{ $person->full_name }}</a>
                                            <p class="section-sub truncate">{{ $person->department ?: 'UIU' }}</p>
                                        </div>
                                        <form method="POST" action="{{ route('groups.members.approve', [$group, $person]) }}">
                                            @csrf
                                            <button type="submit" class="btn-primary !px-2.5 !py-1 text-xs">Accept</button>
                                        </form>
                                        <form method="POST" action="{{ route('groups.members.remove', [$group, $person]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-ghost !px-2.5 !py-1 text-xs">Decline</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="surface p-5">
                        <h2 class="mb-3 text-[15px] font-semibold">
                            {{ $group->isCourse() ? 'Class list' : 'Members' }}
                            <span class="font-normal text-ink-400">({{ $memberCount }})</span>
                        </h2>
                        <div class="rail-scroll max-h-[28rem] space-y-3 overflow-y-auto">
                            @forelse ($members as $member)
                                <div class="flex items-center gap-3">
                                    <x-user-avatar :user="$member" size="h-9 w-9" text="text-xs" />
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('profile.show', $member) }}" class="truncate text-sm font-semibold hover:underline">{{ $member->full_name }}</a>
                                        <p class="section-sub truncate">
                                            {{ $roleOf($member) }}{{ $member->student_id ? ' · '.$member->student_id : '' }}
                                        </p>
                                    </div>
                                    @if ($canManage && ! $group->isOwnedBy($member) && ! $group->isClub() && ! $group->isArchived())
                                        <form method="POST" action="{{ route('groups.members.remove', [$group, $member]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-ghost !px-2 !py-1 text-xs" title="Remove from group">
                                                <x-ui-icon name="x" class="h-3.5 w-3.5" />
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-ink-400">No members yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
