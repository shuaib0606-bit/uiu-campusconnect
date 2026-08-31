<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    protected $fillable = [
        'title', 'description', 'category', 'poster_path', 'start_datetime', 'end_datetime',
        'location', 'registration_url', 'capacity', 'organizer_user_id', 'club_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_user_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function registrants(): BelongsToMany
    {
        // The pivot has created_at but no updated_at, so withTimestamps() cannot be used.
        return $this->belongsToMany(User::class, 'event_registrations')->withPivot('created_at');
    }

    public function interested(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_interests')->withPivot('created_at');
    }

    public function checkins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_checkins')->withPivot('checked_in_at');
    }
}
