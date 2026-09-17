<?php

namespace App\Models;

use App\Models\Concerns\HasGallery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LostFoundItem extends Model
{
    use HasGallery;

    protected $fillable = [
        'user_id', 'type', 'title', 'description', 'location', 'event_date',
        'photo_path', 'status', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Whoever marked the item reunited: the poster, or an administrator. */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(LostFoundClaim::class, 'item_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** The poster, or an administrator acting on their behalf. */
    public function canBeManagedBy(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $this->user_id === $user->id);
    }

    public function coverColumn(): ?string
    {
        return 'photo_path';
    }

    public function galleryOwnerId(): ?int
    {
        return $this->user_id;
    }
}
