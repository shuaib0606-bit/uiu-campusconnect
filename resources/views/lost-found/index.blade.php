<x-app-layout title="Lost & Found — UIU CampusConnect">
    <div class="mx-auto max-w-5xl" x-data="{ open: false }">
        <div class="section-heading mb-5">
            <div>
                <h1>Lost & Found</h1>
                <p class="section-sub">{{ $items->total() }} items reported</p>
            </div>
            <button type="button" class="btn-primary" @click="open = true"><x-ui-icon name="plus" class="h-4 w-4" />Report item</button>
        </div>
        <div x-show="open" x-cloak class="surface mb-6 p-6">
            <form action="{{ route('lost-found.store') }}" method="POST" enctype="multipart/form-data" class="grid gap-3 sm:grid-cols-2">
                @csrf
                <select name="type" class="field"><option value="lost">Lost</option><option value="found">Found</option></select>
                <input name="location" placeholder="Location" class="field">
                <input name="title" placeholder="Item" required class="field sm:col-span-2">
                <textarea name="description" placeholder="Description" required class="field sm:col-span-2" rows="3"></textarea>
                <input type="file" name="photo" accept="image/*" class="field sm:col-span-2">
                <button type="submit" class="btn-primary sm:col-span-2">Publish</button>
            </form>
        </div>

        @if ($items->isEmpty())
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="inbox" class="h-6 w-6" /></span>
                <h3>Nothing reported yet</h3>
                <p>Lost something on campus, or found an item? Report it and it will show up here.</p>
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $item)
                    <div class="surface surface-hover overflow-hidden"
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
                        <div class="h-32 bg-ink-100 dark:bg-ink-800" @if($item->photo_path) style="background-image:url({{ Storage::url($item->photo_path) }});background-size:cover;background-position:center" @endif></div>
                        <div class="p-4">
                            <span class="chip {{ $item->type === 'lost' ? 'text-red-600' : 'text-emerald-600' }}">{{ ucfirst($item->type) }}</span>
                            <h3 class="mt-2 font-medium">{{ $item->title }}</h3>
                            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">{{ $item->location }}</p>
                            <template x-if="!sent">
                                <div>
                                    <button type="button" class="btn-secondary mt-3 w-full" x-show="!claim" @click="claim = true">Claim</button>
                                    <form x-show="claim" x-cloak class="mt-2" @submit.prevent="send($el)">
                                        <textarea name="message" required placeholder="Message" rows="2" class="field"></textarea>
                                        <button type="submit" class="btn-primary mt-2 w-full" :disabled="busy">Send</button>
                                    </form>
                                </div>
                            </template>
                            <p x-show="sent" x-cloak class="mt-3 text-sm text-primary-600 dark:text-primary-300">Claim sent.</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-6">{{ $items->links() }}</div>
        @endif
    </div>
</x-app-layout>
