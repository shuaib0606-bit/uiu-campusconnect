<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One section of one course in one trimester: CSE 2213 Section C, Fall 2026.
 * Sections rather than courses, because Section A and Section C are two
 * different rooms of people who should not share a wall.
 */
class CourseSection extends Model
{
    protected $fillable = [
        'trimester_id', 'course_code', 'course_title', 'section_name',
        'schedule_pattern', 'start_time', 'end_time', 'room', 'seat_limit',
        'source', 'external_id',
    ];

    public function trimester(): BelongsTo
    {
        return $this->belongsTo(Trimester::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class, 'section_id');
    }

    /** The group that stands for this section. */
    public function group(): HasOne
    {
        return $this->hasOne(CommunityGroup::class, 'source_id')
            ->where('kind', CommunityGroup::KIND_COURSE);
    }

    public function label(): string
    {
        return trim($this->course_code.' '.$this->section_name);
    }

    public function title(): string
    {
        return $this->label().' - '.$this->course_title;
    }

    /** The person listed as teaching it, if the import named one. */
    public function teacher(): ?User
    {
        return $this->enrollments()
            ->where('role', CourseEnrollment::TEACHER)
            ->where('status', CourseEnrollment::ACTIVE)
            ->with('user')
            ->first()?->user;
    }

    public function scheduleLine(): string
    {
        $parts = array_filter([
            $this->schedule_pattern,
            $this->start_time ? substr((string) $this->start_time, 0, 5).'-'.substr((string) $this->end_time, 0, 5) : null,
            $this->room,
        ]);

        return $parts ? implode(' · ', $parts) : 'Schedule not published';
    }
}
