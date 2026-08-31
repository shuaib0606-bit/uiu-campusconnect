@php
    $hasAdvanced = $department !== '' || $batch !== '' || $semester !== '';
    $activeFilterCount = collect([$department, $batch, $semester])->filter(fn ($v) => $v !== '')->count() + ($urgent ? 1 : 0);
@endphp
<x-app-layout title="Notices - UIU CampusConnect">
    <div class="mx-auto max-w-4xl" x-data="{ filtersOpen: {{ $hasAdvanced ? 'true' : 'false' }} }">

        <div class="surface mb-5 p-4">
            <form method="GET" class="flex flex-col gap-2 sm:flex-row">
                @if ($urgent)<input type="hidden" name="urgent" value="1">@endif
                @if ($department !== '')<input type="hidden" name="department" value="{{ $department }}">@endif
                @if ($batch !== '')<input type="hidden" name="batch" value="{{ $batch }}">@endif
                @if ($semester !== '')<input type="hidden" name="semester" value="{{ $semester }}">@endif
                <div class="group relative flex-1">
                    <x-ui-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-ink-400 transition-all duration-300 ease-out group-focus-within:scale-110 group-focus-within:text-primary-500" />
                    <input type="search" name="q" value="{{ $q }}" placeholder="Search notices by title or details" class="field pl-10 transition-all duration-300 ease-out focus:shadow-[0_10px_28px_-18px_var(--color-primary-500)]">
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn-secondary flex-1 whitespace-nowrap sm:flex-initial" x-bind:class="filtersOpen && '!bg-primary-500/10 !text-primary-700 dark:!text-primary-300'" @click="filtersOpen = !filtersOpen">
                        <x-ui-icon name="sliders" class="h-4 w-4 transition-transform duration-500 ease-out" x-bind:class="filtersOpen && 'rotate-180'" />
                        <span>Filters</span>
                        @if ($activeFilterCount > 0)
                            <span class="rounded-full bg-primary-500 px-1.5 text-xs text-white">{{ $activeFilterCount }}</span>
                        @endif
                    </button>
                    <button type="submit" class="btn-primary flex-1 whitespace-nowrap sm:flex-initial">Search</button>
                </div>
            </form>

            <div class="cc-stagger mt-3 flex flex-wrap gap-1.5 border-t border-ink-100 pt-3 dark:border-ink-800/70">
                <a href="{{ route('notices.index', array_filter(['q' => $q])) }}" class="filter-pill {{ $type === '' ? 'is-active' : '' }}">All types</a>
                @foreach ($types as $key => $label)
                    <a href="{{ route('notices.index', array_filter(['type' => $key, 'q' => $q])) }}" class="filter-pill {{ $type === $key ? 'is-active' : '' }}">{{ $label }}</a>
                @endforeach
            </div>

            <div x-show="filtersOpen" x-cloak
                 x-transition:enter="transition duration-300 ease-out"
                 x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition duration-200 ease-in"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 -translate-y-2 scale-[0.98]"
                 class="mt-3 grid origin-top gap-2 border-t border-ink-100 pt-3 dark:border-ink-800/70 sm:grid-cols-2 lg:grid-cols-4">
                <form method="GET" class="contents">
                    @if ($type !== '')<input type="hidden" name="type" value="{{ $type }}">@endif
                    @if ($q !== '')<input type="hidden" name="q" value="{{ $q }}">@endif
                    <input name="department" value="{{ $department }}" placeholder="Department" class="field">
                    <input name="batch" value="{{ $batch }}" placeholder="Batch / year" class="field">
                    <input name="semester" value="{{ $semester }}" placeholder="Semester" class="field">
                    <label class="flex items-center gap-2 rounded-xl border border-ink-200 px-3 text-sm dark:border-ink-800"><input type="checkbox" name="urgent" value="1" @checked($urgent) class="h-4 w-4 shrink-0 rounded border-ink-300 text-primary-500 focus:ring-primary-500"> <span>Important only</span></label>
                    <div class="flex gap-2 sm:col-span-2 lg:col-span-4">
                        <button type="submit" class="btn-primary">Apply filters</button>
                        <a href="{{ route('notices.index') }}" class="btn-secondary">Reset all</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="hero-panel mb-5 p-7 sm:p-8">
            <x-ui-icon name="megaphone" class="hero-icon" />
            <div class="relative z-10 flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-md">
                    <p class="eyebrow"><x-ui-icon name="megaphone" class="h-3.5 w-3.5" />Official notice board</p>
                    <h1 class="hero-title mt-2">Important campus updates in one place</h1>
                    <p class="section-sub mt-2">Exam notices, payment deadlines, class routines, scholarships, transport, and department updates &mdash; without digging through the feed.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('feed.index') }}" class="btn-primary whitespace-nowrap">Go to Feed</a>
                    <a href="{{ route('events.index') }}" class="btn-secondary whitespace-nowrap">Upcoming Events</a>
                </div>
            </div>

            <div class="cc-stagger relative z-10 mt-5 grid grid-cols-3 gap-2 sm:gap-3" style="--cc-offset: 220ms">
                <div class="hero-stat">
                    <span class="stat-value block truncate text-lg font-semibold tracking-tight sm:text-2xl">{{ number_format($stats['all']) }}</span>
                    <span class="mt-0.5 block truncate text-[10px] font-medium uppercase tracking-wide text-ink-500 dark:text-ink-400 sm:text-xs">Published</span>
                </div>
                <div class="hero-stat {{ $stats['urgent'] > 0 ? 'is-alert' : '' }}">
                    <span class="stat-value block truncate text-lg font-semibold tracking-tight text-red-600 dark:text-red-300 sm:text-2xl">{{ number_format($stats['urgent']) }}</span>
                    <span class="mt-0.5 block truncate text-[10px] font-medium uppercase tracking-wide text-ink-500 dark:text-ink-400 sm:text-xs">Urgent</span>
                </div>
                <div class="hero-stat">
                    <span class="stat-value block truncate text-lg font-semibold tracking-tight sm:text-2xl">{{ number_format($stats['academic']) }}</span>
                    <span class="mt-0.5 block truncate text-[10px] font-medium uppercase tracking-wide text-ink-500 dark:text-ink-400 sm:text-xs">Academic</span>
                </div>
            </div>
        </div>

        @if ($featured)
            @php $important = $featured->isImportant(); @endphp
            <a href="{{ route('notices.show', $featured) }}" class="surface surface-interactive sheen-once mb-5 flex gap-4 p-0">
                <div class="accent-bar {{ $important ? 'is-urgent' : 'is-accent' }}"></div>
                <div class="flex flex-1 min-w-0 gap-4 py-5 pr-6">
                    <div class="card-icon flex w-14 shrink-0 flex-col items-center rounded-xl bg-ink-900/[0.04] py-2 dark:bg-white/[0.06]">
                        <span class="text-[11px] font-semibold uppercase text-ink-400">{{ $featured->created_at->format('M') }}</span>
                        <span class="text-lg font-bold">{{ $featured->created_at->format('d') }}</span>
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap gap-1.5">
                            <span class="chip !bg-primary-500/10 !text-primary-700 dark:!text-primary-300">Featured</span>
                            <span class="chip">{{ $types[$featured->notice_type] ?? ucfirst($featured->notice_type) }}</span>
                            @if ($important)<span class="chip chip-alert !bg-red-500/10 !text-red-600 dark:!text-red-300">Important</span>@endif
                            @if ($featured->attachments_count > 0)
                                <span class="chip !bg-primary-500/10 !text-primary-700 dark:!text-primary-300"><x-ui-icon name="paperclip" class="h-3.5 w-3.5" />{{ $featured->attachments_count }}</span>
                            @endif
                        </div>
                        <h2 class="card-title mt-1.5 text-[17px] font-semibold">{{ $featured->title }}</h2>
                        <p class="mt-1 text-sm text-ink-600 dark:text-ink-300">{{ \Illuminate\Support\Str::limit(strip_tags($featured->body), 190) }}</p>
                        <p class="section-sub mt-2">Issued by {{ $featured->issuer->full_name }} &middot; {{ $featured->created_at->format('M j, Y g:i A') }}</p>
                        <span class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary-600 dark:text-primary-300">Read full notice<x-ui-icon name="chevron-right" class="h-4 w-4" /></span>
                    </div>
                </div>
            </a>
        @endif

        @if ($canIssue)
            <details class="surface mb-5 p-6">
                <summary class="cursor-pointer inline-flex items-center gap-1.5 text-[15px] font-semibold"><x-ui-icon name="edit" class="h-4 w-4" />Publish a notice</summary>
                <form method="POST" action="{{ route('notices.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="block"><span class="mb-1 block text-xs font-medium">Title</span><input name="title" required class="field"></label>
                        <label class="block"><span class="mb-1 block text-xs font-medium">Type</span>
                            <select name="notice_type" class="field">
                                @foreach ($types as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <label class="block"><span class="mb-1 block text-xs font-medium">Notice details</span><textarea name="body" rows="4" required class="field"></textarea></label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="block"><span class="mb-1 block text-xs font-medium">Priority</span>
                            <select name="priority" class="field">
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </label>
                        <label class="block"><span class="mb-1 block text-xs font-medium">Audience</span>
                            <select name="audience" class="field">
                                <option value="all">Everyone</option>
                                <option value="students">Students</option>
                                <option value="faculties">Faculty</option>
                                <option value="officials">Officials</option>
                            </select>
                        </label>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <input name="department" placeholder="Department (optional)" class="field">
                        <input name="batch" placeholder="Batch / year" class="field">
                        <input name="semester" placeholder="Semester" class="field">
                    </div>
                    <label class="block">
                        <span class="mb-1 flex items-center gap-1.5 text-xs font-medium"><x-ui-icon name="paperclip" class="h-3.5 w-3.5" />Attachments (optional)</span>
                        <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" class="field !py-1.5">
                        <span class="section-sub mt-1 block">Seat plans, routines, or forms &mdash; PDF, image, Word, or Excel, up to 10 MB each.</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_urgent" value="1" class="h-4 w-4 shrink-0 rounded border-ink-300 text-primary-500 focus:ring-primary-500"> <span>Mark as urgent</span></label>
                    <button type="submit" class="btn-primary w-full">Publish notice</button>
                </form>
            </details>
        @endif

        @if ($notices->isEmpty() && ! $featured)
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="flag" class="h-6 w-6" /></span>
                <h3>No notices found</h3>
                <p>Try clearing your filters or searching with a different keyword.</p>
            </div>
        @elseif ($notices->isNotEmpty())
            <div class="cc-stagger space-y-3">
                @foreach ($notices as $notice)
                    @php $important = $notice->isImportant(); $meta = array_filter([$notice->audience, $notice->department, $notice->batch, $notice->semester]); @endphp
                    <a href="{{ route('notices.show', $notice) }}" class="surface surface-interactive flex p-0" style="--cc-stagger: {{ min($loop->index, 14) * 55 }}ms">
                        <div class="accent-bar {{ $important ? 'is-urgent' : '' }}"></div>
                        <div class="flex flex-1 min-w-0 gap-4 p-5">
                            <span class="card-icon grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $important ? 'bg-red-500/10 text-red-600 dark:text-red-300' : 'bg-primary-500/10 text-primary-600 dark:text-primary-300' }}"><x-ui-icon :name="$important ? 'alert-triangle' : 'file-text'" class="h-5 w-5" /></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap gap-1.5">
                                    <span class="chip">{{ $types[$notice->notice_type] ?? ucfirst($notice->notice_type) }}</span>
                                    @if ($important)<span class="chip chip-alert !bg-red-500/10 !text-red-600 dark:!text-red-300">Important</span>@endif
                                    @if ($notice->attachments_count > 0)
                                        <span class="chip !bg-primary-500/10 !text-primary-700 dark:!text-primary-300"><x-ui-icon name="paperclip" class="h-3.5 w-3.5" />{{ $notice->attachments_count }} {{ \Illuminate\Support\Str::plural('file', $notice->attachments_count) }}</span>
                                    @endif
                                </div>
                                <h2 class="card-title mt-1.5 text-[15px] font-semibold">{{ $notice->title }}</h2>
                                <p class="mt-1 text-sm text-ink-600 dark:text-ink-300">{{ \Illuminate\Support\Str::limit(strip_tags($notice->body), 230) }}</p>
                                <div class="section-sub mt-2 flex flex-wrap gap-x-3 gap-y-1">
                                    @if (! empty($meta))<span>{{ implode(' · ', $meta) }}</span>@endif
                                    <span>Issued by {{ $notice->issuer->full_name }}</span>
                                    <span>{{ $notice->created_at->diffForHumans() }}</span>
                                </div>
                                <span class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary-600 dark:text-primary-300">Read full notice<x-ui-icon name="chevron-right" class="h-4 w-4" /></span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-6">{{ $notices->links() }}</div>
        @endif
    </div>
</x-app-layout>
