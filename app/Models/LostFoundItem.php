<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LostFoundItem extends Model
{
    protected $fillable = ['user_id', 'type', 'title', 'description', 'location', 'photo_path', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(LostFoundClaim::class, 'item_id');
    }
}
