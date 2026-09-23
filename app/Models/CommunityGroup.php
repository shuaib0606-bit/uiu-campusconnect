<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Three kinds of room share this table.
 *
 * - open   : a student made it, people ask to join, the maker runs it.
 * - course : one per course section. Nobody joins or leaves; you are in it
 *            because you are enrolled, and the teacher runs it.
 * - club   : one per club. The moderator and officers run it, and membership
 *            follows club_members.
 *
 * They share one page, one wall and one file store, and differ only in where
 * the roster comes from and who is allowed to speak.
 */
class CommunityGroup extends Model
{
    public const KIND_OPEN = 'open';

    public const KIND_COURSE = 'course';

    public const KIND_CLUB = 'club';

    /** Only the people running the room may post. */
    public const POSTING_STAFF = 'staff';

    /** Anybody in the room may post. */
    public const POSTING_EVERYONE = 'everyone';

    /** Pivot role for somebody who has asked to join a private open group. */
    public const PENDING = 'pending';

    /** Pivot roles that run a room, whatever kind it is. */
    public const STAFF_ROLES = ['owner', 'teacher', 'assistant', 'officer'];

    protected $table = 'community_groups';

    protected $fillable = [
        'name', 'slug', 'group_type', 'kind', 'source_id', 'description',
        'department', 'cover_path', 'visibility', 'posting_mode',
        'archived_at', 'owner_id',
    ];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Accepted members only. A request to join a private group is stored in
     * the same pivot with the role 'pending', so it is deliberately excluded
     * here: every membership check in the app goes through this relation, and
     * a pending request must not read files, post, or be counted as a member.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_group_members', 'group_id', 'user_id')
            ->withPivot('role')
            ->wherePivot('role', '!=', self::PENDING);
    }

    /** People waiting for the owner to let them in. */
    public function pendingMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_group_members', 'group_id', 'user_id')
            ->withPivot('role')
            ->wherePivot('role', self::PENDING);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'group_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(CommunityGroupFile::class, 'group_id');
    }

    public function materials(): HasMany
    {
        return $this->files()->where('kind', CommunityGroupFile::MATERIAL);
    }

    public function notices(): HasMany
    {
        return $this->files()->where('kind', CommunityGroupFile::NOTICE);
    }

    /** The section this class group stands for, when it is a class group. */
    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'source_id');
    }

    /**
     * The section, but only for a class group.
     *
     * source_id points at course_sections for a class group and at clubs
     * for a club group, so the raw relation happily resolves "the section
     * whose id equals this club's id" for a club. Anything that reads the
     * section without checking the kind first should read it here.
     */
    public function courseSection(): ?CourseSection
    {
        return $this->kind === self::KIND_COURSE ? $this->section : null;
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'source_id');
    }

    // ---------------------------------------------------------------- kinds

    public function isCourse(): bool
    {
        return $this->kind === self::KIND_COURSE;
    }

    public function isClub(): bool
    {
        return $this->kind === self::KIND_CLUB;
    }

    public function isOpen(): bool
    {
        return $this->kind === self::KIND_OPEN;
    }

    /**
     * A derived group's roster comes from somewhere else, so it has no join
     * button, no leave button and no requests to approve.
     */
    public function isDerived(): bool
    {
        return ! $this->isOpen();
    }

    public function isPrivate(): bool
    {
        return $this->visibility === 'private';
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    // ---------------------------------------------------------- memberships

    public function hasMember(?User $user): bool
    {
        return $user !== null && $this->members()->where('users.id', $user->id)->exists();
    }

    public function hasPending(?User $user): bool
    {
        return $user !== null && $this->pendingMembers()->where('users.id', $user->id)->exists();
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && (int) $this->owner_id === (int) $user->id;
    }

    /** This person's pivot role, or null when they are not in the room. */
    public function roleOf(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return $this->members()->where('users.id', $user->id)->value('community_group_members.role');
    }

    /**
     * Runs the room: the teacher of a class, a club's moderator or officers,
     * or the student who made an open group.
     */
    public function isStaff(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isOwnedBy($user)) {
            return true;
        }

        return in_array((string) $this->roleOf($user), self::STAFF_ROLES, true);
    }

    // --------------------------------------------------------- what is allowed

    /** Nothing can be written to an archived room, by anybody. */
    public function canPost(?User $user): bool
    {
        if ($this->isArchived() || ! $this->hasMember($user)) {
            return false;
        }

        return $this->posting_mode === self::POSTING_EVERYONE || $this->isStaff($user);
    }

    /** Slides and notices are put up by the people running the room. */
    public function canPublish(?User $user): bool
    {
        return ! $this->isArchived() && $this->isStaff($user);
    }

    /** Open groups let any member share a file; derived ones do not. */
    public function canUpload(?User $user): bool
    {
        if ($this->isArchived()) {
            return false;
        }

        return $this->isOpen() ? $this->hasMember($user) : $this->isStaff($user);
    }

    public function canManage(?User $user): bool
    {
        return $this->isStaff($user);
    }

    /** Reading is always allowed to members, archived or not. */
    public function canRead(?User $user): bool
    {
        return $this->hasMember($user) || (! $this->isPrivate() && $user !== null);
    }

    // ------------------------------------------------------------- lifecycle

    /**
     * How the end of teaching should be announced inside a class group:
     * null while the trimester is running, a countdown once it has ended, and
     * a settled message once the room is archived.
     *
     * @return array{tone: string, text: string}|null
     */
    public function lifecycleNotice(): ?array
    {
        if (! $this->isCourse()) {
            return null;
        }

        if ($this->isArchived()) {
            return [
                'tone' => 'muted',
                'text' => 'This class is archived. Materials and notices stay here to download, but nothing new can be posted.',
            ];
        }

        $trimester = $this->section?->trimester;

        if (! $trimester || ! $trimester->hasEnded()) {
            return null;
        }

        $days = $trimester->daysUntilArchive();

        return [
            'tone' => $days <= 5 ? 'urgent' : 'warning',
            'text' => $days === 0
                ? 'This class ended on '.$trimester->ends_on->format('M j, Y').'. It becomes read-only today - download anything you still need.'
                : 'This class ended on '.$trimester->ends_on->format('M j, Y').'. It becomes read-only in '
                    .$days.' '.($days === 1 ? 'day' : 'days').' - download anything you still need.',
        ];
    }
}
