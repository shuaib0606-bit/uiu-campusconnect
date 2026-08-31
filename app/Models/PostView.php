<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostView extends Model
{
    // Composite key (post_id, user_id); there is no surrogate id column.
    public $incrementing = false;

    // The table carries seen_at instead of created_at/updated_at.
    public $timestamps = false;

    protected $fillable = ['post_id', 'user_id', 'seen_at'];

    protected function casts(): array
    {
        return ['seen_at' => 'datetime'];
    }
}
