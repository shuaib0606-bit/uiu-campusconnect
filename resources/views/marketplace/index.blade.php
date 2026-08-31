<x-app-layout title="Marketplace - UIU CampusConnect">
    <div class="mx-auto max-w-5xl" x-data="{ open: false }">
        <div class="section-heading mb-5">
            <div>
                <h1>Marketplace</h1>
                <p class="section-sub">{{ $total }} {{ \Illuminate\Support\Str::plural('item', $total) }} available right now &middot; books, gadgets, roommates, and tuition posts.</p>
            </div>
            @auth
                <button type="button" class="btn-primary" @click="open = !open"><x-ui-icon name="plus" class="h-4 w-4" />Post Item</button>
            @endauth
        </div>

        @auth
            <div x-show="open" x-cloak class="surface mb-6 p-6">
                <h2 class="mb-3 text-[15px] font-semibold">Post a marketplace item</h2>
                <form action="{{ route('marketplace.store') }}" method="POST" enctype="multipart/form-data" class="grid gap-3 sm:grid-cols-2">
                    @csrf
                    <label class="block"><span class="mb-1 block text-xs font-medium">Category</span>
                        <select name="category" class="field">
                            @foreach ($categories as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block"><span class="mb-1 block text-xs font-medium">Title</span><input name="title" required class="field"></label>
                    <textarea name="description" placeholder="Describe condition, details, or offer" required class="field sm:col-span-2" rows="3"></textarea>
                    <label class="block"><span class="mb-1 block text-xs font-medium">Price</span><input type="number" step="0.01" name="price" placeholder="Leave blank if negotiable" class="field"></label>
                    <label class="block"><span class="mb-1 block text-xs font-medium">Location</span><input name="location" placeholder="Campus / area" class="field"></label>
                    <label class="block sm:col-span-2"><span class="mb-1 block text-xs font-medium">Photo</span><input type="file" name="photo" accept="image/*" class="field !py-1.5"></label>
                    <button type="submit" class="btn-primary sm:col-span-2">Publish Listing</button>
                </form>
            </div>
        @endauth

        <div class="surface mb-5 p-3">
            <form method="GET" class="relative mb-3">
                @if ($category !== '')<input type="hidden" name="category" value="{{ $category }}">@endif
                <x-ui-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-ink-400" />
                <input type="search" name="q" value="{{ $q }}" placeholder="Search books, laptops, roommates, tuition..." class="field pl-10">
            </form>
            <div class="flex flex-wrap gap-1.5">
                <a href="{{ route('marketplace.index', array_filter(['q' => $q])) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $category === '' ? 'bg-primary-500 text-white' : 'text-ink-600 hover:bg-ink-900/[0.04] dark:text-ink-300 dark:hover:bg-white/[0.05]' }}">All</a>
                @foreach ($categories as $key => $label)
                    <a href="{{ route('marketplace.index', array_filter(['category' => $key, 'q' => $q])) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $category === $key ? 'bg-primary-500 text-white' : 'text-ink-600 hover:bg-ink-900/[0.04] dark:text-ink-300 dark:hover:bg-white/[0.05]' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        @if ($items->isEmpty())
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="shopping-bag" class="h-6 w-6" /></span>
                <h3>{{ $q !== '' || $category !== '' ? 'No matching listings' : 'No listings yet' }}</h3>
                <p>{{ $q !== '' || $category !== '' ? 'Try a different search term or category.' : 'Books, gadgets, and anything else students want to sell will show up here.' }}</p>
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $item)
                    <article class="surface surface-hover flex flex-col overflow-hidden">
                        <a href="{{ route('marketplace.show', $item) }}" aria-label="{{ $item->title }}" class="relative block h-40 w-full bg-cover bg-center bg-gradient-to-br from-primary-500/15 to-primary-700/25" @if($item->photo_path) style="background-image:url({{ Storage::url($item->photo_path) }})" @endif>
                            @unless ($item->photo_path)
                                <span class="absolute inset-0 grid place-items-center text-primary-500/40 dark:text-primary-300/30"><x-ui-icon name="shopping-bag" class="h-10 w-10" /></span>
                            @endunless
                            <span class="absolute left-2.5 top-2.5 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-semibold text-ink-700 shadow-sm backdrop-blur dark:bg-ink-900/80 dark:text-ink-200">{{ $categories[$item->category] ?? $item->category }}</span>
                            <span class="absolute bottom-2.5 left-2.5 rounded-full bg-primary-500 px-3 py-1 text-sm font-bold text-white shadow-sm">{{ $item->price !== null ? '৳'.number_format($item->price) : 'Negotiable' }}</span>
                        </a>
                        <div class="flex flex-1 flex-col p-4">
                            <h3 class="truncate text-[15px] font-semibold"><a href="{{ route('marketplace.show', $item) }}" class="transition hover:text-primary-600 dark:hover:text-primary-300">{{ $item->title }}</a></h3>
                            <p class="mt-1 flex-1 text-sm text-ink-600 dark:text-ink-300">{{ \Illuminate\Support\Str::limit($item->description, 100) }}</p>
                            <a href="{{ route('marketplace.show', $item) }}" class="mt-1.5 inline-flex items-center gap-1 text-sm font-medium text-primary-600 dark:text-primary-300">View details<x-ui-icon name="chevron-right" class="h-4 w-4" /></a>

                            <div class="mt-3 flex items-center gap-2 border-t border-ink-100 pt-3 text-xs text-ink-500 dark:border-ink-800/70 dark:text-ink-400">
                                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-primary-100 text-[10px] font-bold text-primary-700 dark:bg-primary-900/40 dark:text-primary-200">{{ $item->seller->avatar_initials }}</span>
                                <span class="truncate font-medium">{{ $item->seller->full_name }}</span>
                                <span class="ml-auto flex shrink-0 items-center gap-1"><x-ui-icon name="map-pin" class="h-3.5 w-3.5" />{{ $item->location ?: 'Campus' }}</span>
                            </div>

                            @auth
                                @if ($item->user_id !== auth()->id())
                                    <a href="{{ route('messages.index', ['with' => $item->user_id]) }}" class="btn-secondary mt-3 w-full">Message Seller</a>
                                @else
                                    <form method="POST" action="{{ route('marketplace.sold', $item) }}" class="mt-3">
                                        @csrf
                                        <button type="submit" class="btn-secondary w-full">Mark Sold</button>
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
