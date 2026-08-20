<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PollVote extends Model
{
    protected $fillable = ['poll_option_id', 'post_id', 'user_id'];
}
