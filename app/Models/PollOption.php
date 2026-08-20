<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PollOption extends Model
{
    public $timestamps = false;
    protected $fillable = ['post_id', 'label', 'position'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }
}
