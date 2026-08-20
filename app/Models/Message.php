<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'sender_id', 'receiver_id', 'body', 'attachment_path', 'attachment_type',
        'is_deleted_by_sender', 'is_deleted_by_receiver', 'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_deleted_by_sender' => 'boolean',
            'is_deleted_by_receiver' => 'boolean',
            'is_read' => 'boolean',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
