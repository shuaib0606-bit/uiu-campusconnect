<?php

namespace App\Models;

use App\Support\Notifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    // The table has created_at only; App\Support\Notifier sets it on every write.
    public $timestamps = false;

    protected $fillable = ['user_id', 'actor_id', 'type', 'entity_type', 'entity_id', 'message', 'group_count', 'is_read', 'created_at'];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'group_count' => 'integer',
            // Cast explicitly: with $timestamps = false, Eloquent does not.
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    /** More than one person is behind this row ("... and 4 others"). */
    public function isAggregated(): bool
    {
        return (int) $this->group_count > 1;
    }

    /** Icon for the notification row, keyed on the notification type. */
    public function iconName(): string
    {
        return match ($this->type) {
            Notifier::POST_REACTION, Notifier::COMMENT_REACTION => 'heart',
            Notifier::POST_COMMENT, Notifier::COMMENT_REPLY, Notifier::ALSO_COMMENTED => 'comment',
            Notifier::MENTION => 'tag',
            Notifier::FOLLOW => 'user-plus',
            Notifier::FRIEND_REQUEST => 'user-plus',
            Notifier::FRIEND_ACCEPT => 'handshake',
            Notifier::NOTICE => 'megaphone',
            Notifier::EVENT => 'calendar',
            Notifier::SYSTEM => 'shield',
            Notifier::GROUP_INVITE, Notifier::GROUP_INVITE_ACCEPTED => 'user-plus',
            Notifier::GROUP_POST_PENDING, Notifier::GROUP_POST_REVIEWED => 'users',
            default => $this->iconForEntity(),
        };
    }

    /** Icon fallback for rows with an unrecognised type. */
    protected function iconForEntity(): string
    {
        return match ($this->entity_type) {
            'post', 'comment' => 'comment',
            'event' => 'calendar',
            'notice' => 'megaphone',
            'message' => 'message',
            'club' => 'users',
            'user', 'friendship', 'connection' => 'users',
            default => 'bell',
        };
    }

    /** Short label for the grouped heading and accent colour on the row. */
    public function category(): string
    {
        return match ($this->type) {
            Notifier::NOTICE, Notifier::SYSTEM => 'campus',
            Notifier::EVENT => 'events',
            Notifier::FOLLOW, Notifier::FRIEND_REQUEST, Notifier::FRIEND_ACCEPT => 'people',
            Notifier::MENTION => 'mentions',
            default => 'activity',
        };
    }
}
