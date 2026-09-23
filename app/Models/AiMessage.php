<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One turn, and one row of the audit trail.
 *
 * The columns after `body` are what make the feature's safety claims
 * checkable rather than asserted: which vendor answered, which tools it
 * called to get there, and whether the output guard had to remove
 * anything on the way out. A redaction recorded here means a layer
 * further up did not hold and wants looking at.
 */
class AiMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'conversation_id', 'user_id', 'role', 'body',
        'provider', 'model', 'tools_used', 'refs', 'redactions', 'rounds', 'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'tools_used' => 'array',
            'refs' => 'array',
            'redactions' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFromAssistant(): bool
    {
        return $this->role === 'assistant';
    }
}
