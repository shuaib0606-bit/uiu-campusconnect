<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventCheckin extends Model
{
    // Composite key (event_id, user_id); there is no surrogate id column.
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'event_checkins';

    protected $fillable = ['event_id', 'user_id'];

    protected $casts = ['checked_in_at' => 'datetime'];
}
