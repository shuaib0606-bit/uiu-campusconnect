@php
    $myReaction = $post->reactions->firstWhere('user_id', auth()->id());
    $reactionCount = $post->reactions->count();
    $isSaved = $post->relationLoaded('saves') ? ($post->saves->firstWhere('user_id', auth()->id()) ? true : false) : false;
@endphp
<article
    class="surface surface-hover p-5"
    data-post-id="{{ $post->id }}"
    x-data="{
        menu: false,
        comments: false,
        hidden: false,
        saved: {{ $isSaved ? 'true' : 'false' }},
        saveBusy: false,
        commentCount: {{ $post->comments_count ?? $post->comments->count() }},
        async toggleSave() {
            if (this.saveBusy) return;
            this.saveBusy = true;
            const data = await window.ccPost('{{ route('posts.save', $post) }}');
            this.saveBusy = false;
            if (data.error) { window.ccToast('Could not save this post.', 'error'); return; }
            this.saved = data.active;
            window.ccToast(this.saved ? 'Saved.' : 'Removed from saved.', 'success');
            this.menu = false;
        },
        async addComment(el) {
            const input = el.querySelector('input[name=body]');
            if (!input.value.trim()) return;
            const data = await window.ccPost('{{ route('comments.store', $post) }}', { body: input.value });
            if (data.error) { window.ccToast('Could not post your comment.', 'error'); return; }
            this.$refs.commentsList.insertAdjacentHTML('beforeend', data.html);
            this.commentCount++;
            input.value = '';
        },
        async hidePost() {
            const data = await window.ccPost('{{ route('posts.hide', $post) }}');
            if (data.error) { window.ccToast('Could not hide this post.', 'error'); return; }
            this.hidden = true;
            window.ccToast('You will see less of this.', 'success');
        },
    }"
    x-show="!hidden"
    x-transition.opacity.duration.300ms
