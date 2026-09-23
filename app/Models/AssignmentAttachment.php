<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A handout attached to an assignment: template, example, rubric.
 *
 * Readable by anyone in the class, which is the whole point of it - and
 * the one line that separates this from a submission.
 */
class AssignmentAttachment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'assignment_id', 'uploaded_by', 'file_path',
        'original_name', 'size', 'mime', 'label',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Everyone in the class, because a handout nobody can open is not a handout. */
    public function readableBy(?User $user): bool
    {
        return $user !== null
            && ($user->isAdmin() || (bool) $this->assignment?->group?->hasMember($user));
    }

    public function sizeLabel(): string
    {
        $bytes = (int) $this->size;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        return $bytes > 0 ? max(1, (int) round($bytes / 1024)).' KB' : '';
    }
}
