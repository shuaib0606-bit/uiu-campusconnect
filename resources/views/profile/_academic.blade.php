{{--
    The Academic tab: a menu of sections on the left, one section at a time
    on the right. Selecting a section is a plain link (?section=...), so it
    works without JavaScript and every section has its own address.

    Expects: $sections (already filtered for this viewer), $current,
    $p (profile), $isOwn, $profileUser.
--}}
@php
    $sectionUrl = fn ($s) => route('profile.show', [$profileUser, 'tab' => 'academic', 'section' => $s->slug()]);
    $badge = [\App\Support\Visibility::ONLY_ME => ['lock', 'Only you'], \App\Support\Visibility::MEMBERS => ['globe', 'UIU members']];
@endphp

@if ($sections->isEmpty())
    <div class="surface mx-auto w-full max-w-[880px] p-8 text-center">
        <x-ui-icon name="academic" class="mx-auto mb-3 h-8 w-8 text-ink-300" />
        @if ($isOwn)
            <h2 class="text-lg font-black">Add your academic profile</h2>
            <p class="mx-auto mt-1 max-w-md text-sm text-ink-500 dark:text-ink-400">Education, research, publications, courses - add the sections you want and choose who sees each one.</p>
            <a href="{{ route('academic.edit') }}" class="btn-primary mt-4">Add sections</a>
        @else
            <p class="text-sm font-semibold text-ink-400">{{ $profileUser->full_name }} has not shared an academic profile yet.</p>
        @endif
    </div>
@else
    <div class="grid gap-5 lg:grid-cols-[250px_minmax(0,1fr)]">
        {{-- Section menu: a column on large screens, a scrolling row on phones. --}}
        <nav class="surface self-start p-2 lg:sticky lg:top-20" aria-label="Academic profile sections">
            <div class="flex gap-1 overflow-x-auto lg:flex-col">
                @foreach ($sections as $s)
                    @php $count = $s->entryCount(); $active = $current && $current->id === $s->id; @endphp
                    <a href="{{ $sectionUrl($s) }}"
                       @if ($active) aria-current="page" @endif
                       class="flex shrink-0 items-center justify-between gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ $active ? 'bg-primary-500/10 text-primary-700 dark:text-primary-300' : 'text-ink-600 hover:bg-ink-900/[0.04] dark:text-ink-300 dark:hover:bg-white/[0.05]' }}">
                        <span class="flex items-center gap-1.5">
                            {{ $s->title }}
                            @if ($isOwn && isset($badge[$s->visibility]))<x-ui-icon :name="$badge[$s->visibility][0]" class="h-3.5 w-3.5 text-ink-400" />@endif
                        </span>
                        @if ($count)<span class="rounded-full bg-ink-900/[0.05] px-2 py-0.5 text-xs font-bold text-ink-500 dark:bg-white/[0.06] dark:text-ink-400">{{ $count }}</span>@endif
                    </a>
                @endforeach
            </div>

            @if ($isOwn)
                <a href="{{ route('academic.edit') }}" class="mt-2 flex items-center justify-center gap-1.5 rounded-xl border border-dashed border-ink-200 px-3 py-2 text-sm font-bold text-ink-600 hover:border-primary-400 hover:text-primary-600 dark:border-ink-700 dark:text-ink-300">
                    <x-ui-icon name="edit" class="h-4 w-4" /> Edit academic profile
                </a>
            @endif
        </nav>

        @if ($current)
            @php $long = mb_strlen($current->body) > 3500; @endphp
            <article class="surface min-w-0 p-6" x-data="{ open: {{ $long ? 'false' : 'true' }} }">
                <header class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b pb-3" style="border-color: var(--hairline)">
                    <h2 class="text-xl font-black">{{ $current->title }}</h2>
                    @if ($isOwn && isset($badge[$current->visibility]))
                        <span class="chip inline-flex items-center gap-1"><x-ui-icon :name="$badge[$current->visibility][0]" class="h-3.5 w-3.5" />{{ $badge[$current->visibility][1] }}</span>
                    @endif
                </header>

                <div class="relative">
                    <div class="academic-text text-[15px] leading-relaxed text-ink-700 dark:text-ink-200" :class="open ? '' : 'max-h-[36rem] overflow-hidden'">
                        {!! $current->html() !!}
                    </div>
                    @if ($long)
                        <div x-show="!open" class="pointer-events-none absolute inset-x-0 bottom-0 h-24" style="background: linear-gradient(to bottom, transparent, var(--surface-card))"></div>
                    @endif
                </div>

                @if ($long)
                    <button type="button" x-show="!open" @click="open = true" class="btn-secondary mt-4 w-full">Show all{{ $current->entryCount() ? ' '.$current->entryCount() : '' }}</button>
                @endif

                <footer class="mt-6 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-medium text-ink-400">
                    @if ($current->isFromWebsite() && ! $current->isLocked())
                        <x-ui-icon name="globe" class="h-3.5 w-3.5" />
                        <span>From the department website{{ $p?->uiu_synced_at ? ', updated '.$p->uiu_synced_at->format('j M Y') : '' }}</span>
                    @elseif ($current->isLocked())
                        <x-ui-icon name="edit" class="h-3.5 w-3.5" />
                        <span>Edited on CampusConnect{{ $current->edited_at ? ', '.$current->edited_at->format('j M Y') : '' }}</span>
                    @else
                        <x-ui-icon name="edit" class="h-3.5 w-3.5" />
                        <span>Added on CampusConnect</span>
                    @endif
                </footer>
            </article>
        @endif
    </div>
@endif
