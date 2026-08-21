<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notice extends Model
{
    protected $fillable = [
        'title', 'body', 'notice_type', 'priority', 'audience',
        'department', 'batch', 'semester', 'is_urgent', 'issuer_id', 'status',
    ];

    protected function casts(): array
    {
        return ['is_urgent' => 'boolean'];
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuer_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(NoticeAttachment::class);
    }

    public function isImportant(): bool
    {
        return $this->is_urgent || in_array($this->priority, ['urgent', 'high'], true);
    }
}
