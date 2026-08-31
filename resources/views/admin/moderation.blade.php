@php
    $statusChip = fn ($status) => in_array($status, \App\Http\Controllers\ModerationController::OPEN_STATUSES, true)
        ? '!bg-amber-500/10 !text-amber-700 dark:!text-amber-300'
        : ($status === 'resolved' ? '!bg-emerald-500/10 !text-emerald-600 dark:!text-emerald-300' : '!bg-ink-500/10 !text-ink-600 dark:!text-ink-300');
@endphp
<x-app-layout title="Moderation Center - UIU CampusConnect">
    <div class="mx-auto max-w-4xl">
        <div class="surface mb-5 flex flex-wrap items-center justify-between gap-6 bg-gradient-to-br from-primary-600 to-primary-800 p-6 text-white">
            <div class="max-w-md">
                <p class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-white/80"><x-ui-icon name="shield" class="h-4 w-4" />Safety</p>
                <h1 class="mt-1 text-[22px] font-semibold">Moderation Center</h1>
                <p class="mt-1 text-sm text-white/85">Review post, comment, and profile reports. Take action to remove content or suspend the reported user, or dismiss the report.</p>
            </div>
            <div class="grid shrink-0 grid-cols-3 gap-2">
                <div class="rounded-2xl bg-white/15 px-3 py-2.5 text-center"><strong class="block text-lg">{{ $counts['posts'] }}</strong><span class="text-xs text-white/80">Posts</span></div>
                <div class="rounded-2xl bg-white/15 px-3 py-2.5 text-center"><strong class="block text-lg">{{ $counts['comments'] }}</strong><span class="text-xs text-white/80">Comments</span></div>
                <div class="rounded-2xl bg-white/15 px-3 py-2.5 text-center"><strong class="block text-lg">{{ $counts['profiles'] }}</strong><span class="text-xs text-white/80">Profiles</span></div>
            </div>
        </div>

        <div class="surface-flat mb-5 flex flex-wrap gap-1.5 p-2">
            @foreach (['open' => 'Open reports', 'posts' => 'Posts', 'comments' => 'Comments', 'profiles' => 'Profiles', 'resolved' => 'Resolved'] as $key => $label)
                <a href="{{ route('moderation.index', ['tab' => $key]) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $tab === $key ? 'bg-primary-500 text-white' : 'text-ink-600 dark:text-ink-300' }}">{{ $label }}</a>
            @endforeach
        </div>

        @if ($posts->isEmpty() && $comments->isEmpty() && $profiles->isEmpty())
            <div class="empty-state">
                <span class="empty-icon"><x-ui-icon name="check-circle" class="h-6 w-6" /></span>
                <h3>Nothing to review</h3>
                <p>There are no reports in this category right now.</p>
            </div>
        @endif

        @if ($posts->isNotEmpty())
            <h2 class="mb-2 mt-2 text-sm font-semibold text-ink-500 dark:text-ink-400">Post reports</h2>
            <div class="mb-5 space-y-3">
                @foreach ($posts as $report)
                    <article class="surface p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="chip {{ $statusChip($report->status) }}">{{ ucfirst($report->status) }}</span>
                            <span class="section-sub">{{ $report->created_at->format('M j, Y g:i A') }}</span>
                        </div>
                        <p class="mt-2 text-sm"><strong>Reported by</strong> {{ $report->reporter->full_name }} &middot; <strong>Reason:</strong> {{ $report->reason }}</p>
                        @if ($report->post)
                            <p class="mt-2 rounded-xl bg-ink-900/[0.03] p-3 text-sm text-ink-600 dark:bg-white/[0.04] dark:text-ink-300">By {{ $report->post->user->full_name }}: {{ \Illuminate\Support\Str::limit($report->post->body, 220) }}</p>
                        @else
                            <p class="mt-2 text-sm text-ink-400">This post no longer exists.</p>
                        @endif
                        @if (in_array($report->status, \App\Http\Controllers\ModerationController::OPEN_STATUSES, true))
                            <form method="POST" action="{{ route('moderation.posts.review', $report) }}" class="mt-3">
                                @csrf
                                <input type="hidden" name="tab" value="{{ $tab }}">
                                <textarea name="moderator_note" placeholder="Moderator note (optional)" rows="2" class="field mb-2"></textarea>
                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" name="decision" value="action" class="btn-danger !px-3 !py-1.5 text-xs">Remove post</button>
                                    <button type="submit" name="decision" value="dismiss" class="btn-secondary !px-3 !py-1.5 text-xs">Dismiss report</button>
                                </div>
                            </form>
                        @elseif ($report->moderator_note)
                            <p class="section-sub mt-2">Moderator note: {{ $report->moderator_note }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif

        @if ($comments->isNotEmpty())
            <h2 class="mb-2 mt-2 text-sm font-semibold text-ink-500 dark:text-ink-400">Comment reports</h2>
            <div class="mb-5 space-y-3">
                @foreach ($comments as $report)
                    <article class="surface p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="chip {{ $statusChip($report->status) }}">{{ ucfirst($report->status) }}</span>
                            <span class="section-sub">{{ $report->created_at->format('M j, Y g:i A') }}</span>
                        </div>
                        <p class="mt-2 text-sm"><strong>Reported by</strong> {{ $report->reporter->full_name }} &middot; <strong>Reason:</strong> {{ $report->reason }}</p>
                        @if ($report->comment)
                            <p class="mt-2 rounded-xl bg-ink-900/[0.03] p-3 text-sm text-ink-600 dark:bg-white/[0.04] dark:text-ink-300">By {{ $report->comment->user->full_name ?? 'Unknown' }}: {{ \Illuminate\Support\Str::limit($report->comment->body, 220) }}</p>
                        @else
                            <p class="mt-2 text-sm text-ink-400">This comment no longer exists.</p>
                        @endif
                        @if (in_array($report->status, \App\Http\Controllers\ModerationController::OPEN_STATUSES, true))
                            <form method="POST" action="{{ route('moderation.comments.review', $report) }}" class="mt-3">
                                @csrf
                                <input type="hidden" name="tab" value="{{ $tab }}">
                                <textarea name="moderator_note" placeholder="Moderator note (optional)" rows="2" class="field mb-2"></textarea>
                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" name="decision" value="action" class="btn-danger !px-3 !py-1.5 text-xs">Remove comment</button>
                                    <button type="submit" name="decision" value="dismiss" class="btn-secondary !px-3 !py-1.5 text-xs">Dismiss report</button>
                                </div>
                            </form>
                        @elseif ($report->moderator_note)
                            <p class="section-sub mt-2">Moderator note: {{ $report->moderator_note }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif

        @if ($profiles->isNotEmpty())
            <h2 class="mb-2 mt-2 text-sm font-semibold text-ink-500 dark:text-ink-400">Profile reports</h2>
            <div class="space-y-3">
                @foreach ($profiles as $report)
                    <article class="surface p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="chip {{ $statusChip($report->status) }}">{{ ucfirst($report->status) }}</span>
                            <span class="section-sub">{{ $report->created_at->format('M j, Y g:i A') }}</span>
                        </div>
                        <p class="mt-2 text-sm"><strong>Reported by</strong> {{ $report->reporter->full_name }} &middot; <strong>Against:</strong> <a href="{{ route('profile.show', $report->reportedUser) }}" class="hover:underline" target="_blank">{{ $report->reportedUser->full_name }}</a></p>
                        <p class="mt-1 text-sm"><strong>Reason:</strong> {{ $report->reason }}</p>
                        @if ($report->details)<p class="mt-1 text-sm text-ink-600 dark:text-ink-300">{{ $report->details }}</p>@endif
                        @if (in_array($report->status, \App\Http\Controllers\ModerationController::OPEN_STATUSES, true))
                            <form method="POST" action="{{ route('moderation.profiles.review', $report) }}" class="mt-3">
                                @csrf
                                <input type="hidden" name="tab" value="{{ $tab }}">
                                <textarea name="moderator_note" placeholder="Moderator note (optional)" rows="2" class="field mb-2"></textarea>
                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" name="decision" value="action" class="btn-danger !px-3 !py-1.5 text-xs">Suspend user</button>
                                    <button type="submit" name="decision" value="dismiss" class="btn-secondary !px-3 !py-1.5 text-xs">Dismiss report</button>
                                </div>
                            </form>
                        @elseif ($report->moderator_note)
                            <p class="section-sub mt-2">Moderator note: {{ $report->moderator_note }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
