<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
