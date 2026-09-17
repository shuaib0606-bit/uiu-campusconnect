<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/** One image attached to an event, listing, club or notice. */
class Media extends Model
{
    // Set explicitly; Eloquent would pluralise the class name to "medias".
    protected $table = 'media';

    /** The table stores created_at only. */
    public const UPDATED_AT = null;

    protected $fillable = ['mediable_type', 'mediable_id', 'path', 'mime_type', 'size', 'caption', 'position', 'uploaded_by'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'position' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }
}
