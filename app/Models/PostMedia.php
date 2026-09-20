<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** One attachment on a post. */
class PostMedia extends Model
{
    public const IMAGE = 'image';

    public const VIDEO = 'video';

    /** A document: a PDF, a slide deck, a spreadsheet, an archive. */
    public const FILE = 'file';

    /** Documents are not public; see the migration that added the column. */
    public const PRIVATE_DISK = 'local';

    // Set explicitly; Eloquent would pluralise the class name to "post_medias".
    protected $table = 'post_media';

    /** The table stores created_at only. */
    public const UPDATED_AT = null;

    protected $fillable = ['post_id', 'path', 'disk', 'original_name', 'type', 'mime_type', 'size', 'position'];

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
        return $this->type === self::VIDEO;
    }

    public function isFile(): bool
    {
        return $this->type === self::FILE;
    }

    /**
     * Only meaningful for pictures and video, which live on the public disk.
     * A document has no public address on purpose - it is fetched through
     * PostController::downloadMedia, which checks who is asking.
     */
    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }

    public function sizeLabel(): string
    {
        $bytes = (int) $this->size;

        if ($bytes <= 0) {
            return '';
        }

        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1).' MB'
            : number_format(max($bytes, 1) / 1024, 1).' KB';
    }

    /** A short, human label for the kind of document this is. */
    public function kindLabel(): string
    {
        $extension = strtoupper(pathinfo((string) ($this->original_name ?: $this->path), PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : 'File';
    }
}
