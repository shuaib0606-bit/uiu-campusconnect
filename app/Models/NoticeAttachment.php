<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoticeAttachment extends Model
{
    public $timestamps = false;

    protected $fillable = ['notice_id', 'file_path', 'original_name', 'mime_type', 'size', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class);
    }

    /** Lowercased file extension taken from the original upload name. */
    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    public function isImage(): bool
    {
        return in_array($this->extension(), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    /** Icon name from the shared ui-icon set for this file type. */
    public function iconName(): string
    {
        return match ($this->extension()) {
            'jpg', 'jpeg', 'png', 'gif', 'webp' => 'image',
            'xls', 'xlsx', 'csv' => 'table',
            default => 'file-text',
        };
    }

    /** Human readable size, e.g. "1.4 MB" or "812 KB". */
    public function humanSize(): string
    {
        $bytes = (int) $this->size;

        if ($bytes <= 0) {
            return '-';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        return number_format(max($bytes, 1) / 1024, 1).' KB';
    }
}
