<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LostFoundClaim extends Model
{
    protected $fillable = ['item_id', 'user_id', 'message', 'status'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(LostFoundItem::class, 'item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
