{{--
    Group chat.

    Shaped like a conversation: oldest at the top, newest at the bottom,
    composer pinned under it, runs of one person collapsed under a single
    name. Two details do most of the work of making it feel right.

    First, messages sit at the BOTTOM of the scroller. A short
    conversation pinned to the top of a tall box leaves a void underneath
    and reads as an empty page rather than a quiet chat.

    Second, your own messages carry no avatar and no name. You know who
    you are. Repeating it on every line is what made the header read
    backwards - "11:42 PM  Teacher  You  DS" - when the row was simply
    reversed.
--}}

@php
    $viewer = auth()->user();
    $isStaff = $group->isStaff($viewer);
    $canPost = $group->canPost($viewer);
@endphp

<div
    x-data="groupChat({
        groupId: {{ $group->id }},
        endpoint: '{{ route('groups.chat.index', $group) }}',
        canSend: {{ $canPost ? 'true' : 'false' }},
        meId: {{ $viewer->id }},
    })"
    {{-- No x-init: Alpine runs init() on its own, and naming it here as
         well booted the chat twice. destroy() likewise runs by itself
         when the panel closes. --}}
    class="flex h-[30rem] max-h-[70vh] flex-col"
>
    <div class="flex items-start justify-between gap-3 pb-3">
        <div class="min-w-0">
            <h2 class="text-[15px] font-semibold">Class chat</h2>
            <p class="section-sub mt-0.5">
                @if ($canPost)
                    Quick questions and answers for this class.
                @else
                    Only staff can write here at the moment.
                @endif
            </p>
        </div>

        {{-- Honest about which path is carrying messages. A green dot that
             says "live" while really polling would be a lie the reader
             cannot check. --}}
        <span class="flex shrink-0 items-center gap-1.5 text-xs text-ink-500 dark:text-ink-400">
            <span class="h-1.5 w-1.5 rounded-full"
                :class="mode === 'live' ? 'bg-emerald-500' : 'bg-amber-500'"></span>
            <span x-text="mode === 'live' ? 'Live' : 'Every few seconds'"></span>
        </span>
    </div>

    <div x-ref="scroller" @scroll.passive="trackScroll()"
        class="flex-1 overflow-y-auto rounded-2xl bg-ink-900/[0.03] px-3 py-2 dark:bg-white/[0.03]">

        {{-- justify-end is what keeps a short conversation resting on the
             composer instead of floating at the top of an empty box. --}}
        <div class="flex min-h-full flex-col justify-end gap-0.5">

            <template x-if="loading">
                <p class="py-8 text-center text-sm text-ink-500 dark:text-ink-400">Loading…</p>
            </template>

            <template x-if="! loading && messages.length === 0">
                <p class="py-8 text-center text-sm text-ink-500 dark:text-ink-400">
                    No messages yet. {{ $canPost ? 'Say something.' : '' }}
                </p>
            </template>

            <template x-for="(message, index) in messages" :key="message.id">
                <div :class="continues(index) ? 'mt-0.5' : 'mt-3 first:mt-0'">

                    <template x-if="startsDay(index)">
                        <p class="py-2 text-center text-[11px] font-medium uppercase tracking-wider text-ink-400"
                            x-text="message.day"></p>
                    </template>

                    {{-- Mine: no avatar, no name, bubble hard right. --}}
                    <template x-if="mine(message)">
                        <div class="group flex items-end justify-end gap-1.5">
                            <template x-if="! message.deleted">
                                <button type="button" @click="remove(message.id)"
                                    class="mb-1 text-xs text-ink-400 opacity-0 transition hover:text-red-500 group-hover:opacity-100"
                                    aria-label="Remove message">&times;</button>
                            </template>

                            <div class="max-w-[75%]">
                                <div class="rounded-2xl rounded-br-md px-3.5 py-2 text-[14px] leading-relaxed"
                                    :class="message.deleted
                                        ? 'bg-transparent px-0 italic text-ink-400'
                                        : 'bg-primary-600 text-white'">
                                    <span x-show="message.deleted">You removed this</span>
                                    <span x-show="! message.deleted" class="whitespace-pre-wrap break-words" x-text="message.body"></span>
                                </div>

                                {{-- Time under the last message of a run only. --}}
                                <template x-if="index === messages.length - 1 || ! continues(index + 1)">
                                    <p class="mt-1 pr-1 text-right text-[11px] text-ink-400" x-text="message.time"></p>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Theirs: avatar on the first of a run, name row above. --}}
                    <template x-if="! mine(message)">
                        <div class="group flex items-end gap-2">
                            <div class="w-7 shrink-0">
                                <template x-if="! continues(index) && message.avatar">
                                    <img :src="message.avatar" alt="" class="h-7 w-7 rounded-full object-cover">
                                </template>
                                <template x-if="! continues(index) && ! message.avatar">
                                    <span class="grid h-7 w-7 place-items-center rounded-full bg-ink-300 text-[11px] font-semibold text-ink-700 dark:bg-ink-700 dark:text-ink-100"
                                        x-text="message.initials"></span>
                                </template>
                            </div>

                            <div class="max-w-[75%]">
                                <template x-if="! continues(index)">
                                    <p class="mb-1 flex items-center gap-1.5 pl-1 text-[11px] text-ink-500 dark:text-ink-400">
                                        <span class="font-semibold text-ink-700 dark:text-ink-200" x-text="message.author"></span>
                                        <template x-if="message.role === 'faculty'">
                                            <span class="rounded bg-primary-500/15 px-1.5 py-px text-[10px] font-medium text-primary-700 dark:text-primary-300">Teacher</span>
                                        </template>
                                        <span x-text="message.time"></span>
                                    </p>
                                </template>

                                <div class="rounded-2xl rounded-bl-md px-3.5 py-2 text-[14px] leading-relaxed"
                                    :class="message.deleted
                                        ? 'bg-transparent px-0 italic text-ink-400'
                                        : 'bg-[var(--surface-page)] shadow-sm'">
                                    <span x-show="message.deleted">Message removed</span>
                                    <span x-show="! message.deleted" class="whitespace-pre-wrap break-words" x-text="message.body"></span>
                                </div>
                            </div>

                            @if ($isStaff)
                                <template x-if="! message.deleted">
                                    <button type="button" @click="remove(message.id)"
                                        class="mb-1 text-xs text-ink-400 opacity-0 transition hover:text-red-500 group-hover:opacity-100"
                                        aria-label="Remove message">&times;</button>
                                </template>
                            @endif
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <template x-if="error">
        <p class="pt-2 text-xs text-red-600 dark:text-red-400" x-text="error"></p>
    </template>

    <div class="pt-3">
        <template x-if="canSend">
            <form @submit.prevent="send()" class="flex items-end gap-2">
                <textarea x-ref="input" x-model="draft" rows="1" maxlength="2000"
                    @keydown.enter.prevent="if (! $event.shiftKey) send()"
                    placeholder="Write a message…"
                    class="field max-h-32 flex-1 resize-none !rounded-2xl !py-2.5"></textarea>

                <button type="submit"
                    class="btn-primary grid h-10 w-10 shrink-0 place-items-center !rounded-full p-0 disabled:opacity-40"
                    :disabled="sending || ! draft.trim()">
                    <x-ui-icon name="send" class="h-4 w-4" />
                </button>
            </form>
        </template>

        <template x-if="! canSend">
            <p class="text-center text-sm text-ink-500 dark:text-ink-400">
                Only staff can write here right now.
            </p>
        </template>
    </div>
</div>
