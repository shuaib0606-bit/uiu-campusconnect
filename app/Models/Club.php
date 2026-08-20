<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    protected $fillable = [
        'name', 'slug', 'category', 'description', 'logo_path', 'cover_path',
        'meeting_time', 'meeting_place', 'moderator_id', 'status',
    ];

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'club_members')->withPivot(['club_role', 'can_post_events']);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function membershipRequests(): HasMany
    {
        return $this->hasMany(ClubMembershipRequest::class);
    }
}
