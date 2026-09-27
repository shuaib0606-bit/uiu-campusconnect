<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of conversation in a group.
 */
class GroupMessage extends Model
{
    protected $fillable = ['group_id', 'user_id', 'body', 'is_deleted'];

    protected function casts(): array
    {
        return ['is_deleted' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CommunityGroup::class, 'group_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Its own author, or anyone who runs the group.
     *
     * A teacher can clear something out of their own classroom; a student
     * can take back what they said. Nobody else.
     */
    public function deletableBy(?User $user): bool
    {
        if (! $user || $this->is_deleted) {
            return false;
        }

        return $user->id === $this->user_id
            || $user->isAdmin()
            || (bool) $this->group?->isStaff($user);
    }

    /**
     * The shape the browser receives, over the socket and over the
     * polling fallback alike - so one renderer draws both.
     *
     * @return array<string, mixed>
     */
    public function forBrowser(): array
    {
        return [
            'id' => $this->id,
            'body' => $this->is_deleted ? null : $this->body,
            'deleted' => $this->is_deleted,
            'user_id' => $this->user_id,
            'author' => $this->author?->full_name,
            'initials' => $this->author?->avatar_initials,
            'avatar' => $this->author?->avatar_url,
            'role' => $this->author?->role,
            'at' => $this->created_at?->toIso8601String(),
            'time' => $this->created_at?->format('g:i A'),
            'day' => $this->created_at?->format('D, M j'),
        ];
    }
}
