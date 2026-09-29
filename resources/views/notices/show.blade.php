@php
    $important = $notice->isImportant();
    $typeLabel = $types[$notice->notice_type] ?? ucfirst($notice->notice_type);
    $meta = array_filter([
        'Audience' => $notice->audience ? ucfirst($notice->audience) : null,
        'Department' => $notice->department,
        'Batch' => $notice->batch,
        'Semester' => $notice->semester,
    ]);
@endphp
<x-app-layout title="{{ $notice->title }} - UIU CampusConnect">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('notices.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-ink-500 transition hover:text-ink-900 dark:text-ink-400 dark:hover:text-white">
            <x-ui-icon name="chevron-right" class="h-4 w-4 rotate-180" />
            Back to Notices
        </a>

        {{-- The banner. Kept as its own card above the notice rather than
             inside it, because the notice card is a flex row with the
             coloured priority bar down its left edge and a picture does not
             belong in that row. Hidden entirely when there is no picture and
             the viewer could not add one. --}}
        @if ($canEditMedia || $notice->hasUploadedCover())
            <div class="surface mb-5 overflow-hidden">
                <x-media-gallery :item="$notice" type="notice" :can-edit="$canEditMedia" height="h-40 sm:h-56" />
            </div>
        @endif

        <article class="surface mb-5 flex p-0">
            <div class="accent-bar {{ $important ? 'is-urgent' : 'is-accent' }}"></div>
            <div class="min-w-0 flex-1 p-6 sm:p-7">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="chip">{{ $typeLabel }}</span>
                    <span class="chip">{{ ucfirst($notice->priority) }} priority</span>
                    @if ($important)
                        <span class="chip chip-alert !bg-red-500/10 !text-red-600 dark:!text-red-300">
                            <x-ui-icon name="alert-triangle" class="h-3.5 w-3.5" />
                            Important
                        </span>
                    @endif
                    @if ($notice->attachments->isNotEmpty())
                        <span class="chip !bg-primary-500/10 !text-primary-700 dark:!text-primary-300">
                            <x-ui-icon name="paperclip" class="h-3.5 w-3.5" />
                            {{ $notice->attachments->count() }} {{ \Illuminate\Support\Str::plural('file', $notice->attachments->count()) }}
                        </span>
                    @endif
                </div>

                <h1 class="mt-3 text-[22px] font-semibold leading-snug tracking-tight sm:text-[26px]">{{ $notice->title }}</h1>

                <div class="mt-4 flex flex-wrap items-center gap-3 border-y border-ink-100 py-3 dark:border-ink-800/70">
                    <x-user-avatar :user="$notice->issuer" size="h-9 w-9" text="text-xs" />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $notice->issuer->full_name }}</p>
                        <p class="section-sub truncate">{{ ucfirst($notice->issuer->role) }} &middot; Issued {{ $notice->created_at->format('M j, Y \a\t g:i A') }}</p>
                    </div>
                    <span class="ml-auto inline-flex shrink-0 items-center gap-1 text-xs text-ink-500 dark:text-ink-400">
                        <x-ui-icon name="clock" class="h-3.5 w-3.5" />
                        {{ $notice->created_at->diffForHumans() }}
                    </span>
                </div>

                <div class="mt-5 text-[15px] leading-relaxed text-ink-700 dark:text-ink-200">
                    {!! nl2br(e($notice->body)) !!}
                </div>

                @if (! empty($meta))
                    <dl class="mt-6 grid gap-2 border-t border-ink-100 pt-5 dark:border-ink-800/70 sm:grid-cols-2">
                        @foreach ($meta as $label => $value)
                            <div class="surface-flat rounded-xl px-3.5 py-2.5">
                                <dt class="text-[11px] font-medium uppercase tracking-wide text-ink-500 dark:text-ink-400">{{ $label }}</dt>
                                <dd class="mt-0.5 truncate text-sm font-medium">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        </article>

        @if ($notice->attachments->isNotEmpty())
            <section class="surface mb-5 p-6">
                <div class="section-heading mb-4">
                    <div>
                        <h2 class="text-[15px] font-semibold">Attachments</h2>
                        <p class="section-sub">Seat plans, routines, and forms attached to this notice.</p>
                    </div>
                </div>

                <div class="cc-stagger space-y-2.5">
                    @foreach ($notice->attachments as $attachment)
                        <div class="surface-flat flex items-center gap-3 rounded-xl p-3.5" style="--cc-stagger: {{ min($loop->index, 10) * 55 }}ms">
                            <span class="card-icon grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-300">
                                <x-ui-icon :name="$attachment->iconName()" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $attachment->original_name }}</p>
                                <p class="section-sub">
                                    {{ strtoupper($attachment->extension()) ?: 'FILE' }} &middot; {{ $attachment->humanSize() }}
                                </p>
                            </div>
                            <a href="{{ Storage::url($attachment->file_path) }}" download="{{ $attachment->original_name }}" class="btn-secondary shrink-0 !px-3 !py-1.5 text-xs">
                                <x-ui-icon name="download" class="h-3.5 w-3.5" />
                                Download
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($related->isNotEmpty())
            <section class="surface p-6">
                <h2 class="mb-3 text-[15px] font-semibold">More {{ strtolower($typeLabel) }} notices</h2>
                <div class="space-y-2">
                    @foreach ($related as $other)
                        <a href="{{ route('notices.show', $other) }}" class="surface-flat surface-hover flex items-center gap-3 rounded-xl p-3.5">
                            <span class="card-icon grid h-9 w-9 shrink-0 place-items-center rounded-lg {{ $other->isImportant() ? 'bg-red-500/10 text-red-600 dark:text-red-300' : 'bg-primary-500/10 text-primary-600 dark:text-primary-300' }}">
                                <x-ui-icon :name="$other->isImportant() ? 'alert-triangle' : 'megaphone'" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $other->title }}</p>
                                <p class="section-sub">
                                    {{ $other->created_at->diffForHumans() }}
                                    @if ($other->attachments_count > 0)
                                        &middot; {{ $other->attachments_count }} {{ \Illuminate\Support\Str::plural('file', $other->attachments_count) }}
                                    @endif
                                </p>
                            </div>
                            <x-ui-icon name="chevron-right" class="h-4 w-4 shrink-0 text-ink-400" />
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
