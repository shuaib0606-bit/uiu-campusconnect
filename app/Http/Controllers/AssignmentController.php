<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentAttachment;
use App\Models\AssignmentSubmission;
use App\Models\CommunityGroup;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Setting work, handing it in, and collecting it.
 *
 * Files go to the private disk and are never served as a URL. Every read
 * passes back through here so the question "may this person open this
 * file" is asked in one place, against the group the assignment belongs
 * to - the same check the rest of the group's materials use.
 */
class AssignmentController extends Controller
{
    public const DISK = 'local';

    public const FOLDER = 'assignment-submissions';

    /** Handouts sit beside submissions but are served under a wider permission. */
    public const ATTACHMENT_FOLDER = 'assignment-handouts';

    /** Enough for a template, an example and a rubric. */
    public const MAX_ATTACHMENTS = 5;

    public const ATTACHMENT_MB = 20;

    /** The widest set a teacher may pick from. Anything outside it is refused whatever the assignment says. */
    public const ALLOWED = [
        'pdf', 'doc', 'docx', 'zip', 'rar', 'png', 'jpg', 'jpeg',
        'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv',
    ];

    public function store(Request $request, CommunityGroup $group)
    {
        abort_unless($group->isStaff($request->user()) || $request->user()->isAdmin(), 403);

        $data = $this->validated($request);

        $assignment = Assignment::create([
            ...$data,
            'group_id' => $group->id,
            'created_by' => $request->user()->id,
        ]);

        // Handouts can come with the assignment or be added later; both
        // paths go through the same method.
        $this->storeAttachments($request, $assignment);

        $this->announce($assignment, $group);

        return back()->with('success', 'Assignment set.');
    }

    public function update(Request $request, CommunityGroup $group, Assignment $assignment)
    {
        abort_unless($assignment->group_id === $group->id, 404);
        abort_unless($assignment->manageableBy($request->user()), 403);

        $assignment->update($this->validated($request));

        return back()->with('success', 'Assignment updated.');
    }

    public function destroy(Request $request, CommunityGroup $group, Assignment $assignment)
    {
        abort_unless($assignment->group_id === $group->id, 404);
        abort_unless($assignment->manageableBy($request->user()), 403);

        // The rows go with the assignment by cascade, but the files on
        // disk do not, so they are removed first.
        foreach ($assignment->submissions()->get() as $submission) {
            Storage::disk(self::DISK)->delete($submission->file_path);
        }

        foreach ($assignment->attachments()->get() as $attachment) {
            Storage::disk(self::DISK)->delete($attachment->file_path);
        }

        $assignment->delete();

        return back()->with('success', 'Assignment removed.');
    }

    /** Opens or closes submissions without touching what has been handed in. */
    public function toggle(Request $request, CommunityGroup $group, Assignment $assignment)
    {
        abort_unless($assignment->group_id === $group->id, 404);
        abort_unless($assignment->manageableBy($request->user()), 403);

        $assignment->update([
            'status' => $assignment->status === Assignment::OPEN ? Assignment::CLOSED : Assignment::OPEN,
        ]);

        return back()->with('success', $assignment->status === Assignment::OPEN
            ? 'Submissions reopened.'
            : 'Submissions closed.');
    }

    /** Adds handouts to an assignment that already exists. */
    public function attach(Request $request, CommunityGroup $group, Assignment $assignment)
    {
        abort_unless($assignment->group_id === $group->id, 404);
        abort_unless($assignment->manageableBy($request->user()), 403);

        $request->validate([
            'attachments' => ['required', 'array', 'max:'.self::MAX_ATTACHMENTS],
            'attachments.*' => ['file', 'max:'.(self::ATTACHMENT_MB * 1024), 'mimes:'.implode(',', self::ALLOWED)],
            'label' => ['nullable', 'string', 'max:190'],
        ], [
            'attachments.*.mimes' => 'Handouts must be one of: '.implode(', ', self::ALLOWED).'.',
        ]);

        $added = $this->storeAttachments($request, $assignment);

        return back()->with('success', $added === 1 ? 'Handout added.' : $added.' handouts added.');
    }

