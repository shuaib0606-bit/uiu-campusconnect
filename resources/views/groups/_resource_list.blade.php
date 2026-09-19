{{--
    The Materials list: slides, readings, lab manuals. Notices are a separate
    thing entirely - they are real notice records, so they also reach the
    university notice board.

    Expects: $heading, $blurb, $items, $canAdd, $group, $canManage.
--}}
<div class="section-heading mb-4">
    <div>
        <h2 class="text-[15px] font-semibold">{{ $heading }}</h2>
        <p class="section-sub">{{ $blurb }}</p>
    </div>
</div>

@if ($canAdd)
    <form method="POST" action="{{ route('groups.publish', $group) }}" enctype="multipart/form-data"
        class="mb-5 space-y-2 rounded-xl bg-ink-900/[0.03] p-3.5 dark:bg-white/[0.04]">
        @csrf
        <input name="title" required maxlength="190" class="field !py-1.5 text-sm" placeholder="Title, e.g. Week 4 slides">
        <input name="body" maxlength="2000" class="field !py-1.5 text-sm" placeholder="A line about it (optional)">
        <label class="block">
            <span class="mb-1 block text-xs text-ink-500 dark:text-ink-400">File</span>
            <input type="file" name="group_file" required
                accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.txt,.png,.jpg,.jpeg"
                class="field !py-1.5 text-xs">
        </label>
        <button type="submit" class="btn-primary !py-1.5 text-xs">Add material</button>
    </form>
@endif

<div class="space-y-2">
    @forelse ($items as $item)
        <div class="rounded-xl border border-ink-100 px-4 py-3 dark:border-ink-800">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold">{{ $item->title }}</p>
                    <p class="section-sub truncate">
                        {{ $item->uploader->full_name ?? 'Unknown' }}
                        &middot; {{ $item->created_at?->format('M j, Y g:i A') }}
                        @if ($item->sizeLabel()) &middot; {{ $item->sizeLabel() }} @endif
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    @if ($item->hasFile())
                        <a href="{{ route('groups.files.download', [$group, $item]) }}" class="btn-secondary !px-2.5 !py-1 text-xs">Download</a>
                    @endif
                    @if (! $group->isArchived() && ($canManage || auth()->id() === (int) $item->user_id))
                        <form method="POST" action="{{ route('groups.files.delete', [$group, $item]) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-ghost !px-2.5 !py-1 text-xs">Remove</button>
                        </form>
                    @endif
                </div>
            </div>

            @if ($item->body)
                <p class="mt-2 whitespace-pre-line text-sm text-ink-600 dark:text-ink-300">{{ $item->body }}</p>
            @endif
        </div>
    @empty
        <div class="empty-state !py-8">
            <span class="empty-icon"><x-ui-icon name="paperclip" class="h-5 w-5" /></span>
            <h3>Nothing here yet</h3>
            <p>Slides and readings shared with this group will appear here.</p>
        </div>
    @endforelse
</div>
