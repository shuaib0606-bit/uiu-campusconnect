<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBlock extends Model
{
    // Composite-key junction table (blocker_id, blocked_id) — no surrogate id
    // column exists, so always query/delete through explicit where()
    // clauses rather than find()/model->delete() on a fetched instance.
    public $incrementing = false;
    protected $fillable = ['blocker_id', 'blocked_id'];
}
