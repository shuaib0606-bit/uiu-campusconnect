<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = [
        'user_id', 'bio', 'profile_photo_path', 'cover_photo_path', 'room', 'phone',
        'office_hours', 'skills', 'interests', 'courses_taught', 'research_interests',
        'privacy_level', 'show_email', 'show_phone', 'show_friend_list',
    ];

    protected function casts(): array
    {
        return [
            'show_email' => 'boolean',
            'show_phone' => 'boolean',
            'show_friend_list' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
