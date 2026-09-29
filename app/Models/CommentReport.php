<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentReport extends Model
{
    protected $fillable = ['comment_id', 'reporter_id', 'reason', 'details', 'status', 'reviewed_by', 'reviewed_at', 'moderator_note'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
