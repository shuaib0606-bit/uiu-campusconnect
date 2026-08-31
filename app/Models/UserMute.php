<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMute extends Model
{
    // Composite key (muter_id, muted_id); there is no surrogate id column.
    public $incrementing = false;
    protected $fillable = ['muter_id', 'muted_id', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
