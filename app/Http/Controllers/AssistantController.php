<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\Assistant;
use App\Services\Ai\ProviderChain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;

/**
 * The campus assistant's one page and its one write endpoint.
 *
 * Every read is scoped by the signed-in user's id rather than trusting
 * the conversation id in the URL, so asking for somebody else's thread
 * returns a 404 and not their questions.
 */
class AssistantController extends Controller
{
    /** How many conversations the list shows, newest first. */
    private const LIST_LIMIT = 300;

    public function index(Request $request, ?AiConversation $conversation = null)
    {
        $user = $request->user();

        // Route model binding resolves by id alone, which would hand over
        // any conversation to anyone who guessed a number. Ownership is
        // checked before anything is read.
        if ($conversation && (int) $conversation->user_id !== (int) $user->id) {
            abort(404);
        }

        // Every conversation is kept until its owner deletes it, and the
        // list is what lets them go back to one and carry on.
        $conversations = AiConversation::where('user_id', $user->id)
            ->latest('updated_at')
            ->limit(self::LIST_LIMIT)
            ->get(['id', 'title', 'updated_at']);

        if ($conversation && ! $conversations->contains('id', $conversation->id)) {
            $conversations->push($conversation);
        }

        $messages = $conversation
            ? $conversation->messages()->get()->map(fn (AiMessage $message) => [
                'id' => $message->id,
                'role' => $message->role,
                'body' => $message->body,
                'tools_used' => $message->tools_used ?? [],
                'at' => $message->created_at?->toDayDateTimeString(),
            ])->values()
            : collect();

        return Inertia::render('Assistant/Index', [
            'conversationId' => $conversation?->id,
            'messages' => $messages,
            'conversationTitle' => $conversation?->title,
            'conversations' => $conversations->map(fn (AiConversation $row) => [
                'id' => $row->id,
                'title' => $row->title,
                'url' => route('assistant.show', $row, false),
                'updated' => $row->updated_at?->toIso8601String(),
                'at' => $row->updated_at?->diffForHumans(),
            ])->values(),
            'available' => (bool) config('ai.enabled') && app(ProviderChain::class)->available(),
            'personaName' => config('ai.persona_name'),
            'suggestions' => $this->suggestions(),
            'maxLength' => (int) config('ai.max_question_length'),
        ]);
    }

    public function ask(Request $request, Assistant $assistant)
    {
        $user = $request->user();

        $data = $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:'.config('ai.max_question_length')],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        if (! config('ai.enabled')) {
            return back()->with('error', 'The assistant is switched off.');
        }

        if ($problem = $this->throttle($user->id)) {
            return back()->with('error', $problem);
        }

        $conversation = $this->conversationFor($user->id, $data['conversation_id'] ?? null, $data['question']);
        $history = $conversation->recentTurns((int) config('ai.history_turns', 8));

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'user',
            'body' => $data['question'],
        ]);

        $startedAt = microtime(true);

        try {
            $result = $assistant->ask($user, $data['question'], $history);
        } catch (AiUnavailable $e) {
            // The question is already stored, so the student can retry it
            // without retyping once a provider comes back.
            Log::error('Assistant unavailable.', ['user_id' => $user->id, 'reason' => $e->getMessage()]);

            $conversation->touch();

            return back()->with('error', $e->isRateLimit()
                ? 'The assistant is busy right now - that is the free tier\'s limit for this minute. Wait about a minute and ask again.'
                : 'The assistant could not be reached just now. Please try again in a moment.');
        }

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'assistant',
            'body' => $result['answer'],
            'provider' => $result['provider'],
            'model' => $result['model'],
            'tools_used' => $result['tools_used'],
            'refs' => $result['refs'],
            'redactions' => $result['redactions'],
            'rounds' => $result['rounds'],
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        $conversation->touch();

        return redirect()->route('assistant.show', $conversation);
    }

    /** A clearer name for a conversation than its first question. */
    public function rename(Request $request, AiConversation $conversation)
    {
        if ((int) $conversation->user_id !== (int) $request->user()->id) {
            abort(404);
        }

        $data = $request->validate(['title' => ['required', 'string', 'max:110']]);

        // Renaming is not activity: the conversation keeps its place in the list.
        $conversation->timestamps = false;
        $conversation->forceFill(['title' => AiConversation::titleFrom($data['title'])])->save();

        return back();
    }

    public function destroy(Request $request, AiConversation $conversation)
    {
        if ((int) $conversation->user_id !== (int) $request->user()->id) {
            abort(404);
        }

        DB::transaction(fn () => $conversation->delete());

        // Deleting another conversation from the list keeps the open one open.
        return $request->boolean('stay')
            ? back()->with('success', 'Conversation deleted.')
            : redirect()->route('assistant.index')->with('success', 'Conversation deleted.');
    }

    /** Returns a message to show the student, or null when they are within both limits. */
    private function throttle(int $userId): ?string
    {
        $limits = (array) config('ai.rate_limit');

        if (RateLimiter::tooManyAttempts('ai:min:'.$userId, (int) $limits['per_minute'])) {
            $seconds = RateLimiter::availableIn('ai:min:'.$userId);

            return 'That is a lot of questions at once. Try again in '.$seconds.' seconds.';
        }

        if (RateLimiter::tooManyAttempts('ai:day:'.$userId, (int) $limits['per_day'])) {
            return 'You have reached the daily limit for the assistant. It resets tomorrow.';
        }

        RateLimiter::hit('ai:min:'.$userId, 60);
        RateLimiter::hit('ai:day:'.$userId, 86400);

        return null;
    }

    private function conversationFor(int $userId, ?int $id, string $question): AiConversation
    {
        if ($id) {
            $existing = AiConversation::where('user_id', $userId)->find($id);

            if ($existing) {
                return $existing;
            }
        }

        return AiConversation::create([
            'user_id' => $userId,
            'title' => AiConversation::titleFrom($question),
        ]);
    }

    /**
     * Starting points, chosen to show the range: the asker's own day, a
     * class, a person, the campus, and how the site works. A teacher gets
     * the questions a teacher asks.
     *
     * @return list<string>
     */
    private function suggestions(): array
    {
        if (request()->user()?->role === 'faculty') {
            return [
                'Who has not handed in the latest assignment?',
                'Which of my classes have the chat open?',
                'Any unread notices or messages for me?',
                'What is on the academic calendar next?',
                'How do I set an assignment with a handout?',
            ];
        }

        return [
            'How many assignments do I have due?',
            'Which of my classes have the chat open?',
            'Who teaches my courses, and what are their emails?',
            'What is happening on campus this week?',
            'When is the next shuttle to campus?',
        ];
    }
}
