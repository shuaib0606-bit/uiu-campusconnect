@php
    // Set when the composer is placed on a group wall. A group post has one
    // audience - the membership - so the visibility selector is replaced by a
    // label, and a hidden field tells PostController where it belongs.
    $group = $group ?? null;

    $me = auth()->user();
    $firstName = \Illuminate\Support\Str::before($me->full_name, ' ');

    // The three values posts.visibility accepts.
    $visibilityOptions = [
        'uiu' => 'Everyone at UIU',
        'department' => 'My department',
        'connections' => 'Connections only',
    ];
@endphp

{{-- Post composer. --}}
<div class="surface p-4 sm:p-5"
    x-data="ccComposer({
        maxFiles: {{ \App\Http\Controllers\PostController::MAX_MEDIA }},
        maxFileSize: {{ \App\Http\Controllers\PostController::MAX_FILE_KB }} * 1024,
        maxBody: {{ \App\Http\Controllers\PostController::MAX_BODY }},
        maxPollOptions: 6,
        searchUrl: '{{ route('users.search') }}',
    })"
    x-on:keydown.escape.window="if (open) requestClose()"
>
    @if ($errors->any())
        <p role="alert" class="mb-3 rounded-xl bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">
            {{ $errors->first() }}
        </p>
    @endif

    {{-- Resting state --}}
    <div class="cc-composer-live flex items-center gap-3">
        <x-user-avatar :user="$me" size="h-10 w-10" text="text-sm" />
        <button type="button" x-ref="trigger" @click="openComposer()"
            class="flex-1 rounded-full px-4 py-2.5 text-left text-[15px] text-ink-500 transition hover:bg-ink-900/[0.06] dark:text-ink-400 dark:hover:bg-white/[0.08]"
            style="background: var(--surface-sunken);">
            What's on your mind, {{ $firstName }}?
        </button>
    </div>

    {{-- Fallback composer for when JavaScript is unavailable. --}}
    <noscript>
        <style>.cc-composer-live { display: none !important; }</style>
        <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
            @csrf
            @if ($group)<input type="hidden" name="group_id" value="{{ $group->id }}">@endif
            <textarea name="body" rows="3" required maxlength="{{ \App\Http\Controllers\PostController::MAX_BODY }}" placeholder="{{ $group ? 'Share something with '.$group->name : 'Share something with campus' }}" class="field resize-none"></textarea>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <label class="text-xs text-ink-500 dark:text-ink-400">
                    Photos or videos
                    <input type="file" name="media[]" accept="image/*,video/*,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,.txt,.md,.zip" multiple class="mt-1 block text-xs">
                </label>
                @if ($group)
                    <span class="chip">Group members only</span>
                @else
                    <select name="visibility" class="field !w-auto !py-1.5 text-xs" aria-label="Who can see this post">
                        @foreach ($visibilityOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                @endif
                <button type="submit" class="btn-primary">Post</button>
            </div>
        </form>
    </noscript>

    <div class="cc-composer-live mt-3 grid grid-cols-2 gap-1 border-t border-ink-100 pt-2 sm:grid-cols-4 dark:border-ink-800/70">
        <button type="button" @click="openComposer('media')"
            class="flex items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-ink-600 transition hover:bg-ink-900/[0.05] dark:text-ink-300 dark:hover:bg-white/[0.06]">
            <x-ui-icon name="image" class="h-5 w-5 text-emerald-500" />
            Photo / video
        </button>
        <button type="button" @click="openComposer('poll')"
            class="flex items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-ink-600 transition hover:bg-ink-900/[0.05] dark:text-ink-300 dark:hover:bg-white/[0.06]">
            <x-ui-icon name="chart-bar" class="h-5 w-5 text-primary-500" />
            Poll
        </button>
        <button type="button" @click="openComposer('media')"
            class="flex items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-ink-600 transition hover:bg-ink-900/[0.05] dark:text-ink-300 dark:hover:bg-white/[0.06]">
            <x-ui-icon name="paperclip" class="h-5 w-5 text-amber-500" />
            Document
        </button>
        <button type="button" @click="openComposer('code')"
            class="flex items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-ink-600 transition hover:bg-ink-900/[0.05] dark:text-ink-300 dark:hover:bg-white/[0.06]">
            <x-ui-icon name="file-text" class="h-5 w-5 text-sky-500" />
            Code
        </button>
    </div>

    {{-- Dialog --}}
    <div x-show="open" x-cloak style="display: none;"
        class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto p-3 sm:p-6"
        role="dialog" aria-modal="true" aria-labelledby="cc-composer-title"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="if ($event.target === $el) dragging = false"
        @drop.prevent="onDrop($event)">

        <div x-show="open" x-transition.opacity.duration.200ms
            class="fixed inset-0 bg-ink-950/60 backdrop-blur-sm" @click="requestClose()" aria-hidden="true"></div>

        <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data"
            x-ref="form" @submit="onSubmit($event)"
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-3 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            class="surface relative z-10 my-6 w-full max-w-xl overflow-hidden">
            @csrf
            @if ($group)<input type="hidden" name="group_id" value="{{ $group->id }}">@endif

            {{-- Multi-select file input. sr-only rather than hidden, to keep it in the tab order. --}}
            <input type="file" id="cc-composer-files" x-ref="input" name="media[]" multiple
                accept="image/*,video/*,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,.txt,.md,.zip" class="sr-only"
                @change="addFiles($event.target.files)">

            <header class="relative border-b border-ink-100 px-5 py-3.5 dark:border-ink-800/70">
                <h2 id="cc-composer-title" class="text-center text-base font-semibold">Create post</h2>
                <button type="button" class="btn-icon absolute right-3 top-2" @click="requestClose()" aria-label="Close composer">
                    <x-ui-icon name="x" class="h-5 w-5" />
                </button>
            </header>

            <div class="max-h-[70vh] overflow-y-auto px-5 py-4">
                {{-- author + audience --}}
                <div class="flex items-center gap-3">
                    <x-user-avatar :user="$me" size="h-10 w-10" text="text-sm" />
                    <div class="min-w-0">
                        <p class="truncate text-[15px] font-semibold">{{ $me->full_name }}</p>
                        <div class="relative mt-0.5 inline-flex items-center gap-1.5 rounded-full py-1 pl-2.5 pr-6 text-xs font-medium text-ink-600 dark:text-ink-300"
                            style="background: var(--surface-sunken); border: 1px solid var(--hairline);">
                            @if ($group)
                                <x-ui-icon name="users" class="h-3.5 w-3.5" />
                                <span class="pr-2">{{ $group->name }} members</span>
                            @else
                                <template x-if="visibility === 'uiu'"><span class="contents"><x-ui-icon name="globe" class="h-3.5 w-3.5" /></span></template>
                                <template x-if="visibility === 'department'"><span class="contents"><x-ui-icon name="building" class="h-3.5 w-3.5" /></span></template>
                                <template x-if="visibility === 'connections'"><span class="contents"><x-ui-icon name="lock" class="h-3.5 w-3.5" /></span></template>
                                <select name="visibility" x-model="visibility" aria-label="Who can see this post"
                                    class="appearance-none bg-transparent text-xs font-medium focus:outline-none">
                                    @foreach ($visibilityOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-ui-icon name="chevron-down" class="pointer-events-none absolute right-2 h-3 w-3" />
                            @endif
                        </div>
                    </div>
                </div>

                {{-- body + mention picker --}}
                <div class="relative mt-3">
                    <textarea name="body" x-ref="body" x-model="body" rows="4"
                        maxlength="{{ \App\Http\Controllers\PostController::MAX_BODY }}"
                        @input="onBodyInput()"
                        @click="onBodyInput()"
                        @keyup.arrow-left="onBodyInput()"
                        @keyup.arrow-right="onBodyInput()"
                        @keydown="onBodyKeydown($event)"
                        @blur="setTimeout(() => closeMentions(), 120)"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-controls="cc-composer-mentions"
                        :aria-expanded="mOpen ? 'true' : 'false'"
                        placeholder="What's on your mind, {{ $firstName }}?"
                        class="w-full resize-none border-0 bg-transparent p-0 text-lg leading-relaxed text-ink-900 placeholder:text-ink-400 focus:outline-none focus:ring-0 dark:text-ink-50"></textarea>

                    <div id="cc-composer-mentions" x-show="mOpen" x-cloak role="listbox"
                        class="absolute inset-x-0 top-full z-20 mt-1 max-h-64 overflow-y-auto rounded-xl py-1 shadow-lifted"
                        style="background: var(--surface-raised); border: 1px solid var(--hairline);">
                        <template x-for="(person, i) in mItems" :key="person.id">
                            <button type="button" role="option" :aria-selected="i === mIndex ? 'true' : 'false'"
                                @mousedown.prevent="chooseMention(person)"
                                @mouseenter="mIndex = i"
                                class="flex w-full items-center gap-2.5 px-3 py-2 text-left transition"
                                :class="i === mIndex ? 'bg-primary-500/10' : ''">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-xs font-semibold bg-cover bg-center" :class="person.avatar_url ? '' : 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-200'" :style="person.avatar_url ? `background-image:url('${person.avatar_url}')` : ''" x-text="person.avatar_url ? '' : person.avatar_initials"></span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium" x-text="person.full_name"></span>
                                    <span class="block truncate text-xs text-ink-500 dark:text-ink-400">
                                        <span x-text="person.display"></span> · <span x-text="person.role_label"></span>
                                    </span>
                                </span>
                            </button>
                        </template>
                    </div>

                    <p class="mt-1 text-right text-xs tabular-nums" x-show="showCounter" x-cloak
                        :class="charsLeft < 0 ? 'text-red-600 dark:text-red-400' : 'text-ink-500 dark:text-ink-400'"
                        x-text="charsLeft + ' characters left'"></p>
                </div>

                {{-- Code mode. The language is the whole switch: its presence
                     is what makes the post a snippet rather than prose. --}}
                <div x-show="code" x-cloak class="mt-3 flex flex-wrap items-center gap-2 rounded-2xl p-3" style="background: var(--surface-sunken);">
                    <span class="text-xs font-medium text-ink-600 dark:text-ink-300">Language</span>
                    <select name="code_language" x-model="codeLanguage" :disabled="!code"
                        class="field !w-auto !py-1.5 text-xs">
                        @foreach (\App\Http\Controllers\PostController::CODE_LANGUAGES as $language)
                            <option value="{{ $language }}">{{ $language === 'text' ? 'Plain text' : $language }}</option>
                        @endforeach
                    </select>
                    <span class="text-xs text-ink-500 dark:text-ink-400">Paste the snippet into the box above.</span>
                    <button type="button" class="btn-ghost ml-auto !px-2 !py-1 text-xs" @click="toggleCode(false)">Remove code</button>
                </div>

                {{-- poll builder --}}
                <div x-show="poll" x-cloak class="mt-3 rounded-2xl p-3" style="background: var(--surface-sunken);">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">Poll options</p>
                        <button type="button" class="btn-ghost !px-2 !py-1 text-xs" @click="togglePoll()">Remove poll</button>
                    </div>
                    <div class="space-y-2">
                        <template x-for="(opt, i) in options" :key="i">
                            <div class="flex items-center gap-2">
                                <input :name="'poll_options[' + i + ']'" x-model="options[i]" maxlength="255"
                                    class="field py-2 text-sm" :placeholder="'Option ' + (i + 1)"
                                    :aria-label="'Poll option ' + (i + 1)">
                                <button type="button" class="btn-icon h-8 w-8 shrink-0"
                                    x-show="options.length > 2" @click="removeOption(i)"
                                    :aria-label="'Remove option ' + (i + 1)">
                                    <x-ui-icon name="x" class="h-4 w-4" />
                                </button>
                            </div>
                        </template>
                    </div>
                    <button type="button" class="btn-ghost mt-2 !px-2 !py-1 text-xs" @click="addOption()"
                        :disabled="options.length >= maxPollOptions">
                        <x-ui-icon name="plus" class="h-3.5 w-3.5" />
                        <span x-text="options.length >= maxPollOptions ? 'Maximum options reached' : 'Add option'"></span>
                    </button>
                </div>

                {{-- attachment previews --}}
                <div x-show="items.length > 0" x-cloak class="mt-3">
                    <div class="grid gap-2" :class="gridClass">
                        <template x-for="(item, i) in items" :key="item.key">
                            <div class="relative overflow-hidden rounded-xl bg-ink-900/[0.06] dark:bg-white/[0.06]" :class="tileClass(i)">
                                <template x-if="item.kind === 'image'">
                                    <img :src="item.url" :alt="item.name" class="h-full w-full object-cover">
                                </template>
                                <template x-if="item.kind === 'video'">
                                    <video :src="item.url" muted playsinline preload="metadata" class="h-full w-full object-cover"></video>
                                </template>

                                {{-- A document has nothing to look at, so the
                                     tile shows what it is instead. --}}
                                <template x-if="item.kind === 'file'">
                                    <span class="flex h-full w-full flex-col items-center justify-center gap-1 p-2 text-center">
                                        <x-ui-icon name="paperclip" class="h-6 w-6 text-ink-500 dark:text-ink-400" />
                                        <span class="line-clamp-2 break-all text-[11px] font-medium" x-text="item.name"></span>
                                        <span class="text-[10px] text-ink-500 dark:text-ink-400" x-text="humanSize(item.size)"></span>
                                    </span>
                                </template>

                                <template x-if="item.kind === 'video'">
                                    <span class="pointer-events-none absolute left-2 top-2 inline-flex items-center gap-1 rounded-full bg-ink-950/70 px-2 py-0.5 text-[11px] font-medium text-white">
                                        <x-ui-icon name="video" class="h-3 w-3" />Video
                                    </span>
                                </template>

                                {{-- The feed shows four tiles and counts the rest. --}}
                                <template x-if="i === 3 && hiddenInFeed > 0">
                                    <span class="pointer-events-none absolute inset-0 grid place-items-center bg-ink-950/55 text-xl font-semibold text-white">
                                        +<span x-text="hiddenInFeed"></span>
                                    </span>
                                </template>

                                <button type="button" @click="remove(i)"
                                    class="absolute right-1.5 top-1.5 grid h-7 w-7 place-items-center rounded-full bg-ink-950/70 text-white transition hover:bg-ink-950"
                                    :aria-label="'Remove ' + item.name">
                                    <x-ui-icon name="x" class="h-4 w-4" />
                                </button>

                                <div class="absolute bottom-1.5 left-1.5 flex items-center gap-1">
                                    <button type="button" @click="move(i, -1)" :disabled="i === 0"
                                        class="grid h-7 w-7 place-items-center rounded-full bg-ink-950/70 text-white transition hover:bg-ink-950 disabled:opacity-30"
                                        :aria-label="'Move ' + item.name + ' earlier'">
                                        <x-ui-icon name="arrow-left" class="h-3.5 w-3.5" />
                                    </button>
                                    <button type="button" @click="move(i, 1)" :disabled="i === items.length - 1"
                                        class="grid h-7 w-7 place-items-center rounded-full bg-ink-950/70 text-white transition hover:bg-ink-950 disabled:opacity-30"
                                        :aria-label="'Move ' + item.name + ' later'">
                                        <x-ui-icon name="arrow-right" class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <p class="mt-2 flex items-center justify-between gap-3 text-xs text-ink-500 dark:text-ink-400">
                        <span><span x-text="items.length"></span> of {{ \App\Http\Controllers\PostController::MAX_MEDIA }} attached</span>
                        <button type="button" class="btn-ghost !px-2 !py-1 text-xs" @click="pick()"
                            :disabled="items.length >= maxFiles">
                            <x-ui-icon name="plus" class="h-3.5 w-3.5" />Add more
                        </button>
                    </p>
                    <p x-show="hiddenInFeed > 0" x-cloak class="text-xs text-ink-500 dark:text-ink-400">
                        The feed shows the first four; the last tile carries a +<span x-text="hiddenInFeed"></span> badge and opens the rest.
                    </p>
                </div>

                {{-- Empty drop target --}}
                <button type="button" x-show="items.length === 0" x-cloak @click="pick()"
                    class="mt-3 flex w-full flex-col items-center justify-center gap-1.5 rounded-2xl border-2 border-dashed py-7 text-sm transition"
                    :class="dragging ? 'border-primary-400 bg-primary-500/10 text-primary-600 dark:text-primary-300' : 'border-ink-200 text-ink-500 dark:border-ink-700 dark:text-ink-400'">
                    <x-ui-icon name="image" class="h-6 w-6" />
                    <span class="font-medium">Add photos or videos</span>
                    <span class="text-xs">or drag and drop them here</span>
                </button>

                <p x-show="error" x-cloak x-text="error" role="alert"
                    class="mt-3 rounded-xl bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400"></p>
            </div>

            <footer class="border-t border-ink-100 px-5 py-3.5 dark:border-ink-800/70">
                <div class="flex items-center justify-between gap-3 rounded-xl px-1">
                    <span class="text-sm font-medium text-ink-600 dark:text-ink-300">Add to your post</span>
                    <div class="flex items-center gap-1">
                        <label for="cc-composer-files" class="btn-icon cursor-pointer" title="Photo or video">
                            <x-ui-icon name="image" class="h-5 w-5 text-emerald-500" />
                            <span class="sr-only">Add photos or videos</span>
                        </label>
                        <button type="button" class="btn-icon" @click="togglePoll()"
                            :class="poll ? 'text-primary-600 dark:text-primary-300' : ''" title="Poll">
                            <x-ui-icon name="chart-bar" class="h-5 w-5" />
                            <span class="sr-only">Add a poll</span>
                        </button>
                        <button type="button" class="btn-icon" @click="toggleCode()"
                            :class="code ? 'text-primary-600 dark:text-primary-300' : ''" title="Code">
                            <x-ui-icon name="file-text" class="h-5 w-5" />
                            <span class="sr-only">Post code</span>
                        </button>
                    </div>
                </div>

                <p class="mt-2 px-1 text-xs text-ink-500 dark:text-ink-400">
                    Type {{ '@' }} to mention someone.
                </p>

                <button type="submit" class="btn-primary mt-3 w-full" :disabled="!canPost">
                    <template x-if="busy"><span class="contents"><x-ui-icon name="refresh" class="h-4 w-4 animate-spin" /></span></template>
                    <span x-text="busy ? 'Posting' : 'Post'"></span>
                </button>
            </footer>

            {{-- Drag overlay --}}
            <div x-show="dragging" x-cloak
                class="pointer-events-none absolute inset-0 z-20 grid place-items-center rounded-2xl border-2 border-dashed border-primary-400 bg-primary-500/15 backdrop-blur-[2px]">
                <span class="rounded-full bg-[var(--surface-raised)] px-4 py-2 text-sm font-medium shadow-lifted">Drop to attach</span>
            </div>
        </form>
    </div>
</div>
