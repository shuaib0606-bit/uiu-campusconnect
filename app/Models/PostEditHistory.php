<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostEditHistory extends Model
{
    public $timestamps = false;
    protected $fillable = ['post_id', 'previous_body', 'edited_at'];
}
