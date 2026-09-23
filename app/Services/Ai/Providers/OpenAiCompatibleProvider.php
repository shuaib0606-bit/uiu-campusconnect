<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiReply;
use App\Services\Ai\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Grok and DeepSeek.
 *
 * Both publish an OpenAI-shaped /chat/completions endpoint, so one class
 * serves both and the only difference is the base URL, the key and the
 * model name. If a third vendor turns up speaking the same dialect it
 * needs a config entry and nothing else.
 */
class OpenAiCompatibleProvider implements AiProvider
{
    /**
     * Total seconds spent waiting out rate limits on one call, across
     * all retries. Beyond this the minute's budget is genuinely gone and
     * the next provider is the faster answer - and a student watching a
     * spinner has limits of their own.
     */
    private const MAX_RETRY_WAIT = 18.0;

    /**
     * Two retries rather than one, because the throttle hints seen in
     * practice are short - three and twelve seconds - and a second short
     * wait still beats failing the question.
     */
    private const MAX_RETRIES = 2;

    public function __construct(
        private readonly string $name,
        private readonly ?string $key,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeout,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function configured(): bool
    {
        return is_string($this->key) && trim($this->key) !== '';
    }

    public function chat(array $messages, array $tools = []): AiReply
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => config('ai.temperature'),
            'max_tokens' => config('ai.max_tokens'),
        ];

        if ($tools !== []) {
            $payload['tools'] = array_map(
                fn (array $tool) => ['type' => 'function', 'function' => $tool],
                $tools,
            );
            $payload['tool_choice'] = 'auto';
        }

        $response = $this->send($payload);

        if ($response->failed()) {
            // The body often carries the real reason (bad model name, no
            // credit, key revoked). Truncated because it can be an HTML
            // error page from a proxy rather than JSON.
            throw new RuntimeException(
                'HTTP '.$response->status().' '.mb_substr((string) $response->body(), 0, 300)
            );
        }

        $choice = $response->json('choices.0.message');

        if (! is_array($choice)) {
            throw new RuntimeException('the response had no message');
        }

        return new AiReply(
            text: (string) ($choice['content'] ?? ''),
            toolCalls: $this->readToolCalls($choice),
            provider: $this->name,
            model: $this->model,
        );
    }

    /**
     * Posts once, and waits out a rate limit rather than giving up on it.
     *
     * A free tier measured in tokens per minute throttles constantly, but
     * it also says how long to wait, and the wait is usually two to twelve
     * seconds - far less than falling through to a provider that may be
     * slower, or failing the question outright. Falling through is for a
     * provider that is broken; a provider saying "in four seconds" is
     * working, just busy.
     *
     * One retry only. If it throttles twice the minute really is spent,
     * and the next provider deserves its turn.
     */
    private function send(array $payload): \Illuminate\Http\Client\Response
    {
        $request = fn () => Http::withToken((string) $this->key)
            ->timeout($this->timeout)
            ->acceptJson()
            ->post(rtrim($this->baseUrl, '/').'/chat/completions', $payload);

        $response = $request();
        $spent = 0.0;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            if ($response->status() !== 429) {
                return $response;
            }

            $wait = $this->retryAfter($response);

            // No hint, or a wait longer than the budget left: the minute
            // is genuinely gone, so hand over rather than sit on it.
            if ($wait === null || $spent + $wait > self::MAX_RETRY_WAIT) {
                return $response;
            }

            usleep((int) ($wait * 1_000_000));
            $spent += $wait;

            $response = $request();
        }

        return $response;
    }

    /**
     * How long the vendor asked us to wait, in seconds, or null when that
     * is longer than it is worth blocking a web request for.
     *
     * Two places carry it: a Retry-After header, and - on Groq - a
     * sentence in the error body reading "Please try again in 3.8325s".
     */
    private function retryAfter(\Illuminate\Http\Client\Response $response): ?float
    {
        $seconds = null;

        if ($header = $response->header('Retry-After')) {
            $seconds = (float) $header;
        }

        if (! $seconds && preg_match('/try again in ([0-9.]+)s/i', (string) $response->body(), $match)) {
            $seconds = (float) $match[1];
        }

        // A tenth of a second of slack, because the server's clock and
        // ours are not the same clock.
        $seconds = $seconds ? $seconds + 0.1 : null;

        return ($seconds !== null && $seconds <= self::MAX_RETRY_WAIT) ? $seconds : null;
    }

    /**
     * Arguments arrive as a JSON string rather than an object. A model
     * that emits malformed JSON gets that call dropped instead of taking
     * the whole request down - it will usually manage it on the retry.
     *
     * @return list<array{id: string, name: string, arguments: array<string, mixed>}>
     */
    private function readToolCalls(array $message): array
    {
        $calls = [];

        foreach ($message['tool_calls'] ?? [] as $index => $call) {
            $name = $call['function']['name'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $arguments = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);

            $calls[] = [
                'id' => (string) ($call['id'] ?? 'call_'.$index),
                'name' => $name,
                'arguments' => is_array($arguments) ? $arguments : [],
            ];
        }

        return $calls;
    }
}
