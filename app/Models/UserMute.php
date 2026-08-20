<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMute extends Model
{
    // Composite-key junction table (muter_id, muted_id) — no surrogate id
    // column exists, so always query/delete through explicit where()
    // clauses rather than find()/model->delete() on a fetched instance.
    public $incrementing = false;
    protected $fillable = ['muter_id', 'muted_id'];
}
