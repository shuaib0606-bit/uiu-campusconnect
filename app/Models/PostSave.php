<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostSave extends Model
{
    // Composite-key junction table (post_id, user_id) — no surrogate id
    // column exists, so always query/delete through explicit where()
    // clauses rather than find()/model->delete() on a fetched instance.
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = ['post_id', 'user_id'];
}
