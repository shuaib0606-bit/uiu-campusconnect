<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Superseded by CourseSection.
 *
 * This was the registrar typing a course code, room and seat count that became
 * an announcement and nothing else - no roster, no group, no enrolment. Those
 * are real records now, and /administration writes them instead.
 *
 * The model and its table are kept so that anything already entered is not
 * lost. Nothing reads them.
 *
 * @deprecated
 */
class SectionAnnouncement extends Model
{
    protected $fillable = [
        'course_code', 'section_name', 'title', 'schedule_pattern',
        'start_time', 'end_time', 'room', 'seat_limit', 'issuer_id',
    ];

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuer_id');
    }
}
