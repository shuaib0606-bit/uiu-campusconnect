@php
    $isLost = $item->type === 'lost';
    $isOpen = $item->status === 'open';
    $accentText = $isLost ? 'text-red-600 dark:text-red-300' : 'text-emerald-600 dark:text-emerald-300';
    $accentSoft = $isLost ? 'bg-red-500/10' : 'bg-emerald-500/10';
@endphp
<x-app-layout title="{{ $item->title }} - Lost &amp; Found">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('lost-found.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-ink-500 transition hover:text-ink-900 dark:text-ink-400 dark:hover:text-white">
            <x-ui-icon name="chevron-right" class="h-4 w-4 rotate-180" />
            Back to Lost &amp; Found
        </a>

        <article class="surface mb-5 overflow-hidden">
            <div class="relative h-64 w-full bg-cover bg-center {{ $isLost ? 'bg-gradient-to-br from-red-500/10 to-red-700/20' : 'bg-gradient-to-br from-emerald-500/10 to-emerald-700/20' }} sm:h-80" @if ($item->photo_path) style="background-image:url('{{ Storage::url($item->photo_path) }}')" @endif>
                @unless ($item->photo_path)
                    <span class="absolute inset-0 grid place-items-center {{ $isLost ? 'text-red-500/40 dark:text-red-300/30' : 'text-emerald-500/40 dark:text-emerald-300/30' }}">
                        <x-ui-icon name="inbox" class="h-16 w-16" />
                    </span>
                @endunless
                <span class="absolute left-0 top-4 rounded-r-full py-1 pl-4 pr-4 text-xs font-bold uppercase tracking-wide text-white shadow-sm {{ $isLost ? 'bg-red-500' : 'bg-emerald-500' }}">{{ $item->type }}</span>
                @unless ($isOpen)
                    <span class="absolute right-3 top-3 rounded-full bg-ink-900/85 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white backdrop-blur">Resolved</span>
                @endunless
            </div>

            <div class="p-6 sm:p-7">
                <h1 class="text-[22px] font-semibold leading-snug tracking-tight sm:text-[26px]">{{ $item->title }}</h1>

                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    <div class="surface-flat rounded-xl px-3.5 py-2.5">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-ink-500 dark:text-ink-400">{{ $isLost ? 'Lost near' : 'Found at' }}</p>
                        <p class="mt-0.5 truncate text-sm font-medium">{{ $item->location ?: 'Not specified' }}</p>
                    </div>
                    <div class="surface-flat rounded-xl px-3.5 py-2.5">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-ink-500 dark:text-ink-400">Date</p>
                        <p class="mt-0.5 truncate text-sm font-medium">{{ $item->event_date ? $item->event_date->format('M j, Y') : 'Not specified' }}</p>
                    </div>
                    <div class="surface-flat rounded-xl px-3.5 py-2.5">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-ink-500 dark:text-ink-400">Status</p>
                        <p class="mt-0.5 truncate text-sm font-medium {{ $isOpen ? $accentText : '' }}">{{ $isOpen ? 'Still open' : 'Resolved' }}</p>
                    </div>
                </div>

                <h2 class="mt-5 text-[15px] font-semibold">Description</h2>
                <div class="mt-1.5 text-[15px] leading-relaxed text-ink-700 dark:text-ink-200">
                    {!! nl2br(e($item->description)) !!}
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-ink-100 pt-4 dark:border-ink-800/70">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full {{ $accentSoft }} text-xs font-bold {{ $accentText }}">{{ $item->user->avatar_initials }}</span>
                    <div class="min-w-0">
                        <a href="{{ route('profile.show', $item->user) }}" class="truncate text-sm font-semibold hover:underline">{{ $item->user->full_name }}</a>
                        <p class="section-sub truncate">Posted {{ $item->created_at->format('M j, Y g:i A') }} &middot; {{ $item->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            </div>
        </article>

        @auth
            @if ($isOwner)
                <section class="surface p-6">
                    <div class="section-heading mb-4">
                        <div>
                            <h2 class="text-[15px] font-semibold">Claims on your post</h2>
                            <p class="section-sub">People who believe this item is theirs, or who can return it.</p>
                        </div>
                        @if ($isOpen)
                            <form method="POST" action="{{ route('lost-found.resolve', $item) }}">
                                @csrf
                                <button type="submit" class="btn-secondary whitespace-nowrap">
                                    <x-ui-icon name="check-circle" class="h-4 w-4" />
                                    Mark Resolved
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="space-y-3">
                        @forelse ($claims as $claim)
                            <div class="surface-flat rounded-xl p-3.5">
                                <div class="flex items-center gap-3">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-primary-100 text-[10px] font-bold text-primary-700 dark:bg-primary-900/40 dark:text-primary-200">{{ $claim->user->avatar_initials }}</span>
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('profile.show', $claim->user) }}" class="truncate text-sm font-semibold hover:underline">{{ $claim->user->full_name }}</a>
                                        <p class="section-sub">{{ $claim->created_at->diffForHumans() }}</p>
                                    </div>
                                    <a href="{{ route('messages.index', ['with' => $claim->user_id]) }}" class="btn-secondary shrink-0 !px-3 !py-1.5 text-xs">Reply</a>
                                </div>
                                <p class="mt-2 text-sm text-ink-600 dark:text-ink-300">{{ $claim->message }}</p>
                            </div>
                        @empty
                            <div class="empty-state">
                                <span class="empty-icon"><x-ui-icon name="inbox" class="h-6 w-6" /></span>
                                <h3>No claims yet</h3>
                                <p>When someone recognises this item, their message will appear here.</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            @elseif ($isOpen)
                <section class="surface p-6"
                     x-data="{
                        claim: false,
                        sent: {{ $hasClaimed ? 'true' : 'false' }},
                        busy: false,
                        async send(el) {
                            const input = el.querySelector('textarea[name=message]');
                            if (!input.value.trim() || this.busy) return;
                            this.busy = true;
                            const data = await window.ccPost('{{ route('lost-found.claim', $item) }}', { message: input.value });
                            this.busy = false;
                            if (data.error) { window.ccToast('Could not send your claim.', 'error'); return; }
                            this.sent = true;
                            window.ccToast('Claim submitted to the poster.', 'success');
                        },
                     }">
                    <h2 class="text-[15px] font-semibold">{{ $isLost ? 'Have you seen this item?' : 'Is this yours?' }}</h2>
                    <p class="section-sub mt-1">
                        {{ $isLost
                            ? 'Message the owner with where you saw it - they will get your note straight away.'
                            : 'Describe something only the owner would know so the finder can verify your claim.' }}
                    </p>

                    <template x-if="!sent">
                        <div class="mt-4">
                            <button type="button" class="btn-primary w-full sm:w-auto" x-show="!claim" @click="claim = true">
                                <x-ui-icon name="send" class="h-4 w-4" />
                                Claim / Contact
                            </button>
                            <form x-show="claim" x-cloak @submit.prevent="send($el)">
                                <textarea name="message" required rows="3" class="field" placeholder="Explain why this item may be yours, or how you can return it"></textarea>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="submit" class="btn-primary" :disabled="busy">Send Claim</button>
                                    <button type="button" class="btn-ghost" @click="claim = false">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </template>

                    <div x-show="sent" x-cloak class="mt-4 flex items-center gap-2 rounded-xl bg-primary-500/10 px-3.5 py-2.5 text-sm text-primary-700 dark:text-primary-300">
                        <x-ui-icon name="check-circle" class="h-4 w-4 shrink-0" />
                        <span>Your claim has been sent to {{ $item->user->full_name }}.</span>
                    </div>

                    <a href="{{ route('messages.index', ['with' => $item->user_id]) }}" class="btn-secondary mt-3 inline-flex">
                        <x-ui-icon name="message" class="h-4 w-4" />
                        Message directly
                    </a>
                </section>
            @else
                <section class="surface p-6">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                            <x-ui-icon name="check-circle" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-[15px] font-semibold">This one is settled</h2>
                            <p class="section-sub">The poster marked this item as resolved, so no new claims are needed.</p>
                        </div>
                    </div>
                </section>
            @endif
        @endauth
    </div>
</x-app-layout>
