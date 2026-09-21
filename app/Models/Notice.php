<?php

namespace App\Models;

use App\Models\Concerns\HasGallery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notice extends Model
{
    use HasGallery;

    protected $fillable = [
        'title', 'body', 'notice_type', 'priority', 'audience',
        'department', 'batch', 'semester', 'is_urgent', 'issuer_id', 'status',
        'group_id',
    ];

    protected function casts(): array
    {
        return ['is_urgent' => 'boolean'];
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuer_id');
    }

    /**
     * Set when the notice belongs to a course or club group. A scoped notice
     * shows inside that group and, on the university board, only to the people
     * in it - a class announcement is not campus news.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CommunityGroup::class, 'group_id');
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'notice_reads')->withPivot('read_at');
    }

    public function isScoped(): bool
    {
        return $this->group_id !== null;
    }

    /**
     * Limits a notice query to what this person is allowed to see: everything
     * unscoped, plus the notices of the groups they are actually in.
     */
    public function scopeVisibleTo($query, ?User $viewer)
    {
        if (! $viewer) {
            return $query->whereNull('notices.group_id');
        }

        return $query->where(fn ($q) => $q
            ->whereNull('notices.group_id')
            ->orWhereExists(fn ($sub) => $sub->selectRaw('1')
                ->from('community_group_members')
                ->whereColumn('community_group_members.group_id', 'notices.group_id')
                ->where('community_group_members.user_id', $viewer->id)
                ->where('community_group_members.role', '!=', CommunityGroup::PENDING)));
    }

    /** Group notices this person has not opened yet. */
    public static function unreadGroupCountFor(?User $viewer): int
    {
        if (! $viewer) {
            return 0;
        }

        return static::where('status', 'published')
            ->whereNotNull('notices.group_id')
            ->visibleTo($viewer)
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')
                ->from('notice_reads')
                ->whereColumn('notice_reads.notice_id', 'notices.id')
                ->where('notice_reads.user_id', $viewer->id))
            ->count();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(NoticeAttachment::class);
    }

    public function isImportant(): bool
    {
        return $this->is_urgent || in_array($this->priority, ['urgent', 'high'], true);
    }

    public function coverColumn(): ?string
    {
        return null;
    }

    public function galleryOwnerId(): ?int
    {
        return $this->issuer_id;
    }

    /**
     * A notice belongs to the office that issued it rather than to the person
     * who typed it, so anyone who may issue notices may also fix the picture
     * on one - including a notice a colleague published last term. A group
     * notice is the exception: it is the teacher's or the group's staff.
     */
    public function galleryEditableBy(User $user): bool
    {
        if ($this->isScoped()) {
            return $this->issuer_id === $user->id
                || ($this->group && $this->group->isStaff($user));
        }

        return $user->canIssueNotices();
    }
}
