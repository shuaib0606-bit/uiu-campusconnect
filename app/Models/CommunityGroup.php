<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityGroup extends Model
{
    protected $table = 'community_groups';
    protected $fillable = ['name', 'slug', 'description', 'cover_path', 'visibility', 'owner_id'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_group_members', 'group_id', 'user_id')->withPivot('role');
    }

    public function files(): HasMany
    {
        return $this->hasMany(CommunityGroupFile::class, 'group_id');
    }
}
