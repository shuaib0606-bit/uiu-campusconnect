@php
    $myReaction = $post->reactions->firstWhere('user_id', auth()->id());
    $reactionCount = $post->reactions->count();
    $isSaved = $post->relationLoaded('saves') ? ($post->saves->firstWhere('user_id', auth()->id()) ? true : false) : false;

    $reactionSet = \App\Http\Controllers\ReactionController::REACTIONS;
    $isOwnPost = $post->user_id === auth()->id();

    // Property access caches the relation on the user for the rest of the request.
    $isFollowing = ! $isOwnPost && auth()->user()->following->contains('id', $post->user_id);
@endphp
<article
    class="surface surface-interactive p-5"
    {{-- Raise the whole card while its menu is open. --}}
    :class="menu && 'z-40'"
    data-post-id="{{ $post->id }}"
    x-data="{
        menu: false,
        menuUp: false,
        // Flip the dropdown above the trigger when there isn't room below it.
        openMenu(el) {
            if (!this.menu) {
                const r = el.getBoundingClientRect();
                this.menuUp = (window.innerHeight - r.bottom) < 340 && r.top > 340;
            }
            this.menu = !this.menu;
        },
        comments: false,
        hidden: false,
        // Both of these used to be browser dialogs, which freeze the page.
        reporting: false,
        confirmBlock: false,
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
            <x-user-avatar :user="$post->user" size="h-10 w-10" text="text-sm" />
        </a>
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <a href="{{ route('profile.show', $post->user) }}" class="truncate text-[15px] font-semibold hover:underline">{{ $post->user->full_name }}</a>
                @if(in_array($post->user->role, ['admin','official']) || $post->user->is_verified)
                    <span class="chip !px-1.5 !py-0.5 text-primary-600 dark:text-primary-300"><x-ui-icon name="check" class="h-3 w-3" /></span>
                @endif
            </div>
            <p class="text-xs text-ink-500 dark:text-ink-400">
                {{ $post->user->roleLabel() }} &middot;
                {{-- The timestamp is the post's permalink; nothing else linked to it. --}}
                <a href="{{ route('posts.show', $post) }}" class="transition hover:text-ink-700 hover:underline dark:hover:text-ink-200" title="{{ $post->created_at->format('M j, Y g:i A') }}">{{ $post->created_at->diffForHumans() }}</a>
            </p>
        </div>

        <div class="relative" @click.outside="menu = false; reporting = false; confirmBlock = false">
            <button type="button" class="btn-icon" @click="openMenu($el)"><x-ui-icon name="dots" class="h-5 w-5" /></button>
            <div x-show="menu" x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                :class="menuUp ? 'bottom-full mb-1 origin-bottom-right' : 'top-full mt-1 origin-top-right'"
                class="post-menu absolute right-0 z-30 w-60 rounded-xl border border-ink-100 bg-[var(--surface-raised)] py-1 text-sm shadow-lifted dark:border-ink-800">

                <button type="button" @click="toggleSave()" class="post-menu-item" :class="saved && 'text-primary-600 dark:text-primary-300'">
                    <x-ui-icon name="bookmark" class="h-4 w-4 shrink-0" />
                    <span x-text="saved ? 'Saved post' : 'Save post'"></span>
                </button>

                {{-- A group post stays inside its group, so it has no Share. --}}
                @unless ($post->group_id)
                    <form action="{{ route('posts.share', $post) }}" method="POST">
                        @csrf
                        <button class="post-menu-item"><x-ui-icon name="share" class="h-4 w-4 shrink-0" />Share post</button>
                    </form>
                @endunless

                @if (! $isOwnPost)
                    <button type="button" @click="hidePost(); menu = false" class="post-menu-item">
                        <x-ui-icon name="x" class="h-4 w-4 shrink-0" />Hide post
                    </button>

                    <div class="my-1 border-t border-ink-100 dark:border-ink-800/70"></div>

                    <button type="button" x-data="ccAction({
                            url: '{{ route('network.follow', $post->user) }}',
                            errorMessage: 'Could not update follow.',
                        })" @click="run(); menu = false" class="post-menu-item">
                        <x-ui-icon name="user-minus" class="h-4 w-4 shrink-0" />{{ $isFollowing ? 'Unfollow' : 'Follow' }} {{ \Illuminate\Support\Str::before($post->user->full_name, ' ') }}
                    </button>

                    <button type="button" x-data="ccAction({
                            url: '{{ route('network.mute', $post->user) }}',
                            payload: { days: 30 },
                            errorMessage: 'Could not mute this person.',
                        })" @click="run(); menu = false" class="post-menu-item">
                        <x-ui-icon name="speaker-off" class="h-4 w-4 shrink-0" />Mute author for 30 days
                    </button>

                    <button type="button" x-show="!confirmBlock" @click="confirmBlock = true"
                        class="post-menu-item text-red-600 hover:!bg-red-500/10 dark:text-red-400">
                        <x-ui-icon name="ban" class="h-4 w-4 shrink-0" />Block author
                    </button>

                    <div x-show="confirmBlock" x-cloak class="px-4 py-2.5">
                        <p class="text-xs text-ink-600 dark:text-ink-300">Block {{ $post->user->full_name }}? You will no longer see each other's posts.</p>
                        <div class="mt-2 flex gap-2">
                            <button type="button" x-data="ccAction({
                                    url: '{{ route('network.block', $post->user) }}',
                                    errorMessage: 'Could not block this person.',
                                })" @click="run(); menu = false; confirmBlock = false"
                                class="btn-danger !px-3 !py-1.5 text-xs">Block</button>
                            <button type="button" @click="confirmBlock = false" class="btn-ghost !px-3 !py-1.5 text-xs">Cancel</button>
                        </div>
                    </div>

                    <div class="my-1 border-t border-ink-100 dark:border-ink-800/70"></div>

                    <button type="button" x-show="!reporting" @click="reporting = true"
                        class="post-menu-item text-red-600 hover:!bg-red-500/10 dark:text-red-400">
                        <x-ui-icon name="flag" class="h-4 w-4 shrink-0" />Report post
                    </button>

                    <form x-show="reporting" x-cloak action="{{ route('posts.report', $post) }}" method="POST" class="px-4 py-2.5" x-data="{ reason: '', details: '' }">
                        @csrf
                        <span class="block text-xs font-medium text-ink-600 dark:text-ink-300">Why are you reporting this?</span>
                        <select name="reason" required x-model="reason" class="field mt-1.5 !py-1.5 text-xs">
                            <option value="">Choose a reason</option>
                            <option>Spam or advertising</option>
                            <option>Harassment or bullying</option>
                            <option>False information</option>
                            <option>Inappropriate content</option>
                            <option>Something else</option>
                        </select>
                        <x-report-details />
                        <div class="mt-2 flex gap-2">
                            <button type="submit" class="btn-primary !px-3 !py-1.5 text-xs">Send report</button>
                            <button type="button" @click="reporting = false" class="btn-ghost !px-3 !py-1.5 text-xs">Cancel</button>
                        </div>
                    </form>
                @endif

                @if ($isOwnPost || auth()->user()->isAdmin())
                    <div class="my-1 border-t border-ink-100 dark:border-ink-800/70"></div>
                    <form action="{{ route('posts.destroy', $post) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="reason" value="Removed by author">
                        <button class="post-menu-item text-red-600 hover:!bg-red-500/10 dark:text-red-400">
                            <x-ui-icon name="x" class="h-4 w-4 shrink-0" />Delete post
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @if ($post->isCode())
        {{-- A snippet is shown as code, never run and never interpreted: the
             body is escaped here exactly as it is anywhere else. --}}
        <div class="mt-3.5 overflow-hidden rounded-xl border border-ink-100 dark:border-ink-800"
            x-data="{ copied: false, copy() {
                navigator.clipboard.writeText(this.$refs.code.textContent).then(() => {
                    this.copied = true;
                    setTimeout(() => this.copied = false, 1500);
                }).catch(() => {});
            } }">
            <div class="flex items-center justify-between gap-2 bg-ink-900/[0.04] px-3.5 py-1.5 dark:bg-white/[0.06]">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">
                    {{ $post->code_language ?: 'text' }}
                </span>
                <button type="button" @click="copy()" class="btn-ghost !px-2 !py-0.5 text-[11px]">
                    <span x-show="!copied">Copy</span>
                    <span x-show="copied" x-cloak>Copied</span>
                </button>
            </div>
            <pre class="rail-scroll overflow-x-auto px-3.5 py-3 text-[13px] leading-relaxed"><code x-ref="code" class="cc-code font-mono" data-language="{{ $post->code_language ?: 'text' }}">{{ $post->body }}</code></pre>
        </div>
    @else
        {{-- Mentions::render() escapes the body before substituting mention anchors. --}}
        <p class="mt-3.5 whitespace-pre-line text-[15px] leading-relaxed">{!! \App\Support\Mentions::render($post) !!}</p>
    @endif

    {{-- Link preview. Only drawn when the fetch actually found something. --}}
    @if ($post->link && $post->link->isUseful())
        <a href="{{ $post->link->url }}" target="_blank" rel="noopener noreferrer nofollow"
            class="mt-3.5 flex gap-3 overflow-hidden rounded-xl border border-ink-100 transition hover:border-primary-300 dark:border-ink-800">
            @if ($post->link->image_url)
                <span class="hidden h-24 w-32 shrink-0 bg-ink-900/[0.06] bg-cover bg-center sm:block dark:bg-white/[0.06]"
                    style="background-image: url('{{ $post->link->image_url }}')"></span>
            @endif
            <span class="min-w-0 flex-1 px-3.5 py-3">
                <span class="block text-[11px] font-medium uppercase tracking-wide text-ink-500 dark:text-ink-400">
                    {{ $post->link->site_name ?: $post->link->host() }}
                </span>
                @if ($post->link->title)
                    <span class="mt-0.5 block truncate text-sm font-semibold">{{ $post->link->title }}</span>
                @endif
                @if ($post->link->description)
                    <span class="mt-0.5 line-clamp-2 block text-xs text-ink-600 dark:text-ink-300">{{ $post->link->description }}</span>
                @endif
            </span>
        </a>
    @endif

    {{-- Documents. Listed rather than shown: they are read elsewhere, and
         they are fetched through a route that checks who is asking. --}}
    @if ($post->documents->isNotEmpty())
        <div class="mt-3.5 space-y-1.5">
            @foreach ($post->documents as $document)
                <a href="{{ route('posts.media.download', $document) }}"
                    class="flex items-center gap-3 rounded-xl border border-ink-100 px-3.5 py-2.5 transition hover:border-primary-300 dark:border-ink-800">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-primary-500/10 text-[10px] font-bold text-primary-700 dark:text-primary-300">
                        {{ Str::limit($document->kindLabel(), 4, '') }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium">{{ $document->original_name ?: basename($document->path) }}</span>
                        <span class="block text-xs text-ink-500 dark:text-ink-400">{{ $document->sizeLabel() }}</span>
                    </span>
                    <x-ui-icon name="download" class="h-4 w-4 shrink-0 text-ink-400" />
                </a>
            @endforeach
        </div>
    @endif

    {{-- Attachments --}}
    @php
        $media = $post->media_items;
        $mediaCount = $media->count();
        // The feed shows at most four tiles; the rest are counted on the last one.
        $mediaTiles = $media->take(4);
        $mediaOverflow = $mediaCount - $mediaTiles->count();
    @endphp

    @if ($mediaCount > 0)
        <div class="mt-3.5" x-data="{
            lightbox: -1,
            media: @js($media->map(fn ($m) => ['url' => $m->url, 'type' => $m->type])->values()),
            step(delta) { this.lightbox = (this.lightbox + delta + this.media.length) % this.media.length },
        }">
            @if ($mediaCount === 1)
                @php $only = $mediaTiles->first(); @endphp
                @if ($only->type === 'video')
                    <video src="{{ $only->url }}" controls preload="metadata" class="max-h-[520px] w-full rounded-2xl bg-black"></video>
                @else
                    <button type="button" @click="lightbox = 0" class="block w-full">
                        <img src="{{ $only->url }}" loading="lazy" alt="" class="max-h-[520px] w-full rounded-2xl object-cover">
                    </button>
                @endif
            @else
                {{-- 2 = side by side, 3 = one tall plus two stacked,
                     4+ = a 2x2 with a +N badge on the last tile. --}}
                <div class="grid gap-1.5 overflow-hidden rounded-2xl {{ $mediaCount === 3 ? 'grid-cols-2 grid-rows-2' : 'grid-cols-2' }}">
                    @foreach ($mediaTiles as $i => $item)
                        @php $isOverflowTile = $mediaOverflow > 0 && $i === 3; @endphp
                        <div class="relative overflow-hidden bg-ink-900/[0.06] dark:bg-white/[0.06] {{ $mediaCount === 3 && $i === 0 ? 'row-span-2 h-full' : 'aspect-square' }}">
                            @if ($item->type === 'video' && ! $isOverflowTile)
                                <video src="{{ $item->url }}" controls preload="metadata" class="h-full w-full bg-black object-cover"></video>
                            @else
                                <button type="button" @click="lightbox = {{ $i }}" class="block h-full w-full">
                                    @if ($item->type === 'video')
                                        <video src="{{ $item->url }}" muted preload="metadata" class="h-full w-full bg-black object-cover"></video>
                                    @else
                                        <img src="{{ $item->url }}" loading="lazy" alt="" class="h-full w-full object-cover">
                                    @endif
                                    @if ($isOverflowTile)
                                        <span class="absolute inset-0 grid place-items-center bg-ink-950/55 text-2xl font-semibold text-white">+{{ $mediaOverflow }}</span>
                                    @endif
                                </button>
                            @endif

                            @if ($item->type === 'video' && ! $isOverflowTile)
                                <span class="pointer-events-none absolute left-2 top-2 inline-flex items-center gap-1 rounded-full bg-ink-950/70 px-2 py-0.5 text-[11px] font-medium text-white">
                                    <x-ui-icon name="video" class="h-3 w-3" />Video
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Lightbox --}}
            <template x-teleport="body">
                <div x-show="lightbox >= 0" x-cloak
                    class="fixed inset-0 z-[80] flex items-center justify-center bg-ink-950/90 p-4"
                    @click.self="lightbox = -1"
                    @keydown.escape.window="lightbox = -1"
                    @keydown.arrow-right.window="if (lightbox >= 0) step(1)"
                    @keydown.arrow-left.window="if (lightbox >= 0) step(-1)"
                    role="dialog" aria-modal="true" aria-label="Post attachments">

                    <button type="button" class="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                        @click="lightbox = -1" aria-label="Close">
                        <x-ui-icon name="x" class="h-5 w-5" />
                    </button>

                    <template x-if="media.length > 1">
                        <button type="button" class="absolute left-3 grid h-10 w-10 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                            @click="step(-1)" aria-label="Previous">
                            <x-ui-icon name="chevron-left" class="h-5 w-5" />
                        </button>
                    </template>
                    <template x-if="media.length > 1">
                        <button type="button" class="absolute right-3 grid h-10 w-10 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                            @click="step(1)" aria-label="Next">
                            <x-ui-icon name="chevron-right" class="h-5 w-5" />
                        </button>
                    </template>

                    <template x-if="lightbox >= 0 && media[lightbox] && media[lightbox].type === 'image'">
                        <img :src="media[lightbox].url" alt="" class="max-h-[85vh] max-w-[90vw] rounded-xl object-contain">
                    </template>
                    <template x-if="lightbox >= 0 && media[lightbox] && media[lightbox].type === 'video'">
                        <video :src="media[lightbox].url" controls autoplay class="max-h-[85vh] max-w-[90vw] rounded-xl bg-black"></video>
                    </template>

                    <template x-if="media.length > 1">
                        <span class="absolute bottom-5 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-white tabular-nums"
                            x-text="(lightbox + 1) + ' / ' + media.length"></span>
                    </template>
                </div>
            </template>
        </div>
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

    <div class="mt-4 flex items-center gap-1 border-t border-ink-100 pt-3 dark:border-ink-800/70">
        <div class="relative" x-data="ccReactions({
                url: '{{ route('posts.react', $post) }}',
                active: {{ $myReaction ? 'true' : 'false' }},
                reaction: {{ $myReaction ? "'".$myReaction->reaction."'" : 'null' }},
                count: {{ $reactionCount }},
                labels: @js(collect($reactionSet)->map(fn ($r) => $r['label'])),
                icons: @js(collect($reactionSet)->map(fn ($r) => $r['icon'])),
            })"
            @mouseenter="show()" @mouseleave="hide()">

            {{-- Hover flyout --}}
            <div x-show="open" x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-1 scale-90"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-120"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-90"
                class="absolute bottom-full left-0 z-30 origin-bottom-left pb-2">
                <div class="flex items-center gap-0.5 rounded-full border border-ink-100 bg-[var(--surface-raised)] p-1 shadow-lifted dark:border-ink-800">
                    @foreach ($reactionSet as $key => $r)
                        <button type="button" @click="send('{{ $key }}')"
                            class="reaction-pick" :class="reaction === '{{ $key }}' && 'is-picked'"
                            title="{{ $r['label'] }}" aria-label="{{ $r['label'] }}">
                            <x-ui-icon :name="$r['icon']" class="h-5 w-5" />
                            <span class="reaction-pick-label">{{ $r['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <button type="button" @click="primary()" class="reaction-btn" :class="active && 'is-on'">
                <template x-if="reaction === 'love'"><span class="contents"><x-ui-icon name="heart" class="reaction-icon h-4.5 w-4.5" /></span></template>
                <template x-if="reaction === 'helpful'"><span class="contents"><x-ui-icon name="lightbulb" class="reaction-icon no-fill h-4.5 w-4.5" /></span></template>
                <template x-if="reaction === 'question'"><span class="contents"><x-ui-icon name="question-mark-circle" class="reaction-icon no-fill h-4.5 w-4.5" /></span></template>
                <template x-if="reaction !== 'love' && reaction !== 'helpful' && reaction !== 'question'"><span class="contents"><x-ui-icon name="thumb-up" class="reaction-icon h-4.5 w-4.5" /></span></template>
                <span x-text="label"></span>
                <span x-show="count" x-cloak x-text="count" class="tabular-nums"></span>
            </button>
        </div>

        <button type="button" class="reaction-btn" :class="comments && 'is-on'" @click="comments = !comments">
            <x-ui-icon name="comment" class="reaction-icon no-fill h-4.5 w-4.5" />
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
            <x-user-avatar :user="auth()->user()" size="h-8 w-8" text="text-xs" />

            {{-- Mention autocomplete --}}
            <div class="relative flex-1" x-data="ccMentionField({ searchUrl: '{{ route('users.search') }}' })" @click.outside="close()">
                <div x-show="mOpen" x-cloak
                    class="absolute bottom-full left-0 z-30 mb-1 w-72 max-w-full overflow-hidden rounded-xl border border-ink-100 bg-[var(--surface-raised)] py-1 shadow-lifted dark:border-ink-800">
                    <template x-for="(item, i) in mItems" :key="item.id">
                        <button type="button" @click="choose(item)" @mouseenter="mIndex = i"
                            class="flex w-full items-center gap-2.5 px-3 py-2 text-left"
                            :class="i === mIndex ? 'bg-primary-500/10' : ''">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-[10px] font-semibold bg-cover bg-center" :class="item.avatar_url ? '' : 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-200'" :style="item.avatar_url ? `background-image:url('${item.avatar_url}')` : ''" x-text="item.avatar_url ? '' : item.avatar_initials"></span>
                            <span class="min-w-0">
                                <span class="block truncate text-[13px] font-medium" x-text="item.full_name"></span>
                                <span class="block truncate text-[11px] text-ink-500 dark:text-ink-400" x-text="'@' + item.handle"></span>
                            </span>
                        </button>
                    </template>
                </div>

                <input x-ref="field" name="body" required placeholder="Write a comment (use @ to mention)"
                    autocomplete="off" class="field w-full py-2"
                    @input="detect()" @keydown="onKeydown($event)" @blur="setTimeout(() => close(), 150)">
            </div>

            <button type="submit" class="btn-icon"><x-ui-icon name="send" class="h-4.5 w-4.5" /></button>
        </form>
    </div>
</article>
