<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's hand-in for one assignment.
 *
 * The file itself lives on the private disk and is only ever served
 * through a controller that checks who is asking, the same way group
 * materials are. A submission is readable by the student who wrote it
 * and by the staff of the class - never by the rest of the group, since
 * classmates reading each other's work is not a feature.
 */
class AssignmentSubmission extends Model
{
    protected $fillable = [
        'assignment_id', 'user_id', 'file_path', 'original_name',
        'size', 'mime', 'note', 'is_late', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_late' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function readableBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->id === $this->user_id
            || $user->isAdmin()
            || (bool) $this->assignment?->group?->isStaff($user);
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