    public function detach(Request $request, CommunityGroup $group, Assignment $assignment, AssignmentAttachment $attachment)
    {
        abort_unless($assignment->group_id === $group->id, 404);
        abort_unless($attachment->assignment_id === $assignment->id, 404);
        abort_unless($assignment->manageableBy($request->user()), 403);

        Storage::disk(self::DISK)->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Handout removed.');
    }

    /**
     * Serves a handout to anyone in the class.
     *
     * The permission here is deliberately wider than the one on
     * submissions: a template every student is meant to fill in is of no
     * use if only its author can open it.
     */
    public function downloadAttachment(Request $request, CommunityGroup $group, Assignment $assignment, AssignmentAttachment $attachment)
    {
        abort_unless($assignment->group_id === $group->id, 404);
        abort_unless($attachment->assignment_id === $assignment->id, 404);
        abort_unless($attachment->readableBy($request->user()), 403);

        abort_unless(Storage::disk(self::DISK)->exists($attachment->file_path), 404, 'That file is no longer on the server.');

        return Storage::disk(self::DISK)->download($attachment->file_path, $attachment->original_name);
    }

    /** @return int how many were stored */
    private function storeAttachments(Request $request, Assignment $assignment): int
    {
        $files = $request->file('attachments') ?? [];
        $label = $request->input('label');
        $room = self::MAX_ATTACHMENTS - $assignment->attachments()->count();
        $stored = 0;

        foreach (array_slice((array) $files, 0, max(0, $room)) as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            AssignmentAttachment::create([
                'assignment_id' => $assignment->id,
                'uploaded_by' => $request->user()->id,
                'file_path' => $file->store(self::ATTACHMENT_FOLDER, self::DISK),
                'original_name' => $file->getClientOriginalName(),
                'size' => (int) $file->getSize(),
                'mime' => $file->getClientMimeType(),
                // One label covers the batch: uploading a template and its
                // example together, "Use these" describes both.
                'label' => $stored === 0 ? $label : null,
            ]);

            $stored++;
        }

        return $stored;
    }

    /**
     * Hands work in, or replaces what was handed in before.
     *
     * Lateness is decided here, once, from the clock at the moment the
     * file arrives - not recomputed on every read, so moving a deadline
     * afterwards cannot retroactively brand somebody late.
     */
    public function submit(Request $request, CommunityGroup $group, Assignment $assignment)
    {
        $user = $request->user();

        abort_unless($assignment->group_id === $group->id, 404);

        if (! $assignment->acceptsSubmissionFrom($user)) {
            return back()->with('error', $assignment->status !== Assignment::OPEN
                ? 'Submissions for this assignment are closed.'
                : 'This assignment is past its deadline and late work was not allowed.');
        }

        $extensions = array_values(array_intersect($assignment->extensions(), self::ALLOWED));

        $request->validate([
            'submission' => [
                'required', 'file',
                'max:'.((int) $assignment->max_file_mb * 1024),
                'mimes:'.implode(',', $extensions ?: self::ALLOWED),
            ],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'submission.mimes' => 'That file type is not accepted here. Allowed: '.implode(', ', $extensions).'.',
            'submission.max' => 'That file is larger than the '.$assignment->max_file_mb.' MB limit.',
        ]);

        $file = $request->file('submission');
        $previous = $assignment->submissionFor($user);

        $path = $file->store(self::FOLDER, self::DISK);

        AssignmentSubmission::updateOrCreate(
            ['assignment_id' => $assignment->id, 'user_id' => $user->id],
            [
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'size' => (int) $file->getSize(),
                'mime' => $file->getClientMimeType(),
                'note' => $request->input('note'),
                'is_late' => $assignment->isOverdue(),
                'submitted_at' => now(),
            ],
        );

        // Only once the replacement is safely stored.
        if ($previous && $previous->file_path !== $path) {
            Storage::disk(self::DISK)->delete($previous->file_path);
        }

        return back()->with('success', $previous ? 'Submission replaced.' : 'Submitted.');
    }

