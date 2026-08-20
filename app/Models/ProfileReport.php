<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileReport extends Model
{
    protected $fillable = ['reported_user_id', 'reporter_id', 'reason', 'details', 'status', 'reviewed_by', 'reviewed_at', 'moderator_note'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
