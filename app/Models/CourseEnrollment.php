<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseEnrollment extends Model
{
    public const STUDENT = 'student';

    public const TEACHER = 'teacher';

    public const ASSISTANT = 'assistant';

    public const ACTIVE = 'active';

    public const DROPPED = 'dropped';

    public $timestamps = false;

    protected $fillable = [
        'section_id', 'user_id', 'role', 'status', 'source', 'enrolled_at', 'dropped_at',
    ];

    protected function casts(): array
    {
        return ['enrolled_at' => 'datetime', 'dropped_at' => 'datetime'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'section_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Teachers and assistants run the class; students attend it. */
    public function isStaff(): bool
    {
        return in_array($this->role, [self::TEACHER, self::ASSISTANT], true);
    }
}
