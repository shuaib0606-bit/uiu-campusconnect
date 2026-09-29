{{--
    Assignments inside a class group.

    Two audiences share this panel and see different things. A student
    sees the deadline, their own submission state and one upload control.
    Staff see the same list plus who has handed in, and can open any
    submission. Neither ever sees a classmate's file, because the download
    route checks that before it serves a byte.
--}}

@php
    $viewer = auth()->user();
    $manages = $group->isStaff($viewer) || ($viewer?->isAdmin() ?? false);
@endphp

<div class="flex items-start justify-between gap-3">
    <div>
        <h2 class="text-[15px] font-semibold">Assignments</h2>
        <p class="section-sub mt-0.5">
            {{ $manages
                ? 'Work you have set for this class, and what has come in.'
                : 'Work set for this class. Hand in before the deadline.' }}
        </p>
    </div>

    @if ($manages && ! $group->isArchived())
        <button type="button" @click="setting = ! setting" class="btn-secondary !px-3 !py-1.5 text-xs shrink-0">
            <x-ui-icon name="plus" class="h-3.5 w-3.5" />New assignment
        </button>
    @endif
</div>

@if ($manages && ! $group->isArchived())
    <form x-show="setting" x-cloak method="POST" action="{{ route('assignments.store', $group) }}"
        enctype="multipart/form-data"
        class="mt-4 space-y-3 rounded-xl border border-ink-200 p-4 dark:border-ink-700">
        @csrf

        <label class="block">
            <span class="mb-1.5 block text-sm font-medium">Title</span>
            <input name="title" required maxlength="190" class="field" placeholder="Lab report 3">
        </label>

        <label class="block">
            <span class="mb-1.5 block text-sm font-medium">Instructions <span class="font-normal text-ink-400">(optional)</span></span>
            <textarea name="instructions" rows="3" maxlength="5000" class="field resize-none"
                placeholder="What to do, and what to hand in."></textarea>
        </label>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium">Due <span class="font-normal text-ink-400">(optional)</span></span>
                <input type="datetime-local" name="due_at" class="field">
            </label>

            <label class="block">
                <span class="mb-1.5 block text-sm font-medium">Max file size</span>
                <select name="max_file_mb" class="field">
                    @foreach ([5, 10, 20, 50] as $mb)
                        <option value="{{ $mb }}" @selected($mb === 20)>{{ $mb }} MB</option>
                    @endforeach
                </select>
            </label>
        </div>

        <fieldset>
            <legend class="mb-1.5 text-sm font-medium">Accepted files</legend>
            <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                @foreach (\App\Http\Controllers\AssignmentController::ALLOWED as $ext)
                    <label class="flex items-center gap-1.5 text-sm">
                        <input type="checkbox" name="allowed_extensions[]" value="{{ $ext }}"
                            @checked(in_array($ext, ['pdf', 'zip', 'docx', 'png', 'jpg'], true))
                            class="rounded border-ink-300 text-primary-600 focus:ring-primary-400">
                        {{ $ext }}
                    </label>
                @endforeach
            </div>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Leave all unticked to accept any of these.</p>
        </fieldset>

        <label class="block">
            <span class="mb-1.5 block text-sm font-medium">
                Handouts <span class="font-normal text-ink-400">(optional)</span>
            </span>
            <input type="file" name="attachments[]" multiple
                class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-700 dark:file:bg-primary-950/40 dark:file:text-primary-300">
            <span class="mt-1 block text-xs text-ink-500 dark:text-ink-400">
                A template, a worked example, a marking rubric. Up to
                {{ \App\Http\Controllers\AssignmentController::MAX_ATTACHMENTS }} files,
                {{ \App\Http\Controllers\AssignmentController::ATTACHMENT_MB }} MB each. Everyone in the class can open these.
            </span>
        </label>

        <label class="block">
            <span class="mb-1.5 block text-sm font-medium">What are the handouts for? <span class="font-normal text-ink-400">(optional)</span></span>
            <input name="label" maxlength="190" class="field !py-1.5 text-sm" placeholder="Use this template and follow the sample formatting">
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="allow_late" value="1" checked
                class="rounded border-ink-300 text-primary-600 focus:ring-primary-400">
            Accept late submissions, flagged as late
        </label>

        <div class="flex justify-end gap-2">
            <button type="button" @click="setting = false" class="btn-secondary !px-3 !py-1.5 text-sm">Cancel</button>
            <button type="submit" class="btn-primary !px-4 !py-1.5 text-sm">Set assignment</button>
        </div>
    </form>
