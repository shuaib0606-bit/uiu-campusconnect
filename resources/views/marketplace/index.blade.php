<x-app-layout title="Marketplace — UIU CampusConnect">
    <div class="mx-auto max-w-5xl" x-data="{ open: false }">
        <div class="section-heading mb-5">
            <div>
                <h1>Marketplace</h1>
                <p class="section-sub">{{ $items->total() }} items listed by students</p>
            </div>
            <button type="button" class="btn-primary" @click="open = true"><x-ui-icon name="plus" class="h-4 w-4" />List item</button>
        </div>
        <div x-show="open" x-cloak class="surface mb-6 p-6">
            <form action="{{ route('marketplace.store') }}" method="POST" enctype="multipart/form-data" class="grid gap-3 sm:grid-cols-2">
                @csrf
                <input name="title" placeholder="Title" required class="field sm:col-span-2">
                <textarea name="description" placeholder="Description" required class="field sm:col-span-2" rows="3"></textarea>
                <input type="number" step="0.01" name="price" placeholder="Price (BDT)" required class="field">
                <input name="category" placeholder="Category" required class="field">
                <select name="condition" class="field"><option value="new">New</option><option value="used" selected>Used</option></select>
                <input type="file" name="photo" accept="image/*" class="field">
                <button type="submit" class="btn-primary sm:col-span-2">Publish</button>
            </form>
        </div>

        @if ($items->isEmpty())
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="shopping-bag" class="h-6 w-6" /></span>
                <h3>No listings yet</h3>
                <p>Books, gadgets, and anything else students want to sell will show up here.</p>
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($items as $item)
                    <div class="surface surface-hover overflow-hidden">
                        <div class="h-32 bg-ink-100 dark:bg-ink-800" @if($item->photo_path) style="background-image:url({{ Storage::url($item->photo_path) }});background-size:cover;background-position:center" @endif></div>
                        <div class="p-4">
                            <h3 class="truncate font-medium">{{ $item->title }}</h3>
                            <p class="text-sm font-semibold text-primary-600 dark:text-primary-300">৳{{ number_format($item->price) }}</p>
                            <p class="text-xs text-ink-400">{{ $item->seller->full_name }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-6">{{ $items->links() }}</div>
        @endif
    </div>
</x-app-layout>
