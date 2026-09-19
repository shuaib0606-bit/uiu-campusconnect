<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One proposed handover of a club's student panel.
 *
 * Nothing changes while it is pending. On approval the club's designations are
 * replaced wholesale by the rows in club_panel_members.
 */
class ClubPanelChange extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    /** A panel has to have one of these, and only one. */
    public const PRESIDENT = 'president';

    protected $fillable = [
        'club_id', 'proposed_by', 'note', 'status',
        'decision_note', 'decided_by', 'decided_at', 'previous_panel',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'previous_panel' => 'array'];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function panelMembers(): HasMany
    {
        return $this->hasMany(ClubPanelMember::class, 'panel_change_id')->orderBy('position');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function statusLabel(): string
    {
        return ucfirst($this->status);
    }
}
