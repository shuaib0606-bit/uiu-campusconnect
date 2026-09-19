<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Two things share this table, because they are the same shape: a heading, an
 * optional body and an optional attachment.
 *
 * - material : slides, a reading, a lab manual. Usually a file.
 * - notice   : an announcement from whoever runs the room. Often words only.
 *
 * They are kept out of the wall on purpose. A class notice that scrolls away
 * under conversation is a notice nobody reads.
 */
class CommunityGroupFile extends Model
{
    public const MATERIAL = 'material';

    public const NOTICE = 'notice';

    public $timestamps = false;

    protected $fillable = [
        'group_id', 'user_id', 'kind', 'title', 'body',
        'file_path', 'original_name', 'size', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CommunityGroup::class, 'group_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function hasFile(): bool
    {
        return filled($this->file_path);
    }

    public function sizeLabel(): string
    {
        $bytes = (int) $this->size;

        if (! $this->hasFile() || $bytes <= 0) {
            return '';
        }

        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1).' MB'
            : number_format(max($bytes, 1) / 1024, 1).' KB';
    }
}
