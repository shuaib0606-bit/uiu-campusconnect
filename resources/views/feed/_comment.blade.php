@if (!$comment->is_deleted)
@php
    $myCommentReaction = $comment->likes->firstWhere('user_id', auth()->id());
    $reactionSet = \App\Http\Controllers\ReactionController::REACTIONS;
@endphp
{{-- Anchor target for notification links to a comment. --}}
<div id="comment-{{ $comment->id }}" class="flex items-start gap-2.5">
    <a href="{{ route('profile.show', $comment->user) }}" class="shrink-0">
        <span class="grid h-8 w-8 place-items-center rounded-full bg-ink-900/5 dark:bg-white/10 text-xs font-semibold">{{ $comment->user->avatar_initials }}</span>
    </a>
    <div class="min-w-0 flex-1">
        <div class="rounded-2xl bg-ink-900/[0.03] dark:bg-white/[0.05] px-3.5 py-2">
            <a href="{{ route('profile.show', $comment->user) }}" class="text-[13px] font-semibold hover:underline">{{ $comment->user->full_name }}</a>
            {{-- Mentions::render() escapes the body before injecting mention anchors. --}}
            <p class="whitespace-pre-line text-[14px]">{!! \App\Support\Mentions::render($comment) !!}</p>
        </div>

        <div class="mt-1 flex items-center gap-3 pl-3.5 text-xs text-ink-500 dark:text-ink-400">
            <span>{{ $comment->created_at->diffForHumans() }}</span>

            <div class="relative" x-data="ccReactions({
                    url: '{{ route('comments.react', $comment) }}',
                    active: {{ $myCommentReaction ? 'true' : 'false' }},
                    reaction: {{ $myCommentReaction ? "'".$myCommentReaction->reaction."'" : 'null' }},
                    count: {{ $comment->likes->count() }},
                    labels: @js(collect($reactionSet)->map(fn ($r) => $r['label'])),
                    icons: @js(collect($reactionSet)->map(fn ($r) => $r['icon'])),
                })"
                @mouseenter="show()" @mouseleave="hide()">

                <div x-show="open" x-cloak
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-1 scale-90"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-120"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-90"
                    class="absolute bottom-full left-0 z-30 origin-bottom-left pb-1.5">
                    <div class="flex items-center gap-0.5 rounded-full border border-ink-100 bg-[var(--surface-raised)] p-1 shadow-lifted dark:border-ink-800">
                        @foreach ($reactionSet as $key => $r)
                            <button type="button" @click="send('{{ $key }}')"
                                class="reaction-pick !h-8 !w-8" :class="reaction === '{{ $key }}' && 'is-picked'"
                                title="{{ $r['label'] }}" aria-label="{{ $r['label'] }}">
                                <x-ui-icon :name="$r['icon']" class="h-4 w-4" />
                                <span class="reaction-pick-label">{{ $r['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <button type="button" @click="primary()"
                    class="font-medium transition-colors hover:text-ink-900 dark:hover:text-white"
                    :class="active && 'text-primary-600 dark:text-primary-300'">
                    <span x-text="label"></span><span x-show="count" x-cloak> (<span x-text="count"></span>)</span>
                </button>
            </div>

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
