{{--
    A group post that is not on the wall yet: waiting for approval, or turned
    down. $mode is 'review' for the people running the group (with Approve /
    Decline) or 'mine' for the post's author (with its status and Delete).
    Nothing here reacts, comments or shares - those open once it is approved.
--}}
@php
    $waiting = $post->approval_status === \App\Models\Post::APPROVAL_PENDING;
    $files = $post->media ?? collect();
@endphp
<div class="surface p-4 {{ $mode === 'review' ? '' : ($waiting ? 'ring-1 ring-amber-500/30' : 'opacity-80') }}" id="pending-post-{{ $post->id }}">
    <div class="flex items-start gap-3">
        <x-user-avatar :user="$post->user" size="h-9 w-9" text="text-xs" />
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <a href="{{ route('profile.show', $post->user) }}" class="text-sm font-semibold hover:underline">{{ $post->user->full_name }}</a>
                <span class="text-xs text-ink-400">{{ $post->created_at->diffForHumans() }}</span>
                @if ($waiting)
                    <span class="chip !bg-amber-500/10 !text-amber-700 dark:!text-amber-300">
                        <x-ui-icon name="clock" class="h-3 w-3" />{{ $mode === 'review' ? 'Waiting for your approval' : 'Waiting for approval' }}
                    </span>
                @else
                    <span class="chip !bg-red-500/10 !text-red-700 dark:!text-red-300">Not approved</span>
                @endif
            </div>

            <p class="mt-2 whitespace-pre-line break-words text-[15px] leading-relaxed">{{ $post->body }}</p>

            @if ($files->isNotEmpty())
                <p class="mt-2 inline-flex items-center gap-1 text-xs text-ink-500 dark:text-ink-400">
                    <x-ui-icon name="paperclip" class="h-3.5 w-3.5" />
                    {{ $files->count() }} {{ \Illuminate\Support\Str::plural('attachment', $files->count()) }}
                </p>
            @endif

            @if ($mode === 'mine')
                <p class="section-sub mt-2">
                    {{ $waiting
                        ? 'Only you and the group owner can see this until it is approved.'
                        : 'The group owner did not approve this post. Only you can see it.' }}
                </p>
            @endif

            <div class="mt-3 flex flex-wrap gap-2">
                @if ($mode === 'review')
                    <form method="POST" action="{{ route('groups.posts.approve', [$group, $post]) }}">
                        @csrf
                        <button type="submit" class="btn-primary !px-3 !py-1.5 text-xs">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('groups.posts.reject', [$group, $post]) }}">
                        @csrf
                        <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs">Decline</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('posts.destroy', $post) }}">
                        @csrf @method('DELETE')
                        <input type="hidden" name="reason" value="Removed by author">
                        <button type="submit" class="btn-ghost !px-3 !py-1.5 text-xs">Delete</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
