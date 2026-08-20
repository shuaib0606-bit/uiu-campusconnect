<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostReaction extends Model
{
    // Composite-key junction table (post_id, user_id) — always query/update
    // through explicit where() clauses rather than find()/model->save().
    public $incrementing = false;
    protected $fillable = ['post_id', 'user_id', 'reaction'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
