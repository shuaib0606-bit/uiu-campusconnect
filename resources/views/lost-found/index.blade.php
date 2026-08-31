<x-app-layout title="Lost &amp; Found - UIU CampusConnect">
    <div class="mx-auto max-w-5xl" x-data="{ open: false }">
        <div class="section-heading mb-5">
            <div>
                <h1>Lost &amp; Found</h1>
                <p class="section-sub">Reunite misplaced items with their owners &mdash; post with a photo, location, and date.</p>
            </div>
            @auth
                <button type="button" class="btn-primary" @click="open = !open"><x-ui-icon name="plus" class="h-4 w-4" />Report Item</button>
            @endauth
        </div>

        <div class="mb-5 grid grid-cols-3 gap-3">
            <a href="{{ route('lost-found.index', array_filter(['type' => 'lost', 'q' => $q])) }}" class="stat-card items-start {{ $type === 'lost' ? 'ring-2 ring-red-400' : '' }}">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-red-500/10 text-red-600 dark:text-red-300"><x-ui-icon name="alert-triangle" class="h-4.5 w-4.5" /></span>
                <span class="stat-value mt-1">{{ $stats['lost'] }}</span>
                <span class="stat-label">Lost items</span>
            </a>
            <a href="{{ route('lost-found.index', array_filter(['type' => 'found', 'q' => $q])) }}" class="stat-card items-start {{ $type === 'found' ? 'ring-2 ring-emerald-400' : '' }}">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-300"><x-ui-icon name="inbox" class="h-4.5 w-4.5" /></span>
                <span class="stat-value mt-1">{{ $stats['found'] }}</span>
                <span class="stat-label">Found items</span>
            </a>
            <div class="stat-card items-start">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-ink-900/[0.05] text-ink-500 dark:bg-white/[0.07] dark:text-ink-300"><x-ui-icon name="check-circle" class="h-4.5 w-4.5" /></span>
                <span class="stat-value mt-1">{{ $stats['resolved'] }}</span>
                <span class="stat-label">Reunited</span>
            </div>
        </div>

        @auth
            <div x-show="open" x-cloak class="surface mb-6 p-6">
                <h2 class="mb-3 text-[15px] font-semibold">Report a lost or found item</h2>
                <form action="{{ route('lost-found.store') }}" method="POST" enctype="multipart/form-data" class="grid gap-3 sm:grid-cols-2">
                    @csrf
                    <label class="block"><span class="mb-1 block text-xs font-medium">Type</span>
                        <select name="type" class="field">
                            <option value="lost">Lost item</option>
                            <option value="found">Found item</option>
                        </select>
                    </label>
                    <label class="block"><span class="mb-1 block text-xs font-medium">Title</span><input name="title" required class="field"></label>
                    <textarea name="description" placeholder="Describe item and identifying details" required class="field sm:col-span-2" rows="3"></textarea>
                    <label class="block"><span class="mb-1 block text-xs font-medium">Location</span><input name="location" required class="field"></label>
                    <label class="block"><span class="mb-1 block text-xs font-medium">Date</span><input type="date" name="event_date" class="field"></label>
                    <label class="block sm:col-span-2"><span class="mb-1 block text-xs font-medium">Photo</span><input type="file" name="photo" accept="image/*" class="field !py-1.5"></label>
                    <button type="submit" class="btn-primary sm:col-span-2">Publish Item</button>
                </form>
            </div>
        @endauth

        <div class="surface mb-5 p-3">
            <form method="GET" class="relative mb-3">
                @if ($type !== '')<input type="hidden" name="type" value="{{ $type }}">@endif
                <x-ui-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-ink-400" />
                <input type="search" name="q" value="{{ $q }}" placeholder="Search by item, location, or description" class="field pl-10">
            </form>
            <div class="flex flex-wrap gap-1.5">
                <a href="{{ route('lost-found.index', array_filter(['q' => $q])) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $type === '' ? 'bg-primary-500 text-white' : 'text-ink-600 hover:bg-ink-900/[0.04] dark:text-ink-300 dark:hover:bg-white/[0.05]' }}">All</a>
                <a href="{{ route('lost-found.index', array_filter(['type' => 'lost', 'q' => $q])) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $type === 'lost' ? 'bg-red-500 text-white' : 'text-ink-600 hover:bg-ink-900/[0.04] dark:text-ink-300 dark:hover:bg-white/[0.05]' }}">Lost</a>
                <a href="{{ route('lost-found.index', array_filter(['type' => 'found', 'q' => $q])) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $type === 'found' ? 'bg-emerald-500 text-white' : 'text-ink-600 hover:bg-ink-900/[0.04] dark:text-ink-300 dark:hover:bg-white/[0.05]' }}">Found</a>
            </div>
        </div>

        @if ($items->isEmpty())
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="inbox" class="h-6 w-6" /></span>
                <h3>{{ $q !== '' || $type !== '' ? 'Nothing matches' : 'Nothing reported yet' }}</h3>
                <p>{{ $q !== '' || $type !== '' ? 'Try a different search term or switch tabs.' : 'Lost something on campus, or found an item? Report it and it will show up here.' }}</p>
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2">
                @foreach ($items as $item)
                    <article class="surface surface-hover overflow-hidden"
                         x-data="{
                            claim: false,
                            sent: false,
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
                        <a href="{{ route('lost-found.show', $item) }}" aria-label="{{ $item->title }}" class="relative block h-40 w-full bg-cover bg-center {{ $item->type === 'lost' ? 'bg-gradient-to-br from-red-500/10 to-red-700/20' : 'bg-gradient-to-br from-emerald-500/10 to-emerald-700/20' }}" @if($item->photo_path) style="background-image:url('{{ Storage::url($item->photo_path) }}')" @endif>
                            @unless ($item->photo_path)
                                <span class="absolute inset-0 grid place-items-center {{ $item->type === 'lost' ? 'text-red-500/40 dark:text-red-300/30' : 'text-emerald-500/40 dark:text-emerald-300/30' }}"><x-ui-icon name="inbox" class="h-10 w-10" /></span>
                            @endunless
                            <span class="absolute left-0 top-3 rounded-r-full py-1 pl-3 pr-3.5 text-xs font-bold uppercase tracking-wide text-white shadow-sm {{ $item->type === 'lost' ? 'bg-red-500' : 'bg-emerald-500' }}">{{ $item->type }}</span>
                        </a>
                        <div class="p-5">
                            <h3 class="text-[15px] font-semibold"><a href="{{ route('lost-found.show', $item) }}" class="transition hover:text-primary-600 dark:hover:text-primary-300">{{ $item->title }}</a></h3>
                            <p class="mt-1 text-sm text-ink-600 dark:text-ink-300">{{ \Illuminate\Support\Str::limit($item->description, 130) }}</p>
                            <a href="{{ route('lost-found.show', $item) }}" class="mt-1.5 inline-flex items-center gap-1 text-sm font-medium text-primary-600 dark:text-primary-300">View details<x-ui-icon name="chevron-right" class="h-4 w-4" /></a>

                            <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-ink-500 dark:text-ink-400">
                                <span class="inline-flex items-center gap-1"><x-ui-icon name="map-pin" class="h-3.5 w-3.5" />{{ $item->location }}</span>
                                @if ($item->event_date)<span class="inline-flex items-center gap-1"><x-ui-icon name="calendar" class="h-3.5 w-3.5" />{{ $item->event_date->format('M j, Y') }}</span>@endif
                                <span class="inline-flex items-center gap-1"><x-ui-icon name="clock" class="h-3.5 w-3.5" />{{ $item->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="mt-2 text-xs text-ink-400">Posted by {{ $item->user->full_name }}</p>

                            @auth
                                @if ($item->user_id !== auth()->id())
                                    <template x-if="!sent">
                                        <div class="mt-3">
                                            <button type="button" class="btn-secondary w-full" x-show="!claim" @click="claim = true">Claim / Contact</button>
                                            <form x-show="claim" x-cloak class="mt-2" @submit.prevent="send($el)">
                                                <textarea name="message" required placeholder="Explain why this item may be yours or how you can return it" rows="2" class="field"></textarea>
                                                <button type="submit" class="btn-primary mt-2 w-full" :disabled="busy">Send Claim</button>
                                            </form>
                                        </div>
                                    </template>
                                    <p x-show="sent" x-cloak class="mt-3 text-sm text-primary-600 dark:text-primary-300">Claim sent.</p>
                                @else
                                    <form method="POST" action="{{ route('lost-found.resolve', $item) }}" class="mt-3">
                                        @csrf
                                        <button type="submit" class="btn-secondary w-full">Mark Resolved</button>
                                    </form>
                                @endif
                            @endauth
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-6">{{ $items->links() }}</div>
        @endif
    </div>
</x-app-layout>
