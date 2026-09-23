<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One thread of questions with the campus assistant.
 *
 * A conversation belongs to exactly one student and is never shared, so
 * every read of it is scoped by user_id. There is no "shared with" on
 * purpose: these threads contain whatever that student thought to ask,
 * which is nobody else's business.
 */
class AiConversation extends Model
{
    protected $fillable = ['user_id', 'title'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('id');
    }

    /** The opening question, shortened, so the sidebar reads like questions. */
    public static function titleFrom(string $question): string
    {
        $title = trim(preg_replace('/\s+/', ' ', strip_tags($question)) ?? '');

        return Str::limit($title === '' ? 'New conversation' : $title, 110);
    }

    /**
     * The last few turns, oldest first, in the shape the Assistant wants.
     *
     * Tool calls and their results are deliberately left out. Replaying
     * them would cost tokens on every turn to re-state facts the model
     * already summarised in its answer. What does carry over is each
     * answer's refs - the names and links its tools returned - which is
     * the small part a follow-up question actually needs.
     *
     * @return list<array{role: string, content: string, refs: list<array>}>
     */
    public function recentTurns(int $limit): array
    {
        return $this->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->latest('id')
            ->limit($limit * 2)
            ->get()
            ->sortBy('id')
            ->map(fn (AiMessage $message) => [
                'role' => $message->role,
                'content' => (string) $message->body,
                'refs' => $message->refs ?? [],
            ])
            ->values()
            ->all();
    }
}