>
    <div class="flex items-start gap-3">
        <a href="{{ route('profile.show', $post->user) }}" class="shrink-0">
            <span class="grid h-10 w-10 place-items-center rounded-full bg-primary-100 text-primary-700 text-sm font-semibold dark:bg-primary-900/40 dark:text-primary-200">{{ $post->user->avatar_initials }}</span>
        </a>
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <a href="{{ route('profile.show', $post->user) }}" class="truncate text-[15px] font-semibold hover:underline">{{ $post->user->full_name }}</a>
                @if(in_array($post->user->role, ['admin','official']) || $post->user->is_verified)
                    <span class="chip !px-1.5 !py-0.5 text-primary-600 dark:text-primary-300"><x-ui-icon name="check" class="h-3 w-3" /></span>
                @endif
            </div>
            <p class="text-xs text-ink-500 dark:text-ink-400">{{ $post->user->roleLabel() }} · {{ $post->created_at->diffForHumans() }}</p>
        </div>

        <div class="relative">
            <button type="button" class="btn-icon" @click="menu = !menu" @click.outside="menu = false"><x-ui-icon name="dots" class="h-5 w-5" /></button>
            <div x-show="menu" x-cloak class="absolute right-0 z-20 mt-1 w-48 overflow-hidden rounded-xl border border-ink-100 dark:border-ink-800 bg-white dark:bg-[#1a1815] py-1 shadow-lifted text-sm">
                <button type="button" @click="toggleSave()" class="flex w-full items-center gap-2 px-4 py-2 hover:bg-ink-900/5 dark:hover:bg-white/5" :class="saved ? 'text-primary-600 dark:text-primary-300' : ''"><x-ui-icon name="bookmark" class="h-4 w-4" /><span x-text="saved ? 'Saved' : 'Save'"></span></button>
                <form action="{{ route('posts.share', $post) }}" method="POST"><button class="flex w-full items-center gap-2 px-4 py-2 hover:bg-ink-900/5 dark:hover:bg-white/5">@csrf<x-ui-icon name="share" class="h-4 w-4" />Share to profile</button></form>
                @if($post->user_id !== auth()->id())
                    <button type="button" @click="hidePost(); menu = false" class="flex w-full items-center gap-2 px-4 py-2 hover:bg-ink-900/5 dark:hover:bg-white/5"><x-ui-icon name="x" class="h-4 w-4" />Hide</button>
                    <form action="{{ route('posts.report', $post) }}" method="POST" x-data @submit.prevent="$el.reason.value = prompt('Reason') || ''; if($el.reason.value) $el.submit()"><input type="hidden" name="reason" x-ref="reason"><button class="flex w-full items-center gap-2 px-4 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30">@csrf<x-ui-icon name="flag" class="h-4 w-4" />Report</button></form>
                @endif
                @if($post->user_id === auth()->id() || auth()->user()->isAdmin())
                    <form action="{{ route('posts.destroy', $post) }}" method="POST"><input type="hidden" name="reason" value="Removed by author"><button class="flex w-full items-center gap-2 px-4 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30">@csrf @method('DELETE')<x-ui-icon name="x" class="h-4 w-4" />Delete</button></form>
                @endif
            </div>
        </div>
    </div>

    <p class="mt-3.5 whitespace-pre-line text-[15px] leading-relaxed">{{ $post->body }}</p>

    @if ($post->media_type === 'image' && $post->media_path)
        <img src="{{ Storage::url($post->media_path) }}" class="mt-3.5 max-h-[520px] w-full rounded-2xl object-cover" alt="">
    @elseif ($post->media_type === 'video' && $post->media_path)
        <video src="{{ Storage::url($post->media_path) }}" controls class="mt-3.5 w-full rounded-2xl"></video>
    @endif

    @if ($post->post_type === 'poll' && $post->pollOptions->isNotEmpty())
        @php $totalVotes = $post->pollOptions->sum(fn($o) => $o->votes->count()) ?: 1; @endphp
        <div class="mt-3.5 space-y-2">
            @foreach ($post->pollOptions as $option)
                @php $pct = round($option->votes->count() / $totalVotes * 100); @endphp
                <form action="{{ route('posts.vote', $post) }}" method="POST">
                    @csrf
                    <input type="hidden" name="option_id" value="{{ $option->id }}">
                    <button type="submit" class="relative w-full overflow-hidden rounded-xl border border-ink-200 dark:border-ink-800 px-4 py-2.5 text-left text-sm">
                        <span class="absolute inset-y-0 left-0 bg-primary-500/10" style="width: {{ $pct }}%"></span>
                        <span class="relative flex justify-between"><span>{{ $option->label }}</span><span class="text-ink-500">{{ $pct }}%</span></span>
                    </button>
                </form>
            @endforeach
        </div>
    @endif

    <div class="mt-4 flex items-center gap-1 border-t border-ink-100 dark:border-ink-800/70 pt-3"
         x-data="ccToggle({ url: '{{ route('posts.react', $post) }}', active: {{ $myReaction ? 'true' : 'false' }}, count: {{ $reactionCount }}, payload: { reaction: 'like' } })">
        <button type="button" @click="toggle()" class="btn-ghost !px-3" :class="active ? 'text-primary-600 dark:text-primary-300' : ''">
            <x-ui-icon name="heart" class="h-4.5 w-4.5" />
            <span x-text="count || ''"></span>
        </button>
        <button type="button" class="btn-ghost !px-3" @click="comments = !comments">
            <x-ui-icon name="comment" class="h-4.5 w-4.5" />
            <span x-text="commentCount || ''"></span>
        </button>
    </div>

    <div x-show="comments" x-cloak class="mt-3 space-y-3 border-t border-ink-100 dark:border-ink-800/70 pt-3">
        <div x-ref="commentsList" class="space-y-3">
            @foreach ($post->comments as $comment)
                @include('feed._comment', ['comment' => $comment])
            @endforeach
        </div>

        <form class="flex items-center gap-2" @submit.prevent="addComment($el)">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-primary-100 text-primary-700 text-xs font-semibold dark:bg-primary-900/40 dark:text-primary-200">{{ auth()->user()->avatar_initials }}</span>
            <input name="body" required placeholder="Write a comment" class="field py-2">
            <button type="submit" class="btn-icon"><x-ui-icon name="send" class="h-4.5 w-4.5" /></button>
        </form>
    </div>
</article>
