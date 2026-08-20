<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommentLike extends Model
{
    // Composite-key junction table (comment_id, user_id) — no surrogate id
    // column exists, so always query/delete through explicit where()
    // clauses rather than find()/model->delete() on a fetched instance.
    public $incrementing = false;
    protected $fillable = ['comment_id', 'user_id'];
}
