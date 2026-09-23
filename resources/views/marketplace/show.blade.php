@php
    $isOwner = auth()->check() && $item->user_id === auth()->id();
    $isAvailable = $item->status === 'available';
    $categoryLabel = $categories[$item->category] ?? ucfirst(str_replace('_', ' ', $item->category));
    $priceLabel = $item->price !== null ? '৳'.number_format($item->price) : 'Negotiable';
@endphp
<x-app-layout title="{{ $item->title }} - Marketplace">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('marketplace.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-ink-500 transition hover:text-ink-900 dark:text-ink-400 dark:hover:text-white">
            <x-ui-icon name="chevron-right" class="h-4 w-4 rotate-180" />
            Back to Marketplace
        </a>

        <article class="surface mb-5 overflow-hidden">
            <x-media-gallery :item="$item" type="marketplace" :can-edit="$canEditMedia">
                @unless ($isAvailable)
                    <span class="pointer-events-none absolute right-3 top-3 rounded-full bg-ink-900/85 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white backdrop-blur">Sold</span>
                @endunless
            </x-media-gallery>

            <div class="p-6 sm:p-7">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="chip"><x-ui-icon name="tag" class="h-3.5 w-3.5" />{{ $categoryLabel }}</span>
                            <span class="chip">{{ ucfirst($item->condition) }}</span>
                        </div>
                        <h1 class="mt-2.5 text-[22px] font-semibold leading-snug tracking-tight sm:text-[26px]">{{ $item->title }}</h1>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-2xl font-bold text-primary-600 dark:text-primary-300">{{ $priceLabel }}</p>
                        <p class="section-sub">{{ $item->created_at->diffForHumans() }}</p>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1.5 border-y border-ink-100 py-3 text-xs text-ink-500 dark:border-ink-800/70 dark:text-ink-400">
                    <span class="inline-flex items-center gap-1"><x-ui-icon name="map-pin" class="h-3.5 w-3.5" />{{ $item->location ?: 'Campus' }}</span>
                    <span class="inline-flex items-center gap-1"><x-ui-icon name="clock" class="h-3.5 w-3.5" />Listed {{ $item->created_at->format('M j, Y') }}</span>
                    <span class="inline-flex items-center gap-1">
                        <x-ui-icon :name="$isAvailable ? 'check-circle' : 'x'" class="h-3.5 w-3.5" />
                        {{ $isAvailable ? 'Available' : 'No longer available' }}
                    </span>
                </div>

                <h2 class="mt-5 text-[15px] font-semibold">Description</h2>
                <div class="mt-1.5 text-[15px] leading-relaxed text-ink-700 dark:text-ink-200">
                    {!! nl2br(e($item->description)) !!}
                </div>
            </div>
        </article>

        <section class="surface mb-5 p-6">
            <h2 class="mb-3 text-[15px] font-semibold">Seller</h2>
            <div class="flex flex-wrap items-center gap-3">
                <x-user-avatar :user="$item->seller" size="h-11 w-11" text="text-sm" />
                <div class="min-w-0 flex-1">
                    <a href="{{ route('profile.show', $item->seller) }}" class="truncate text-sm font-semibold hover:underline">{{ $item->seller->full_name }}</a>
                    <p class="section-sub truncate">{{ $item->seller->department ?: ucfirst($item->seller->role) }}</p>
                </div>

                @auth
                    @if ($isOwner)
                        @if ($isAvailable)
                            <form method="POST" action="{{ route('marketplace.sold', $item) }}" class="shrink-0">
                                @csrf
                                <button type="submit" class="btn-secondary">
                                    <x-ui-icon name="check" class="h-4 w-4" />
                                    Mark Sold
                                </button>
                            </form>
                        @else
                            <span class="chip shrink-0">Marked sold</span>
                        @endif
                    @else
                        <a href="{{ route('messages.index', ['with' => $item->seller->public_id]) }}" class="btn-primary shrink-0">
                            <x-ui-icon name="message" class="h-4 w-4" />
                            Message Seller
                        </a>
                    @endif
                @endauth
            </div>

            @unless ($isAvailable)
                <p class="mt-4 rounded-xl bg-ink-900/[0.04] px-3.5 py-2.5 text-sm text-ink-600 dark:bg-white/[0.06] dark:text-ink-300">
                    This listing has been marked as sold. It may still be worth messaging the seller to ask about similar items.
                </p>
            @endunless
        </section>

        @if ($related->isNotEmpty())
            <section class="surface p-6">
                <h2 class="mb-3 text-[15px] font-semibold">Similar listings</h2>
                <div class="space-y-2">
                    @foreach ($related as $other)
                        <a href="{{ route('marketplace.show', $other) }}" class="surface-flat surface-hover flex items-center gap-3 rounded-xl p-3.5">
                            <span class="h-11 w-11 shrink-0 rounded-lg bg-cover bg-center" style="background-image:url('{{ $other->coverUrl() }}')"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $other->title }}</p>
                                <p class="section-sub truncate">{{ $other->seller->full_name }}</p>
                            </div>
                            <span class="shrink-0 text-sm font-semibold text-primary-600 dark:text-primary-300">{{ $other->price !== null ? '৳'.number_format($other->price) : 'Negotiable' }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