@endif

<div class="mt-4 space-y-3">
    @forelse ($assignments as $assignment)
        @php
            $mine = $manages ? null : $assignment->submissionFor($viewer);
            $canSubmit = $assignment->acceptsSubmissionFrom($viewer);
        @endphp

        <div class="rounded-xl border border-ink-200 p-4 dark:border-ink-700" x-data="{ open: false }">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[15px] font-medium">{{ $assignment->title }}</p>

                    <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">
                        {{ $assignment->teacher?->full_name }}
                        @if ($assignment->due_at)
                            &middot; due {{ $assignment->dueLabel() }}
                            <span class="{{ $assignment->isOverdue() ? 'text-red-600 dark:text-red-400' : '' }}">
                                ({{ $assignment->dueRelative() }})
                            </span>
                        @else
                            &middot; no deadline
                        @endif
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-1.5">
                    @if ($assignment->status !== \App\Models\Assignment::OPEN)
                        <span class="chip">Closed</span>
                    @elseif ($mine)
                        <span class="chip {{ $mine->is_late ? 'text-amber-700 dark:text-amber-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                            {{ $mine->is_late ? 'Submitted late' : 'Submitted' }}
                        </span>
                    @elseif ($manages)
                        <span class="chip">{{ $assignment->submissions_count }} in</span>
                    @endif
                </div>
            </div>

            @if ($assignment->instructions)
                <p class="mt-2 whitespace-pre-wrap text-sm text-ink-600 dark:text-ink-300">{{ $assignment->instructions }}</p>
            @endif

            {{-- Handouts from the teacher. Shown to everyone in the class,
                 above the upload control, because a template is no use
                 discovered after you have written the thing. --}}
            @php $handouts = $assignment->attachments; @endphp

            @if ($handouts->isNotEmpty())
                <div class="mt-3 rounded-lg bg-ink-50 p-3 dark:bg-ink-800/50">
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">
                        Provided by {{ $assignment->teacher?->full_name }}
                    </p>

                    @if ($handouts->first()?->label)
                        <p class="mt-1 text-sm text-ink-600 dark:text-ink-300">{{ $handouts->first()->label }}</p>
                    @endif

                    <ul class="mt-2 space-y-1.5">
                        @foreach ($handouts as $handout)
                            <li class="flex items-center gap-2 text-sm">
                                <x-ui-icon name="paperclip" class="h-3.5 w-3.5 shrink-0 text-ink-400" />
                                <a href="{{ route('assignments.attachment', [$group, $assignment, $handout]) }}"
                                    class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                    {{ $handout->original_name }}
                                </a>
                                <span class="text-xs text-ink-500">{{ $handout->sizeLabel() }}</span>

                                @if ($manages && ! $group->isArchived())
                                    <form method="POST" action="{{ route('assignments.detach', [$group, $assignment, $handout]) }}" class="ml-auto">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline dark:text-red-400">Remove</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($manages && ! $group->isArchived() && $handouts->count() < \App\Http\Controllers\AssignmentController::MAX_ATTACHMENTS)
                <form method="POST" action="{{ route('assignments.attach', [$group, $assignment]) }}"
                    enctype="multipart/form-data" class="mt-2 flex flex-wrap items-center gap-2"
                    x-data="{ picked: false }">
                    @csrf
                    <input type="file" name="attachments[]" multiple required @change="picked = true"
                        class="max-w-xs text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-ink-100 file:px-2.5 file:py-1 file:text-xs file:font-medium dark:file:bg-ink-700">
                    <button type="submit" x-show="picked" x-cloak class="btn-secondary !px-3 !py-1 text-xs">Add handout</button>
                </form>
            @endif

            {{-- Student view: their own submission and the upload control. --}}
            @unless ($manages)
                @if ($mine)
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                        <a href="{{ route('assignments.download', [$group, $assignment, $mine]) }}"
                            class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                            {{ $mine->original_name }}
                        </a>
                        <span class="text-xs text-ink-500">{{ $mine->sizeLabel() }} &middot; {{ $mine->submitted_at?->diffForHumans() }}</span>

                        @if ($canSubmit)
                            <form method="POST" action="{{ route('assignments.withdraw', [$group, $assignment]) }}" class="ml-auto">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-600 hover:underline dark:text-red-400">Withdraw</button>
                            </form>
                        @endif
                    </div>
                @endif

                @if ($canSubmit)
                    <form method="POST" action="{{ route('assignments.submit', [$group, $assignment]) }}"
                        enctype="multipart/form-data" class="mt-3 space-y-2"
                        x-data="{ busy: false }" @submit="busy = true">
                        @csrf

                        <input type="file" name="submission" required
                            accept=".{{ implode(',.', $assignment->extensions()) }}"
                            class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-700 dark:file:bg-primary-950/40 dark:file:text-primary-300">

                        <input name="note" maxlength="500" class="field !py-1.5 text-sm"
                            placeholder="A note for your teacher (optional)">

                        <div class="flex items-center gap-2">
                            <button type="submit" class="btn-primary !px-4 !py-1.5 text-sm" :disabled="busy">
                                <span x-show="! busy">{{ $mine ? 'Replace submission' : 'Hand in' }}</span>
                                <span x-show="busy" x-cloak>Uploading…</span>
                            </button>
                            <span class="text-xs text-ink-500 dark:text-ink-400">
                                {{ implode(', ', $assignment->extensions()) }} &middot; up to {{ $assignment->max_file_mb }} MB
                            </span>
                        </div>
                    </form>
                @elseif (! $mine)
                    <p class="mt-3 text-sm text-ink-500 dark:text-ink-400">
                        {{ $assignment->status !== \App\Models\Assignment::OPEN
                            ? 'Submissions are closed.'
                            : 'The deadline has passed and late work was not accepted.' }}
                    </p>
                @endif
            @else
                {{-- Staff view: who has handed in. --}}
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" @click="open = ! open" class="btn-secondary !px-3 !py-1.5 text-xs">
                        <x-ui-icon name="clipboard" class="h-3.5 w-3.5" />
                        <span x-show="! open">View {{ $assignment->submissions_count }} submission{{ $assignment->submissions_count === 1 ? '' : 's' }}</span>
                        <span x-show="open" x-cloak>Hide submissions</span>
                    </button>

                    <form method="POST" action="{{ route('assignments.toggle', [$group, $assignment]) }}">
                        @csrf
                        <button class="btn-secondary !px-3 !py-1.5 text-xs">
                            {{ $assignment->status === \App\Models\Assignment::OPEN ? 'Close submissions' : 'Reopen' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('assignments.destroy', [$group, $assignment]) }}"
                        x-data="{ sure: false }" @submit="if (! sure) { $event.preventDefault(); sure = true }">
                        @csrf @method('DELETE')
                        <button class="btn-secondary !px-3 !py-1.5 text-xs"
                            :class="sure && 'text-red-600 dark:text-red-400'">
                            <span x-show="! sure">Delete</span>
                            <span x-show="sure" x-cloak>Click again to confirm</span>
                        </button>
                    </form>
                </div>

                <div x-show="open" x-cloak class="mt-3 divide-y divide-ink-100 dark:divide-ink-800">
                    @forelse ($assignment->submissions()->with('student:id,full_name,student_id')->latest('submitted_at')->get() as $submission)
                        <div class="flex flex-wrap items-center gap-2 py-2 text-sm">
                            <span class="font-medium">{{ $submission->student?->full_name }}</span>
                            <span class="text-xs text-ink-500">{{ $submission->student?->student_id }}</span>

                            @if ($submission->is_late)
                                <span class="chip text-amber-700 dark:text-amber-400">late</span>
                            @endif

                            <a href="{{ route('assignments.download', [$group, $assignment, $submission]) }}"
                                class="ml-auto font-medium text-primary-600 hover:underline dark:text-primary-400">
                                {{ $submission->original_name }}
                            </a>
                            <span class="text-xs text-ink-500">{{ $submission->sizeLabel() }}</span>

                            @if ($submission->note)
                                <p class="w-full text-xs text-ink-500 dark:text-ink-400">“{{ $submission->note }}”</p>
                            @endif
                        </div>
                    @empty
                        <p class="py-2 text-sm text-ink-500 dark:text-ink-400">Nothing handed in yet.</p>
                    @endforelse
                </div>
            @endunless
        </div>
    @empty
        <p class="text-sm text-ink-500 dark:text-ink-400">
            {{ $manages ? 'No assignments set yet.' : 'No assignments have been set for this class.' }}
        </p>
    @endforelse
</div>
