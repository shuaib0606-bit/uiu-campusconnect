<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = [
        'user_id', 'body', 'post_type', 'media_type', 'media_path', 'media_thumb_path',
        'visibility', 'is_deleted', 'deleted_reason',
    ];

    protected function casts(): array
    {
        return ['is_deleted' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id');
    }

    public function allComments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(PostReaction::class);
    }

    public function saves(): HasMany
    {
        return $this->hasMany(PostSave::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function pollOptions(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('position');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_deleted', false);
    }
}