    /** Withdraws a submission before the deadline. */
    public function withdraw(Request $request, CommunityGroup $group, Assignment $assignment)
    {
        $user = $request->user();
        $submission = $assignment->submissionFor($user);

        abort_unless($assignment->group_id === $group->id, 404);
        abort_unless($submission !== null, 404);

        if (! $assignment->acceptsSubmissionFrom($user)) {
            return back()->with('error', 'This assignment no longer accepts changes.');
        }

        Storage::disk(self::DISK)->delete($submission->file_path);
        $submission->delete();

        return back()->with('success', 'Submission withdrawn.');
    }

    public function download(Request $request, CommunityGroup $group, Assignment $assignment, AssignmentSubmission $submission)
    {
        abort_unless($assignment->group_id === $group->id, 404);
        abort_unless($submission->assignment_id === $assignment->id, 404);

        // A student may open their own work; staff may open anyone's in
        // their class. Classmates may not - which is why this cannot be
        // a public URL.
        abort_unless($submission->readableBy($request->user()), 403);

        abort_unless(Storage::disk(self::DISK)->exists($submission->file_path), 404, 'That file is no longer on the server.');

        return Storage::disk(self::DISK)->download($submission->file_path, $submission->original_name);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
            'allow_late' => ['nullable', 'boolean'],
            'max_file_mb' => ['nullable', 'integer', 'min:1', 'max:50'],
            'allowed_extensions' => ['nullable', 'array'],
            'allowed_extensions.*' => [Rule::in(self::ALLOWED)],

            // Handouts may arrive with the assignment, so they are checked
            // here as well as on the later attach route. Without this pair
            // of rules the creation path would store whatever was sent.
            'attachments' => ['nullable', 'array', 'max:'.self::MAX_ATTACHMENTS],
            'attachments.*' => ['file', 'max:'.(self::ATTACHMENT_MB * 1024), 'mimes:'.implode(',', self::ALLOWED)],
            'label' => ['nullable', 'string', 'max:190'],
        ], [
            'attachments.*.mimes' => 'Handouts must be one of: '.implode(', ', self::ALLOWED).'.',
            'attachments.*.max' => 'Each handout must be under '.self::ATTACHMENT_MB.' MB.',
        ]);

        $extensions = $data['allowed_extensions'] ?? [];

        return [
            'title' => $data['title'],
            'instructions' => $data['instructions'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'allow_late' => $request->boolean('allow_late'),
            'max_file_mb' => $data['max_file_mb'] ?? 20,
            'allowed_extensions' => implode(',', $extensions ?: self::ALLOWED),
        ];
    }

    /** Tells the class, so an assignment does not depend on somebody opening the group. */
    private function announce(Assignment $assignment, CommunityGroup $group): void
    {
        // Membership is a bounded list, so ids come straight off the
        // pivot and the notification goes out against a plain User query -
        // which is what Notifier::broadcast takes.
        $memberIds = $group->members()->pluck('users.id');

        if ($memberIds->isEmpty()) {
            return;
        }

        $due = $assignment->due_at ? ' - due '.$assignment->dueLabel() : '';

        Notifier::broadcast(
            User::query()->where('status', 'active')->whereIn('users.id', $memberIds),
            $assignment->teacher,
            Notifier::NOTICE,
            'assignment',
            $assignment->id,
            'New assignment in '.$group->name.': '.$assignment->title.$due,
            // Coursework is an obligation, not social chatter: a mute or
            // a block between two people should not swallow a deadline.
            respectSocialState: false,
        );
    }
}
