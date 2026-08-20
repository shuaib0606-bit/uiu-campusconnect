@if (!$comment->is_deleted)
@php $iLikeComment = $comment->likes->firstWhere('user_id', auth()->id()) ? true : false; @endphp
<div class="flex items-start gap-2.5">
    <a href="{{ route('profile.show', $comment->user) }}" class="shrink-0">
        <span class="grid h-8 w-8 place-items-center rounded-full bg-ink-900/5 dark:bg-white/10 text-xs font-semibold">{{ $comment->user->avatar_initials }}</span>
    </a>
    <div class="min-w-0 flex-1">
        <div class="rounded-2xl bg-ink-900/[0.03] dark:bg-white/[0.05] px-3.5 py-2">
            <a href="{{ route('profile.show', $comment->user) }}" class="text-[13px] font-semibold hover:underline">{{ $comment->user->full_name }}</a>
            <p class="text-[14px]">{{ $comment->body }}</p>
        </div>
        <div class="mt-1 flex items-center gap-3 pl-3.5 text-xs text-ink-500 dark:text-ink-400"
             x-data="ccToggle({ url: '{{ route('comments.like', $comment) }}', active: {{ $iLikeComment ? 'true' : 'false' }}, count: {{ $comment->likes->count() }} })">
            <span>{{ $comment->created_at->diffForHumans() }}</span>
            <button type="button" @click="toggle()" class="transition-colors hover:text-ink-900 dark:hover:text-white" :class="active ? 'text-primary-600 dark:text-primary-300 font-medium' : ''">
                <span x-text="active ? 'Liked' : 'Like'"></span> (<span x-text="count"></span>)
            </button>
            @if($comment->user_id === auth()->id() || auth()->user()->isAdmin())
                <form action="{{ route('comments.destroy', $comment) }}" method="POST">@csrf @method('DELETE')<button class="hover:text-red-600">Delete</button></form>
            @endif
        </div>

        @foreach ($comment->replies as $reply)
            <div class="mt-2 pl-3.5">@include('feed._comment', ['comment' => $reply])</div>
        @endforeach
    </div>
</div>
@endif
