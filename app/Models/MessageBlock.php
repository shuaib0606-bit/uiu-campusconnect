<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageBlock extends Model
{
    protected $fillable = ['blocker_id', 'blocked_id'];
}
