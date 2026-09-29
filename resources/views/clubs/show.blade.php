<x-app-layout title="{{ $club->name }} - UIU CampusConnect">
    <div class="mx-auto max-w-4xl">
        <a href="{{ route('clubs.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-900 dark:hover:text-white"><x-ui-icon name="chevron-right" class="h-4 w-4 rotate-180" />Back to Clubs</a>

        <div class="surface mb-5 overflow-hidden">
            {{-- The logo sits inside the banner rather than being pulled up
                 over it by a negative margin. The gallery grows a thumbnail
                 strip and an "Edit pictures" bar underneath the picture when
                 there is more than one shot or the viewer may edit, and a
                 logo hanging down from the picture covered them. Inside the
                 frame there is nothing to collide with. --}}
            <x-media-gallery :item="$club" type="club" :can-edit="$canEditMedia" height="h-40 sm:h-52">
                <div class="group absolute bottom-3 left-4 z-20 sm:bottom-4 sm:left-5">
                    @if ($club->logo_path)
                        <img src="{{ Storage::url($club->logo_path) }}" alt="{{ $club->name }} logo" class="h-20 w-20 rounded-2xl border-4 border-white object-cover shadow-lg dark:border-ink-900 sm:h-24 sm:w-24">
                    @else
                        <span class="grid h-20 w-20 place-items-center rounded-2xl border-4 border-white bg-primary-500/10 text-primary-600 shadow-lg backdrop-blur dark:border-ink-900 dark:text-primary-300 sm:h-24 sm:w-24"><x-ui-icon name="award" class="h-9 w-9" /></span>
                    @endif

                    {{-- The picker sits on the logo itself rather than in a
                         settings page, because that is where somebody looks
                         when they want to change it. --}}
                    @if ($canEditMedia)
                        <form method="POST" action="{{ route('clubs.logo', $club) }}" enctype="multipart/form-data"
                            x-data="{ busy: false }" @change="busy = true; $el.submit()"
                            class="absolute inset-0">
                            @csrf
                            <label class="absolute inset-1 grid cursor-pointer place-items-center rounded-xl bg-black/60 text-white opacity-0 transition group-hover:opacity-100 focus-within:opacity-100"
                                :class="busy && '!opacity-100'">
                                <span x-show="!busy" class="flex flex-col items-center gap-0.5">
                                    <x-ui-icon name="camera" class="h-5 w-5" />
                                    <span class="text-[10px] font-medium leading-none">Change</span>
                                </span>
                                <span x-show="busy" x-cloak class="text-[10px] font-medium">…</span>
                                <input type="file" name="logo" accept="image/*" class="sr-only">
                            </label>
                        </form>
                    @endif
                </div>
            </x-media-gallery>

            <div class="p-6">
                <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-end">
                    @if ($canEditMedia && $club->logo_path)
                        <form method="POST" action="{{ route('clubs.logo.remove', $club) }}" class="order-last shrink-0">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-ghost !px-2.5 !py-1 text-xs">Remove logo</button>
                        </form>
                    @endif

                    <div class="min-w-0 flex-1">
                        <span class="chip">{{ $club->category }}</span>
                        <h1 class="mt-1 truncate text-[22px] font-semibold tracking-tight">{{ $club->name }}</h1>
                        <p class="section-sub">Moderated by {{ $club->moderator->full_name ?? 'Not assigned' }}</p>
                    </div>

                    @auth
                        <div class="shrink-0">
                            @if ($isMember)
                                <div x-data="{ confirmLeave: false }" class="text-right">
                                    <button type="button" x-show="!confirmLeave" @click="confirmLeave = true" class="btn-secondary">Leave Club</button>
                                    <div x-show="confirmLeave" x-cloak class="flex items-center gap-2">
                                        <span class="text-xs text-ink-600 dark:text-ink-300">Leave this club?</span>
                                        <form method="POST" action="{{ route('clubs.leave', $club) }}">
                                            @csrf
                                            <button type="submit" class="btn-danger !px-3 !py-1.5 text-xs">Leave</button>
                                        </form>
                                        <button type="button" @click="confirmLeave = false" class="btn-ghost !px-3 !py-1.5 text-xs">Stay</button>
                                    </div>
                                </div>
                            @elseif ($hasPending)
                                <span class="btn-secondary cursor-default opacity-70">Request Pending</span>
                            @else
                                <form method="POST" action="{{ route('clubs.request', $club) }}">
                                    @csrf
                                    <button type="submit" class="btn-primary">Request to Join</button>
                                </form>
                            @endif
                        </div>
                    @endauth
                </div>

                <div class="mt-6 grid grid-cols-3 gap-3">
                    <div class="stat-card items-center text-center"><span class="stat-value">{{ $members->count() }}</span><span class="stat-label">Members</span></div>
                    <div class="stat-card items-center text-center"><span class="stat-value">{{ $events->count() }}</span><span class="stat-label">Upcoming Events</span></div>
                    <div class="stat-card items-center text-center"><span class="stat-value">{{ $posts->count() }}</span><span class="stat-label">Recent Posts</span></div>
                </div>

                <div class="mt-6 border-t border-ink-100 pt-5 dark:border-ink-800/70">
                    <h2 class="mb-2 text-[15px] font-semibold">About {{ $club->name }}</h2>
                    <p class="whitespace-pre-line text-sm text-ink-600 dark:text-ink-300">{{ $club->description }}</p>
                    @if ($club->meeting_time || $club->meeting_place)
                        <p class="section-sub mt-3">Meets {{ $club->meeting_time }}{{ $club->meeting_time && $club->meeting_place ? ' at ' : '' }}{{ $club->meeting_place }}</p>
                    @endif
                </div>
            </div>
        </div>

        @if ($canPostClubContent)
            <div class="mb-5 grid gap-5 sm:grid-cols-2">
                <details class="surface p-6">
                    <summary class="cursor-pointer text-[15px] font-semibold">Post a club event</summary>
                    <form method="POST" action="{{ route('events.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                        @csrf
                        <input type="hidden" name="club_id" value="{{ $club->id }}">
                        <label class="block"><span class="mb-1 block text-xs font-medium">Title</span><input name="title" required class="field"></label>
                        <label class="block"><span class="mb-1 block text-xs font-medium">Category</span><input name="category" value="Club Activity" required class="field"></label>
                        <label class="block"><span class="mb-1 block text-xs font-medium">Description</span><textarea name="description" rows="2" required class="field"></textarea></label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="block"><span class="mb-1 block text-xs font-medium">Start</span><input type="datetime-local" name="start_datetime" required class="field"></label>
                            <label class="block"><span class="mb-1 block text-xs font-medium">End</span><input type="datetime-local" name="end_datetime" required class="field"></label>
                        </div>
                        <label class="block"><span class="mb-1 block text-xs font-medium">Location</span><input name="location" required class="field"></label>
                        <button type="submit" class="btn-primary w-full">Publish Event</button>
                    </form>
                </details>

                <details class="surface p-6">
                    <summary class="cursor-pointer text-[15px] font-semibold">Post to the club page</summary>
                    <form method="POST" action="{{ route('posts.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                        @csrf
                        <input type="hidden" name="club_id" value="{{ $club->id }}">
                        <textarea name="body" rows="3" required placeholder="Share an update with {{ $club->name }} followers..." class="field"></textarea>
                        <label class="block"><span class="mb-1 block text-xs font-medium">Attach media (optional)</span><input type="file" name="media" accept="image/*,video/*" class="field !py-1.5"></label>
                        <button type="submit" class="btn-primary w-full">Post</button>
                    </form>
                </details>
            </div>
        @endif

        @if ($canManage && $pendingRequests->isNotEmpty())
            <div class="surface mb-5 p-6">
                <h2 class="mb-3 text-[15px] font-semibold">Pending Membership Requests <span class="chip">{{ $pendingRequests->count() }}</span></h2>
                <div class="space-y-3">
                    @foreach ($pendingRequests as $reqItem)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-ink-900/[0.03] p-3 dark:bg-white/[0.04]">
                            <div class="flex min-w-0 items-center gap-3">
                                <x-user-avatar :user="$reqItem->user" size="h-9 w-9" text="text-xs" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">{{ $reqItem->user->full_name }}</p>
                                    <p class="section-sub truncate">{{ $reqItem->note ?: 'No note provided.' }}</p>
                                </div>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <form method="POST" action="{{ route('clubs.review-request', [$club, $reqItem]) }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="approved">
                                    <button type="submit" class="btn-primary !px-3 !py-1.5 text-xs">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('clubs.review-request', [$club, $reqItem]) }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="rejected">
                                    <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs">Decline</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Panel succession. A club hands over its student panel as a set:
             the outgoing president proposes it, the moderator or a super admin
             agrees, and every old designation is dropped at the same moment
             the new ones land. --}}
        @if ($canProposePanel || $canApprovePanel || $canChangeModerator)
            <div class="surface mb-5 p-6" x-data="{ proposing: false, seats: [{ user: '', role: '' }] }">
                <div class="section-heading mb-3">
                    <div>
                        <h2 class="text-[15px] font-semibold">Student panel</h2>
                        {{-- A proposal in flight is the more useful thing to
                             say than when the last one landed, and saying
                             "none recorded yet" above a pending one reads as
                             though the proposal did not save. --}}
                        <p class="section-sub">
                            @if ($pendingPanel)
                                A handover is waiting for the moderator.
                            @elseif ($lastPanelChange)
                                Last handover {{ $lastPanelChange->decided_at?->format('M j, Y') }}, approved by
                                {{ $lastPanelChange->decider->full_name ?? 'the moderator' }}.
                            @else
                                No panel handover recorded yet.
                            @endif
                        </p>
                    </div>
                    @if ($canProposePanel && ! $pendingPanel)
                        <button type="button" @click="proposing = !proposing" class="btn-secondary !px-3 !py-1.5 text-xs whitespace-nowrap">
                            <x-ui-icon name="plus" class="h-3.5 w-3.5" />Propose new panel
                        </button>
                    @endif
                </div>

                {{-- A pending handover blocks another being raised. --}}
                @if ($pendingPanel)
                    <div class="rounded-xl border border-amber-300/60 bg-amber-500/[0.07] p-4 dark:border-amber-900/50">
                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                            New panel proposed by {{ $pendingPanel->proposer->full_name ?? 'a panel member' }}
                            &middot; {{ $pendingPanel->created_at->diffForHumans() }}
                        </p>

                        @if ($pendingPanel->note)
                            <p class="mt-1 text-sm text-ink-600 dark:text-ink-300">{{ $pendingPanel->note }}</p>
                        @endif

                        <div class="mt-3 grid gap-1.5 sm:grid-cols-2">
                            @foreach ($pendingPanel->panelMembers as $seat)
                                <div class="flex items-center gap-2 rounded-lg bg-white/60 px-3 py-1.5 text-sm dark:bg-white/[0.06]">
                                    <x-user-avatar :user="$seat->user" size="h-6 w-6" text="text-[10px]" />
                                    <span class="min-w-0 truncate">{{ $seat->user->full_name ?? 'Unknown' }}</span>
                                    <span class="chip ml-auto shrink-0">{{ $seat->club_role }}</span>
                                </div>
                            @endforeach
                        </div>

                        <p class="section-sub mt-3">
                            Approving this drops every designation the club currently has and gives them to the people
                            above. Those replaced stay members of the club.
                        </p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($canApprovePanel)
                                <form method="POST" action="{{ route('clubs.panel.review', [$club, $pendingPanel]) }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="approve">
                                    <button type="submit" class="btn-primary !px-3 !py-1.5 text-xs">Approve handover</button>
                                </form>
                                <form method="POST" action="{{ route('clubs.panel.review', [$club, $pendingPanel]) }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="reject">
                                    <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs">Decline</button>
                                </form>
                            @else
                                <span class="chip !bg-amber-500/10 !text-amber-700 dark:!text-amber-300">Waiting for the moderator</span>
                            @endif

                            @if (auth()->id() === (int) $pendingPanel->proposed_by)
                                <form method="POST" action="{{ route('clubs.panel.cancel', [$club, $pendingPanel]) }}">
                                    @csrf
                                    <button type="submit" class="btn-ghost !px-3 !py-1.5 text-xs">Withdraw</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Proposal form. Rows are added client-side; the server takes
                     whatever arrives and ignores blanks. --}}
                @if ($canProposePanel && ! $pendingPanel)
                    <form x-show="proposing" x-cloak method="POST" action="{{ route('clubs.panel.propose', $club) }}" class="space-y-2">
                        @csrf
                        <p class="section-sub">
                            Everyone on this list becomes an officer. Everyone currently holding a designation loses it,
                            including you if you are not on the list. Exactly one of them has to be President.
                        </p>

                        <template x-for="(seat, i) in seats" :key="i">
                            <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                                <select :name="'panel[' + i + '][user_id]'" x-model="seat.user" required class="field !py-1.5 text-sm">
                                    <option value="">Choose a member</option>
                                    {{-- The moderator is staff and cannot hold a
                                         student designation, so they are not
                                         offered a seat. --}}
                                    @foreach ($members->where('id', '!=', $club->moderator_id) as $member)
                                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                                    @endforeach
                                </select>
                                <input :name="'panel[' + i + '][club_role]'" x-model="seat.role" required maxlength="80"
                                    class="field !py-1.5 text-sm" placeholder="President / General Secretary / Treasurer">
                                <button type="button" class="btn-ghost !px-2.5 !py-1 text-xs"
                                    @click="seats.splice(i, 1)" x-show="seats.length > 1">
                                    <x-ui-icon name="x" class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </template>

                        <button type="button" class="btn-secondary !px-3 !py-1.5 text-xs" @click="seats.push({ user: '', role: '' })">
                            <x-ui-icon name="plus" class="h-3.5 w-3.5" />Add another
                        </button>

                        <input name="note" maxlength="500" class="field !py-1.5 text-sm" placeholder="A line about this handover (optional)">

                        <button type="submit" class="btn-primary !py-1.5 text-xs">Send for approval</button>
                    </form>
                @endif

                {{-- Super admin only: who the faculty moderator is. --}}
                @if ($canChangeModerator)
                    <div class="mt-4 border-t border-ink-100 pt-4 dark:border-ink-800/70">
                        <p class="text-sm font-semibold">Club moderator</p>
                        <p class="section-sub mb-2">Only a super admin can appoint or replace the faculty moderator.</p>
                        <form method="POST" action="{{ route('clubs.moderator', $club) }}" class="flex flex-wrap gap-2">
                            @csrf @method('PUT')
                            <select name="moderator_id" class="field !w-auto !py-1.5 text-sm">
                                <option value="">No moderator</option>
                                @foreach ($moderatorOptions as $option)
                                    <option value="{{ $option->id }}" @selected($club->moderator_id === $option->id)>
                                        {{ $option->full_name }}{{ $option->department ? ' · '.$option->department : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs">Save moderator</button>
                        </form>
                    </div>
                @endif
            </div>
        @endif

        {{-- Club designations. An officer may put a name forward; only the
             moderator may agree to it. --}}
        @if ($canNominate)
            <div class="surface mb-5 p-6" x-data="{ nominating: false }">
                <div class="section-heading mb-3">
                    <div>
                        <h2 class="text-[15px] font-semibold">Club designations</h2>
                        <p class="section-sub">
                            {{ $canManage
                                ? 'Officers can put a member forward. You are the one who approves it.'
                                : 'Put a member forward for a designation. The club moderator approves it before it takes effect.' }}
                        </p>
                    </div>
                    <button type="button" @click="nominating = !nominating" class="btn-secondary !px-3 !py-1.5 text-xs whitespace-nowrap">
                        <x-ui-icon name="plus" class="h-3.5 w-3.5" />Put a name forward
                    </button>
                </div>

                <form x-show="nominating" x-cloak method="POST" action="{{ route('clubs.officers.nominate', $club) }}"
                    class="mb-4 grid gap-2 rounded-xl bg-ink-900/[0.03] p-3.5 sm:grid-cols-[1fr_1fr_auto] dark:bg-white/[0.04]">
                    @csrf
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium">Member</span>
                        <select name="nominee_id" required class="field !py-1.5 text-sm">
                            <option value="">Choose a member</option>
                            @foreach ($members as $member)
                                @continue($club->moderator_id === $member->id)
                                <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium">Designation</span>
                        <input name="club_role" required maxlength="80" class="field !py-1.5 text-sm" placeholder="General Secretary">
                    </label>
                    {{-- The reason comes before the button, or the form asks
                         to be sent halfway through filling it in. --}}
                    <label class="block sm:col-span-3">
                        <span class="mb-1 block text-xs font-medium">Why (optional)</span>
                        <input name="reason" maxlength="500" class="field !py-1.5 text-sm" placeholder="Ran the last two events single-handed.">
                    </label>
                    <div class="sm:col-span-3">
                        <button type="submit" class="btn-primary !py-1.5 text-xs">Send for approval</button>
                    </div>
                </form>

                @forelse ($roleRequests as $roleRequest)
                    <div class="mb-2 rounded-xl border border-ink-100 p-3.5 dark:border-ink-800">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm">
                                    <span class="font-semibold">{{ $roleRequest->nominee->full_name }}</span>
                                    as <span class="font-semibold">{{ $roleRequest->club_role }}</span>
                                </p>
                                <p class="section-sub">
                                    Put forward by {{ $roleRequest->nominator->full_name }}
                                    &middot; {{ $roleRequest->created_at->diffForHumans() }}
                                </p>
                                @if ($roleRequest->reason)
                                    <p class="mt-1.5 text-sm text-ink-600 dark:text-ink-300">{{ $roleRequest->reason }}</p>
                                @endif
                            </div>

                            <div class="flex shrink-0 flex-wrap gap-2">
                                @if ($canManage)
                                    <form method="POST" action="{{ route('clubs.officers.review', [$club, $roleRequest]) }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="approve">
                                        <button type="submit" class="btn-primary !px-3 !py-1.5 text-xs">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('clubs.officers.review', [$club, $roleRequest]) }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="reject">
                                        <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs">Decline</button>
                                    </form>
                                @elseif (auth()->id() === (int) $roleRequest->nominated_by)
                                    <span class="chip !bg-amber-500/10 !text-amber-700 dark:!text-amber-300">Waiting for the moderator</span>
                                    <form method="POST" action="{{ route('clubs.officers.cancel', [$club, $roleRequest]) }}">
                                        @csrf
                                        <button type="submit" class="btn-ghost !px-3 !py-1.5 text-xs">Withdraw</button>
                                    </form>
                                @else
                                    <span class="chip">Pending</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="section-sub">No designation requests waiting.</p>
                @endforelse
            </div>
        @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="surface p-6">
                <h2 class="mb-3 text-[15px] font-semibold">Executives &amp; Members</h2>
                <div class="space-y-3">
                    @forelse ($members as $member)
                        <div class="flex items-center gap-3">
                            <x-user-avatar :user="$member" size="h-9 w-9" text="text-xs" />
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('profile.show', $member) }}" class="text-sm font-semibold hover:underline">{{ $member->full_name }}</a>
                                <p class="section-sub truncate">{{ $member->department ?: ucfirst($member->role) }}</p>
                            </div>
                            @if (! in_array(strtolower($member->pivot->club_role ?? 'member'), ['member', 'general member'], true))
                                <span class="chip shrink-0">{{ ucwords($member->pivot->club_role) }}</span>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-ink-400">No members yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="surface p-6">
                <h2 class="mb-3 text-[15px] font-semibold">Upcoming Club Events</h2>
                <div class="space-y-3">
                    @forelse ($events as $event)
                        <a href="{{ route('events.show', $event) }}" class="block rounded-xl bg-ink-900/[0.03] p-3 hover:bg-ink-900/[0.05] dark:bg-white/[0.04] dark:hover:bg-white/[0.06]">
                            <p class="truncate text-sm font-semibold">{{ $event->title }}</p>
                            <p class="section-sub">{{ $event->start_datetime->format('M j, Y g:i A') }} &middot; {{ $event->registrants_count }} registered</p>
                        </a>
                    @empty
                        <p class="text-sm text-ink-400">No upcoming events.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="surface mt-5 p-6">
            <h2 class="mb-3 text-[15px] font-semibold">Club Feed</h2>
            <div class="space-y-4">
                @forelse ($posts as $post)
                    <div class="border-b border-ink-100 pb-4 last:border-0 last:pb-0 dark:border-ink-800/70">
                        <div class="flex items-center gap-3">
                            <x-user-avatar :user="$post->user" size="h-9 w-9" text="text-xs" />
                            <div class="min-w-0">
                                <p class="text-sm font-semibold">{{ $post->user->full_name }}</p>
                                <p class="section-sub">{{ $post->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm text-ink-600 dark:text-ink-300">{{ $post->body }}</p>
                        @if ($post->media_type === 'image' && $post->media_path)
                            <img src="{{ Storage::url($post->media_path) }}" alt="Post attachment" class="mt-3 max-h-80 w-full rounded-xl object-cover">
                        @elseif ($post->media_type === 'video' && $post->media_path)
                            <video src="{{ Storage::url($post->media_path) }}" controls class="mt-3 max-h-80 w-full rounded-xl"></video>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-ink-400">No posts from this club yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
