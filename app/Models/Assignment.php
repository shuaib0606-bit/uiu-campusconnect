<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A piece of work a teacher has set inside a class group.
 *
 * Who may create one, and who may hand one in, is not decided here: it
 * reduces to group membership and staff role, which CommunityGroup
 * already answers for materials, notices and posts. One idea of who is
 * in a class, used everywhere.
 */
class Assignment extends Model
{
    public const OPEN = 'open';

    public const CLOSED = 'closed';

    protected $fillable = [
        'group_id', 'created_by', 'title', 'instructions', 'due_at',
        'allow_late', 'max_file_mb', 'allowed_extensions', 'status',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'allow_late' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CommunityGroup::class, 'group_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /** Handouts from the teacher: templates, examples, rubrics. Class-readable. */
    public function attachments(): HasMany
    {
        return $this->hasMany(AssignmentAttachment::class)->orderBy('id');
    }

    public function submissionFor(?User $user): ?AssignmentSubmission
    {
        return $user ? $this->submissions()->where('user_id', $user->id)->first() : null;
    }

    /** @return list<string> lower-case, no dots */
    public function extensions(): array
    {
        return array_values(array_filter(array_map(
            fn ($ext) => strtolower(trim($ext, " \t.")),
            explode(',', (string) $this->allowed_extensions),
        )));
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast();
    }

    /**
     * Whether this student may still hand something in.
     *
     * Closing is the teacher's decision and always final. A passed
     * deadline only stops submissions when late work was disallowed when
     * the assignment was set.
     */
    public function acceptsSubmissionFrom(?User $user): bool
    {
        if (! $user || $this->status !== self::OPEN) {
            return false;
        }

        if (! $this->group?->hasMember($user) || $this->group->isStaff($user)) {
            return false;
        }

        return ! $this->isOverdue() || $this->allow_late;
    }

    /** Only group staff set and manage work. */
    public function manageableBy(?User $user): bool
    {
        return $user !== null
            && ($user->isAdmin() || (bool) $this->group?->isStaff($user));
    }

    /** "Due Thu, Oct 2, 11:59 PM", or nothing when open-ended. */
    public function dueLabel(): ?string
    {
        return $this->due_at?->format('D, M j, g:i A');
    }

    /**
     * How the deadline reads to a student right now: "3 days left",
     * "due tomorrow", "2 days overdue". Relative time is what people
     * actually plan around.
     */
    public function dueRelative(): ?string
    {
        if ($this->due_at === null) {
            return null;
        }

        return $this->isOverdue()
            ? $this->due_at->diffForHumans(null, true).' overdue'
            : $this->due_at->diffForHumans(null, true).' left';
    }
}
