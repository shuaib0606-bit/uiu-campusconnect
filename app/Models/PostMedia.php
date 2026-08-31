<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** One attachment on a post. */
class PostMedia extends Model
{
    // Set explicitly; Eloquent would pluralise the class name to "post_medias".
    protected $table = 'post_media';

    /** The table stores created_at only. */
    public const UPDATED_AT = null;

    protected $fillable = ['post_id', 'path', 'type', 'mime_type', 'size', 'position'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'position' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }
}
