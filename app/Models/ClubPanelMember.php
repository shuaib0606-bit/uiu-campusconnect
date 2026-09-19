<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One seat on a proposed panel: a person and the designation they would hold. */
class ClubPanelMember extends Model
{
    public $timestamps = false;

    protected $fillable = ['panel_change_id', 'user_id', 'club_role', 'position'];

    public function change(): BelongsTo
    {
        return $this->belongsTo(ClubPanelChange::class, 'panel_change_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
