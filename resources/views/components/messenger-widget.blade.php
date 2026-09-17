@auth
<div x-data="ccMessenger()" class="hidden lg:block">
    <button type="button" @click="toggleList()"
        class="fixed bottom-6 right-6 z-40 grid h-14 w-14 place-items-center rounded-full bg-primary-500 text-white shadow-lifted hover:bg-primary-600 hover:scale-110 active:scale-90 transition-all duration-200 ease-out">
        <x-ui-icon name="message" class="h-6 w-6" />
        <span x-show="unreadTotal > 0" x-cloak class="absolute -right-1 -top-1 flex h-5 min-w-[20px]">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
            <span class="relative grid h-5 min-w-[20px] place-items-center rounded-full bg-red-500 px-1 text-[11px] font-semibold text-white" x-text="unreadTotal > 9 ? '9+' : unreadTotal"></span>
        </span>
    </button>

    <div x-show="listOpen" x-cloak @click.outside="listOpen = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        class="fixed bottom-24 right-6 z-40 flex h-[440px] w-80 flex-col overflow-hidden rounded-2xl border border-ink-100 dark:border-ink-800/70 bg-[var(--surface-raised)] shadow-lifted">
        <div class="flex items-center justify-between border-b border-ink-100 dark:border-ink-800/70 px-4 py-3">
            <h3 class="text-[15px] font-semibold">Messages</h3>
            <button type="button" @click="listOpen = false" class="btn-icon !h-8 !w-8"><x-ui-icon name="x" class="h-4 w-4" /></button>
        </div>
        <div class="flex-1 overflow-y-auto">
            <template x-if="loadingThreads">
                <div class="p-4 text-sm text-ink-500 dark:text-ink-400">Loading...</div>
            </template>
            <template x-if="!loadingThreads && threads.length === 0">
                <div class="flex flex-col items-center gap-2 px-6 py-12 text-center">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-300"><x-ui-icon name="message" class="h-5 w-5" /></span>
                    <p class="text-sm text-ink-500 dark:text-ink-400">No conversations yet.</p>
                </div>
            </template>
            <template x-for="t in threads" :key="t.user_id">
                <button type="button" @click="openChat(t)" class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-ink-900/[0.04] dark:hover:bg-white/[0.05]">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-xs font-semibold bg-cover bg-center" :class="t.avatar_url ? '' : 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-200'" :style="t.avatar_url ? `background-image:url('${t.avatar_url}')` : ''" x-text="t.avatar_url ? '' : t.avatar_initials"></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-2">
                            <span class="truncate text-[13.5px] font-semibold" x-text="t.name"></span>
                            <span class="shrink-0 text-[11px] text-ink-400" x-text="t.time"></span>
                        </span>
                        <span class="block truncate text-xs text-ink-500 dark:text-ink-400" x-text="t.last_body || 'Sent an attachment'"></span>
                    </span>
                    <span x-show="t.unread > 0" x-cloak class="h-2 w-2 shrink-0 rounded-full bg-primary-500"></span>
                </button>
            </template>
        </div>
    </div>

    <div class="fixed bottom-6 z-40 flex items-end justify-end gap-3" style="right: 96px; max-width: calc(100vw - 120px)">
        <template x-for="w in windows" :key="w.user_id">
            <div x-data="{}" x-effect="w.messages.length; $nextTick(() => { if ($refs.body) $refs.body.scrollTop = $refs.body.scrollHeight })"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                class="flex h-[420px] w-72 flex-col overflow-hidden rounded-2xl border border-ink-100 dark:border-ink-800/70 bg-[var(--surface-raised)] shadow-lifted">
                <div class="flex items-center gap-2 border-b border-ink-100 dark:border-ink-800/70 px-3 py-2.5">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-xs font-semibold bg-cover bg-center" :class="w.avatar_url ? '' : 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-200'" :style="w.avatar_url ? `background-image:url('${w.avatar_url}')` : ''" x-text="w.avatar_url ? '' : w.avatar_initials"></span>
                    <span class="min-w-0 flex-1 truncate text-[13.5px] font-semibold" x-text="w.name"></span>
                    <button type="button" @click="closeChat(w.user_id)" class="btn-icon !h-7 !w-7"><x-ui-icon name="x" class="h-3.5 w-3.5" /></button>
                </div>
                <div class="flex-1 space-y-2 overflow-y-auto px-3 py-3" x-ref="body">
                    <template x-if="w.loading">
                        <div class="text-center text-xs text-ink-500 dark:text-ink-400">Loading...</div>
                    </template>
                    <template x-if="!w.loading && w.messages.length === 0">
                        <div class="text-center text-xs text-ink-500 dark:text-ink-400">Say hello!</div>
                    </template>
                    <template x-for="m in w.messages" :key="m.id">
                        <div class="flex" :class="m.mine ? 'justify-end' : 'justify-start'">
                            <span class="max-w-[80%] rounded-2xl px-3 py-2 text-[13.5px] leading-snug"
                                :class="m.mine ? 'bg-primary-500 text-white rounded-br-sm' : 'bg-ink-900/[0.05] dark:bg-white/[0.07] rounded-bl-sm'"
                                x-text="m.body"></span>
                        </div>
                    </template>
                </div>
                <form class="flex items-center gap-2 border-t border-ink-100 dark:border-ink-800/70 p-2.5" @submit.prevent="send(w)">
                    <input x-model="w.draft" placeholder="Message" class="field !py-2 flex-1 text-[13.5px]">
                    <button type="submit" class="btn-icon !h-8 !w-8"><x-ui-icon name="send" class="h-4 w-4" /></button>
                </form>
            </div>
        </template>
    </div>
</div>
@endauth
