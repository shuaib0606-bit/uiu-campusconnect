<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostSave extends Model
{
    // Composite key (post_id, user_id); there is no surrogate id column.
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = ['post_id', 'user_id'];
}
