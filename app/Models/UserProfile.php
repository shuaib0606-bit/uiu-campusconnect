<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = [
        'user_id', 'bio', 'profile_photo_path', 'cover_photo_path', 'room', 'pabx', 'phone',
        'uiu_profile_url', 'uiu_synced_at', 'uiu_room', 'uiu_pabx',
        'room_visibility', 'pabx_visibility', 'office_hours_visibility', 'courses_visibility', 'research_visibility',
        'office_hours', 'skills', 'interests', 'courses_taught', 'research_interests', 'academic_resources',
        'privacy_level', 'show_email', 'show_student_id', 'show_phone', 'show_friend_list',
    ];

    protected function casts(): array
    {
        return [
            'show_email' => 'boolean',
            'show_student_id' => 'boolean',
            'show_phone' => 'boolean',
            'show_friend_list' => 'boolean',
            'uiu_synced_at' => 'datetime',
        ];
    }

    /** The detail => visibility column pairs a faculty member controls. */
    public const FIELD_VISIBILITY = [
        'room' => 'room_visibility',
        'pabx' => 'pabx_visibility',
        'office_hours' => 'office_hours_visibility',
        'courses_taught' => 'courses_visibility',
        'research_interests' => 'research_visibility',
    ];

    /** Whether this viewer may see one of those details. */
    public function shows(string $field, ?User $viewer): bool
    {
        $column = self::FIELD_VISIBILITY[$field] ?? null;

        return $column === null || \App\Support\Visibility::allows($this->{$column}, $viewer, (int) $this->user_id);
    }

    /** The value of a detail if this viewer may see it, otherwise null. */
    public function visible(string $field, ?User $viewer): ?string
    {
        return $this->shows($field, $viewer) ? ($this->{$field} ?: null) : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
