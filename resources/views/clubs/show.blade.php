<x-app-layout title="{{ $club->name }} - UIU CampusConnect">
    <div class="mx-auto max-w-4xl">
        <a href="{{ route('clubs.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-900 dark:hover:text-white"><x-ui-icon name="chevron-right" class="h-4 w-4 rotate-180" />Back to Clubs</a>

        <div class="surface mb-5 overflow-hidden">
            <div class="h-32 w-full bg-cover bg-center bg-primary-500/10 sm:h-44" @if ($club->cover_path) style="background-image:url('{{ Storage::url($club->cover_path) }}')" @endif></div>
            <div class="p-6">
                <div class="-mt-16 flex flex-col items-start gap-4 sm:-mt-20 sm:flex-row sm:items-end">
                    @if ($club->logo_path)
                        <img src="{{ Storage::url($club->logo_path) }}" alt="{{ $club->name }} logo" class="h-24 w-24 shrink-0 rounded-3xl border-4 border-white object-cover shadow-md dark:border-ink-900 sm:h-28 sm:w-28">
                    @else
                        <span class="grid h-24 w-24 shrink-0 place-items-center rounded-3xl border-4 border-white bg-primary-500/10 text-primary-600 shadow-md dark:border-ink-900 dark:text-primary-300 sm:h-28 sm:w-28"><x-ui-icon name="award" class="h-10 w-10" /></span>
                    @endif

                    <div class="min-w-0 flex-1 pb-1">
                        <span class="chip">{{ $club->category }}</span>
                        <h1 class="mt-1 truncate text-[22px] font-semibold tracking-tight">{{ $club->name }}</h1>
                        <p class="section-sub">Moderated by {{ $club->moderator->full_name ?? 'Not assigned' }}</p>
                    </div>

                    @auth
                        <div class="shrink-0">
                            @if ($isMember)
                                <form method="POST" action="{{ route('clubs.leave', $club) }}" onsubmit="return confirm('Leave this club?');">
                                    @csrf
                                    <button type="submit" class="btn-secondary">Leave Club</button>
                                </form>
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
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-200">{{ $reqItem->user->avatar_initials }}</span>
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

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="surface p-6">
                <h2 class="mb-3 text-[15px] font-semibold">Executives &amp; Members</h2>
                <div class="space-y-3">
                    @forelse ($members as $member)
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-200">{{ $member->avatar_initials }}</span>
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
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-200">{{ $post->user->avatar_initials }}</span>
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
