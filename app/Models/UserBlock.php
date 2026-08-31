<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBlock extends Model
{
    // Composite key (blocker_id, blocked_id); there is no surrogate id column.
    public $incrementing = false;
    protected $fillable = ['blocker_id', 'blocked_id'];
}
