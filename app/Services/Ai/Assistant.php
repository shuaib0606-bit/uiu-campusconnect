<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * One question in, one answer out.
 *
 * The loop is the standard tool-calling shape: send the conversation,
 * and if the model asks for tools, run them, append the results and send
 * it back. It stops when the model answers or when the round budget runs
 * out, whichever comes first - an unbounded loop here is an unbounded
 * bill.
 *
 * Note what this class does not do. It never decides whether a question
 * is allowed. That question is meaningless once the tools can only reach
 * what the asker could already see on the site: a student may ask for
 * anything, and what comes back is what they could have found by
 * clicking. Guarding the question would only teach people to rephrase it.
 */
class Assistant
{
    public function __construct(private readonly ProviderChain $chain) {}

    /**
     * @param  list<array{role: string, content: string, refs?: list<array>}>  $history  earlier turns, oldest first
     * @return array{
     *     answer: string,
     *     provider: ?string,
     *     model: ?string,
     *     tools_used: list<string>,
     *     redactions: list<string>,
     *     refs: list<array{label: string, link: string, note?: string}>,
     *     rounds: int
     * }
     *
     * @throws AiUnavailable
     */
    public function ask(User $user, string $question, array $history = []): array
    {
        $tools = new CampusTools($user);
        $guard = new OutputGuard($user);

        // The asker's own state, read fresh, so the most common personal
        // questions are answered without a single tool call.
        $snapshot = ContextSnapshot::for($user);

        $messages = [['role' => 'system', 'content' => SystemPrompt::for($user, $snapshot, References::forPrompt($history))]];

        foreach ($history as $turn) {
            $messages[] = [
                'role' => $turn['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => (string) $turn['content'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $question];

        $definitions = $tools->definitions();
        $used = [];

        // Every tool result this answer, kept to work out what it was about.
        $results = [];

        // Every email address a tool returned this turn. These are the only
        // third-party addresses the output guard will let through, because
        // they are the only ones the tools vouched for.
        $vouched = [];

        // Addresses in earlier answers already passed the guard once, so a
        // follow-up that repeats one without looking it up again keeps it.
        foreach ($history as $turn) {
            if ($turn['role'] === 'assistant') {
                preg_match_all('/[\w.+-]+@[\w-]+\.[\w.-]+/', (string) $turn['content'], $found);
                array_push($vouched, ...$found[0]);
            }
        }

        $maxRounds = (int) config('ai.max_tool_rounds', 4);
        $reply = null;

        for ($round = 1; $round <= $maxRounds; $round++) {
            $reply = $this->chain->chat($messages, $definitions);

            if (! $reply->wantsTools()) {
                break;
            }

            // The model's own turn has to go back in before the results,
            // or the next request has answers to calls it cannot see.
            $messages[] = [
                'role' => 'assistant',
                'content' => $reply->text,
                'tool_calls' => array_map(fn (array $call) => [
                    'id' => $call['id'],
                    'type' => 'function',
                    'function' => [
                        // An argument-less call must serialise as {} and not
                        // [], here as well as in the Gemini translation.
                        'name' => $call['name'],
                        'arguments' => json_encode(
                            $call['arguments'] === [] ? new \stdClass : (object) $call['arguments']
                        ),
                    ],
                ], $reply->toolCalls),
            ];

            foreach ($reply->toolCalls as $call) {
                $used[] = $call['name'];

                $result = $tools->run($call['name'], $call['arguments']);
                $results[] = $result;
                $content = $this->encode($result);

                preg_match_all('/[\w.+-]+@[\w-]+\.[\w.-]+/', $content, $found);
                array_push($vouched, ...$found[0]);

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'],
                    // Gemini matches tool results by name rather than id,
                    // so both travel with every result.
                    'name' => $call['name'],
                    'content' => $content,
                ];
            }

            if ($round === $maxRounds) {
                // Budget spent with the model still asking for more. One
                // last call with no tools offered forces it to answer from
                // what it already has rather than returning nothing.
                $messages[] = [
                    'role' => 'system',
                    'content' => 'No more lookups are available. Answer now using what you have, and say plainly if something could not be found.',
                ];

                $reply = $this->chain->chat($messages);
            }
        }

        $answer = trim((string) $reply?->text);

        if ($answer === '') {
            // A provider answered but said nothing. Rare, and not worth an
            // exception - the student gets an honest sentence instead.
            Log::warning('Assistant produced an empty answer.', [
                'user_id' => $user->id,
                'provider' => $reply?->provider,
                'tools_used' => $used,
            ]);

            $answer = 'I could not put together an answer for that. Try asking it a different way?';
        }

        $cleaned = $guard->clean($answer, array_values(array_unique($vouched)));

        return [
            'answer' => $cleaned['text'],
            'provider' => $reply?->provider,
            'model' => $reply?->model,
            'tools_used' => array_values(array_unique($used)),
            'redactions' => $cleaned['redactions'],
            'refs' => References::collect($results),
            'rounds' => $round ?? 1,
        ];
    }

    /** Tool results travel as JSON text. Unicode stays readable so Bangla survives the trip. */
    private function encode(array $result): string
    {
        return (string) json_encode(
            $result,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );
    }
}
