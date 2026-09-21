<?php

namespace App\Models;

use App\Models\Concerns\HasGallery;
use App\Services\SocialRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    use HasGallery;

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

    public function panelChanges(): HasMany
    {
        return $this->hasMany(ClubPanelChange::class);
    }

    public function roleRequests(): HasMany
    {
        return $this->hasMany(ClubRoleRequest::class);
    }

    public function membershipRequests(): HasMany
    {
        return $this->hasMany(ClubMembershipRequest::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function coverColumn(): ?string
    {
        return 'cover_path';
    }

    public function galleryOwnerId(): ?int
    {
        return $this->moderator_id;
    }

    /**
     * The club's banner and logo are its identity, not the moderator's
     * property, so the student panel maintains them too - they are the ones
     * who make the posters. A super administrator and the faculty moderator
     * can always step in.
     */
    public function galleryEditableBy(User $user): bool
    {
        return $this->moderator_id === $user->id
            || SocialRules::isClubOfficer($user, $this);
    }
}
