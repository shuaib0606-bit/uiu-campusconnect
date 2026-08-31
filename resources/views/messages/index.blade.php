<x-app-layout title="Messages - UIU CampusConnect">
    <div x-data="messagesPage({ initialWith: {{ (int) request('with', 0) }} })" class="mx-auto grid max-w-6xl gap-5 lg:grid-cols-[300px_1fr_260px]" style="height: calc(100vh - 140px); min-height: 520px;">

        {{-- Thread list --}}
        <aside class="surface flex min-h-0 flex-col p-4">
            <div class="mb-3 flex items-start justify-between gap-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-300">Campus Messenger</p>
                    <h1 class="text-[18px] font-semibold tracking-tight">Messages</h1>
                </div>
                <a href="{{ route('network.index') }}" class="btn-icon" title="Find people"><x-ui-icon name="users" class="h-4.5 w-4.5" /></a>
            </div>

            <div class="relative mb-3">
                <x-ui-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                <input type="search" x-model="search" placeholder="Search conversations" class="field pl-9 py-2 text-sm">
            </div>

            <div class="mb-3 flex gap-1">
                <button type="button" @click="filter = 'all'" class="flex-1 rounded-lg px-2 py-1.5 text-xs font-medium" :class="filter === 'all' ? 'bg-primary-500 text-white' : 'bg-ink-900/[0.04] dark:bg-white/[0.06] text-ink-600 dark:text-ink-300'">All</button>
                <button type="button" @click="filter = 'unread'" class="flex-1 rounded-lg px-2 py-1.5 text-xs font-medium" :class="filter === 'unread' ? 'bg-primary-500 text-white' : 'bg-ink-900/[0.04] dark:bg-white/[0.06] text-ink-600 dark:text-ink-300'">Unread</button>
                <button type="button" @click="filter = 'recent'" class="flex-1 rounded-lg px-2 py-1.5 text-xs font-medium" :class="filter === 'recent' ? 'bg-primary-500 text-white' : 'bg-ink-900/[0.04] dark:bg-white/[0.06] text-ink-600 dark:text-ink-300'">Recent</button>
            </div>

            <div class="min-h-0 flex-1 space-y-1 overflow-y-auto">
                <template x-if="loadingThreads">
                    <p class="px-2 py-4 text-center text-sm text-ink-400">Loading conversations&hellip;</p>
                </template>
                <template x-if="!loadingThreads && !filteredThreads.length">
                    <p class="px-2 py-4 text-center text-sm text-ink-400">No conversations yet.</p>
                </template>
                <template x-for="t in filteredThreads" :key="t.user_id">
                    <button type="button" @click="openChat(t)" class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2.5 text-left transition-colors hover:bg-ink-900/[0.04] dark:hover:bg-white/[0.05]" :class="active && active.user_id === t.user_id ? 'bg-primary-500/10' : ''">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary-100 text-primary-700 text-xs font-semibold dark:bg-primary-900/40 dark:text-primary-200" x-text="t.avatar_initials"></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold" x-text="t.name"></span>
                                <span class="shrink-0 text-[11px] text-ink-400" x-text="t.time"></span>
                            </span>
                            <span class="block truncate text-xs text-ink-500 dark:text-ink-400" x-text="t.last_body || 'Tap to start chatting'"></span>
                        </span>
                        <span x-show="t.unread > 0" x-text="t.unread" class="shrink-0 rounded-full bg-primary-500 px-1.5 py-0.5 text-[10px] font-semibold text-white"></span>
                    </button>
                </template>
            </div>
        </aside>

        {{-- Active conversation --}}
        <main class="surface flex min-h-0 flex-col">
            <template x-if="!active">
                <div class="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center">
                    <span class="empty-icon"><x-ui-icon name="message" class="h-6 w-6" /></span>
                    <h2 class="text-[17px] font-semibold">Select a conversation</h2>
                    <p class="max-w-sm text-sm text-ink-500 dark:text-ink-400">Choose a person from the left to view chat history and send a message without leaving the page.</p>
                    <a href="{{ route('network.index') }}" class="btn-secondary">Find people to connect</a>
                </div>
            </template>

            <template x-if="active">
                <div class="flex min-h-0 flex-1 flex-col">
                    <header class="flex items-center gap-3 border-b border-ink-100 dark:border-ink-800/70 px-5 py-3.5">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary-100 text-primary-700 text-xs font-semibold dark:bg-primary-900/40 dark:text-primary-200" x-text="active.avatar_initials"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[15px] font-semibold" x-text="active.name"></p>
                            <p class="truncate text-xs text-ink-500 dark:text-ink-400" x-text="active.designation || active.role"></p>
                        </div>
                        <a :href="'/profile/' + active.user_id" class="btn-icon" title="Open profile"><x-ui-icon name="user" class="h-4.5 w-4.5" /></a>
                        <button type="button" @click="openChat(active)" class="btn-icon" title="Refresh"><x-ui-icon name="refresh" class="h-4.5 w-4.5" /></button>
                    </header>

                    <div x-ref="chatBody" class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-4">
                        <template x-if="active.loading">
                            <p class="py-6 text-center text-sm text-ink-400">Loading messages&hellip;</p>
                        </template>
                        <template x-if="!active.loading && !active.messages.length">
                            <div class="flex flex-col items-center gap-1 py-10 text-center">
                                <p class="text-sm font-semibold">Start the conversation</p>
                                <p class="text-xs text-ink-400">Send a friendly campus message.</p>
                            </div>
                        </template>
                        <template x-for="m in active.messages" :key="m.id">
                            <div class="flex" :class="m.mine ? 'justify-end' : 'justify-start'">
                                <div class="max-w-[75%] rounded-2xl px-4 py-2.5 text-sm" :class="m.mine ? 'bg-primary-500 text-white' : 'bg-ink-900/[0.05] dark:bg-white/[0.07] text-ink-900 dark:text-ink-100'">
                                    <p x-show="m.body" x-text="m.body" class="whitespace-pre-wrap"></p>
                                    <a x-show="m.attachment_url" :href="m.attachment_url" target="_blank" class="flex items-center gap-1.5 text-xs underline" :class="m.mine ? 'text-white' : 'text-primary-600 dark:text-primary-300'">
                                        <x-ui-icon name="paperclip" class="h-3.5 w-3.5" /> Attachment
                                    </a>
                                    <span class="mt-1 block text-[10px] opacity-70" x-text="m.time"></span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <form @submit.prevent="send" class="flex items-center gap-2 border-t border-ink-100 dark:border-ink-800/70 px-4 py-3">
                        <label class="btn-icon cursor-pointer shrink-0" title="Attach a file">
                            <x-ui-icon name="paperclip" class="h-4.5 w-4.5" />
                            <input type="file" class="hidden" @change="pickFile">
                        </label>
                        <span x-show="attachment" x-text="attachment && attachment.name" class="chip max-w-[120px] truncate"></span>
                        <input type="text" x-model="draft" placeholder="Aa" maxlength="2000" class="field flex-1 border-transparent bg-ink-900/[0.03] dark:bg-white/[0.04] py-2.5">
                        <button type="submit" :disabled="sending" class="btn-primary shrink-0 !px-4"><x-ui-icon name="send" class="h-4 w-4" /></button>
                    </form>
                </div>
            </template>
        </main>

        {{-- Details rail --}}
        <aside class="hidden lg:flex lg:min-h-0 lg:flex-col lg:gap-4">
            <div class="surface flex items-center gap-3 p-4">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-primary-100 text-primary-700 text-sm font-semibold dark:bg-primary-900/40 dark:text-primary-200">{{ auth()->user()->avatar_initials }}</span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">{{ auth()->user()->full_name }}</p>
                    <p class="truncate text-xs text-ink-500 dark:text-ink-400">{{ auth()->user()->roleLabel() }}</p>
                </div>
            </div>

            <div class="surface p-4">
                <h3 class="mb-2 text-sm font-semibold">Conversation details</h3>
                <template x-if="!active">
                    <p class="text-sm text-ink-500 dark:text-ink-400">Select a conversation to see profile shortcuts and recent activity.</p>
                </template>
                <template x-if="active">
                    <div class="space-y-2 text-sm">
                        <p class="font-medium" x-text="active.name"></p>
                        <p class="text-ink-500 dark:text-ink-400" x-text="active.designation || active.role"></p>
                        <a :href="'/profile/' + active.user_id" class="btn-secondary mt-1 inline-flex !px-3 !py-1.5 text-xs">View profile</a>
                    </div>
                </template>
            </div>

            <div class="surface p-4">
                <h3 class="mb-2 text-sm font-semibold">Messaging rules</h3>
                <ul class="space-y-1.5 text-xs text-ink-500 dark:text-ink-400">
                    <li>Students can message accepted friends.</li>
                    <li>Faculty can message any active student or colleague.</li>
                    <li>Developer admin cannot read private messages.</li>
                </ul>
            </div>
        </aside>
    </div>
</x-app-layout>
