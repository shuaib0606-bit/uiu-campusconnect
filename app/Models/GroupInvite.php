<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An invitation to join a group. The person is not a member until they
 * accept it on their Applications page.
 */
class GroupInvite extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['group_id', 'user_id', 'invited_by', 'status', 'responded_at'];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CommunityGroup::class, 'group_id');
    }

    /** The person being invited. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PENDING => 'Waiting for your answer',
            self::ACCEPTED => 'Accepted',
            self::DECLINED => 'Declined',
            self::CANCELLED => 'Withdrawn',
            default => ucfirst((string) $this->status),
        };
    }
}
