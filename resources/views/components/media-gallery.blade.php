@props([
    'item',
    'type',
    'canEdit' => false,
    'height' => 'h-64 sm:h-80',
])

@php
    $shots = $item->gallery();
    $cover = $shots->first()['url'] ?? $item->coverUrl();
    $limit = \App\Support\Gallery::LIMIT;
@endphp

<div x-data="{
        shots: @js($shots->values()),
        i: 0,
        open: false,
        editing: false,
        step(n) { this.i = (this.i + n + this.shots.length) % this.shots.length; },
    }"
     @keydown.escape.window="open = false"
     @keydown.arrow-left.window="if (open) step(-1)"
     @keydown.arrow-right.window="if (open) step(1)">

    <div class="relative {{ $height }} w-full overflow-hidden bg-ink-100 bg-cover bg-center dark:bg-ink-800"
         style="background-image:url('{{ $cover }}')"
         x-bind:style="`background-image:url('${shots.length ? shots[i].url : @js($cover)}')`">

        @if ($shots->isNotEmpty())
            <button type="button" class="absolute inset-0 cursor-zoom-in" @click="open = true" aria-label="View picture"></button>
        @endif

        <template x-if="shots.length > 1">
            <div>
                <button type="button" @click="step(-1)"
                        class="absolute left-3 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-ink-900/55 text-white backdrop-blur transition hover:bg-ink-900/75">
                    <x-ui-icon name="chevron-right" class="h-4 w-4 rotate-180" />
                </button>
                <button type="button" @click="step(1)"
                        class="absolute right-3 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-ink-900/55 text-white backdrop-blur transition hover:bg-ink-900/75">
                    <x-ui-icon name="chevron-right" class="h-4 w-4" />
                </button>
                <span class="absolute bottom-3 right-3 rounded-full bg-ink-900/65 px-2.5 py-1 text-[11px] font-semibold text-white backdrop-blur"
                      x-text="`${i + 1} / ${shots.length}`"></span>
            </div>
        </template>

        {{ $slot }}
    </div>

    <template x-if="shots.length > 1">
        <div class="flex gap-2 overflow-x-auto border-b border-ink-100 px-4 py-3 dark:border-ink-800/70">
            <template x-for="(shot, n) in shots" :key="shot.id ?? n">
                <button type="button" @click="i = n"
                        class="h-14 w-20 shrink-0 rounded-lg bg-cover bg-center ring-2 transition"
                        :class="i === n ? 'ring-primary-500' : 'ring-transparent opacity-70 hover:opacity-100'"
                        :style="`background-image:url('${shot.url}')`"></button>
            </template>
        </div>
    </template>

    @if ($canEdit)
        <div class="border-b border-ink-100 px-4 py-3 dark:border-ink-800/70">
            <button type="button" class="btn-ghost text-sm" @click="editing = !editing">
                <x-ui-icon name="image" class="h-4 w-4" />
                <span x-text="editing ? 'Done' : 'Edit pictures'"></span>
            </button>

            <div x-show="editing" x-cloak class="mt-3 space-y-3">
                <form method="POST" action="{{ route('media.store', ['type' => $type, 'id' => $item->getKey()]) }}" enctype="multipart/form-data"
                      class="flex flex-wrap items-center gap-2">
                    @csrf
                    <input type="file" name="images[]" accept="image/*" multiple required
                           class="field flex-1 !py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primary-500 file:px-3 file:py-1.5 file:text-white">
                    <button type="submit" class="btn-primary">Upload</button>
                </form>

                <p class="section-sub">Up to {{ $limit }} pictures. The first one is the cover.</p>

                @if ($shots->isNotEmpty())
                    <div class="flex flex-wrap gap-3">
                        @foreach ($shots as $index => $shot)
                            <div class="w-28">
                                <div class="h-20 w-28 rounded-lg bg-cover bg-center" style="background-image:url('{{ $shot['url'] }}')"></div>
                                @if ($shot['id'])
                                    <div class="mt-1 flex gap-1">
                                        @if ($index > 0)
                                            <form method="POST" action="{{ route('media.cover', $shot['id']) }}" class="flex-1">
                                                @csrf
                                                <button type="submit" class="btn-ghost w-full !px-2 !py-1 text-[11px]">Cover</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('media.destroy', $shot['id']) }}" class="flex-1">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-ghost w-full !px-2 !py-1 text-[11px] text-red-600 dark:text-red-300">Remove</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    <template x-if="open && shots.length">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/90 p-4" @click.self="open = false">
            <img :src="shots[i].url" alt="" class="max-h-[85vh] max-w-full rounded-xl object-contain">

            <button type="button" @click="open = false"
                    class="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/10 text-white backdrop-blur transition hover:bg-white/20">
                <x-ui-icon name="x" class="h-5 w-5" />
            </button>

            <template x-if="shots.length > 1">
                <div>
                    <button type="button" @click.stop="step(-1)"
                            class="absolute left-4 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white backdrop-blur transition hover:bg-white/20">
                        <x-ui-icon name="chevron-right" class="h-5 w-5 rotate-180" />
                    </button>
                    <button type="button" @click.stop="step(1)"
                            class="absolute right-4 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white backdrop-blur transition hover:bg-white/20">
                        <x-ui-icon name="chevron-right" class="h-5 w-5" />
                    </button>
                </div>
            </template>
        </div>
    </template>
</div>
