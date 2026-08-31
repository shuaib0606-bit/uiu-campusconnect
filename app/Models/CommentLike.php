<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommentLike extends Model
{
    // Composite key (comment_id, user_id); there is no surrogate id column.
    public $incrementing = false;

    // A row holds a full reaction (like, love, helpful, question), not just a like.
    protected $fillable = ['comment_id', 'user_id', 'reaction'];
}
