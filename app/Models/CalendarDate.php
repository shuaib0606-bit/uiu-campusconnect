<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row of an academic calendar: a day or a run of days, and what happens then. */
class CalendarDate extends Model
{
    protected $table = 'academic_calendar_events';

    protected $fillable = [
        'calendar_id', 'starts_on', 'ends_on', 'title', 'type', 'important', 'note', 'date_text', 'position',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'important' => 'boolean',
        ];
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(TermCalendar::class, 'calendar_id');
    }
}
