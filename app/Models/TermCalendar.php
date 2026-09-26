<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of UIU's published academic calendars: one program, one term.
 * Filled by `php artisan uiu:import-calendars`.
 */
class TermCalendar extends Model
{
    protected $table = 'academic_calendars';

    protected $fillable = [
        'slug', 'program', 'term', 'term_kind', 'title', 'source_url', 'notes',
        'starts_on', 'ends_on', 'published_on', 'checksum', 'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'published_on' => 'date',
            'imported_at' => 'datetime',
        ];
    }

    public function dates(): HasMany
    {
        return $this->hasMany(CalendarDate::class, 'calendar_id')->orderBy('starts_on')->orderBy('position');
    }

    public function scopeProgram(Builder $query, string $program): Builder
    {
        return $query->where('program', $program);
    }

    /** "Summer 2026 Trimester". */
    public function label(): string
    {
        return trim($this->term.' '.ucfirst($this->term_kind));
    }

    /** Footnotes as paragraphs, for the full calendar page. */
    public function noteParagraphs(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\n{2,}|(?=\s\*{1,4}\s?[A-Z])/', (string) $this->notes))));
    }

    public function isRunning(): bool
    {
        $today = now()->startOfDay();

        return $this->starts_on && $this->ends_on
            && $this->starts_on->lessThanOrEqualTo($today)
            && $this->ends_on->greaterThanOrEqualTo($today);
    }
}
