<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostShare extends Model
{
    public $timestamps = false;
    protected $fillable = ['post_id', 'user_id', 'created_at'];
}
