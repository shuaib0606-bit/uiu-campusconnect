<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiReply;
use App\Services\Ai\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Gemini.
 *
 * The odd one out. Gemini has two roles rather than four (user and
 * model), keeps the system prompt in its own field, carries tool results
 * as parts of a user turn, and gives function calls no id. All of that is
 * translated here so nothing above this class has to know.
 */
class GeminiProvider implements AiProvider
{
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
        [$system, $contents] = $this->translate($messages);

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => config('ai.temperature'),
                'maxOutputTokens' => config('ai.max_tokens'),
            ],
        ];

        if ($system !== '') {
            $payload['system_instruction'] = ['parts' => [['text' => $system]]];
        }

        if ($tools !== []) {
            $payload['tools'] = [['functionDeclarations' => $tools]];
        }

        $url = rtrim($this->baseUrl, '/').'/models/'.$this->model.':generateContent';

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            // The key goes in a header rather than the query string, so it
            // cannot end up in an access log.
            ->withHeaders(['x-goog-api-key' => (string) $this->key])
            ->post($url, $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'HTTP '.$response->status().' '.mb_substr((string) $response->body(), 0, 300)
            );
        }

        $parts = $response->json('candidates.0.content.parts');

        if (! is_array($parts)) {
            // A candidate with no parts usually means Gemini's own safety
            // filter stopped it. Say so rather than returning silence.
            $reason = $response->json('candidates.0.finishReason')
                ?? $response->json('promptFeedback.blockReason');

            throw new RuntimeException('no content returned'.($reason ? ' ('.$reason.')' : ''));
        }

        $text = '';
        $calls = [];

        foreach ($parts as $index => $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }

            if (isset($part['functionCall']['name'])) {
                $calls[] = [
                    // Gemini issues no call id, so one is invented; it only
                    // has to be unique within this turn.
                    'id' => 'gem_'.$index,
                    'name' => (string) $part['functionCall']['name'],
                    'arguments' => (array) ($part['functionCall']['args'] ?? []),
                ];
            }
        }

        return new AiReply(
            text: $text,
            toolCalls: $calls,
            provider: $this->name,
            model: $this->model,
        );
    }

    /**
     * OpenAI-shaped messages into Gemini's contents array.
     *
     * @return array{0: string, 1: list<array<string, mixed>>}
     */
    private function translate(array $messages): array
    {
        $system = '';
        $contents = [];

        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $content = (string) ($message['content'] ?? '');

            if ($role === 'system') {
                $system .= ($system === '' ? '' : "\n\n").$content;

                continue;
            }

            if ($role === 'tool') {
                // A tool result is a user turn carrying a functionResponse.
                // The name must match the declaration or Gemini rejects it.
                $contents[] = [
                    'role' => 'user',
                    'parts' => [[
                        'functionResponse' => [
                            'name' => (string) ($message['name'] ?? 'tool'),
                            'response' => ['result' => $content],
                        ],
                    ]],
                ];

                continue;
            }

            if ($role === 'assistant') {
                $parts = [];

                foreach ($message['tool_calls'] ?? [] as $call) {
                    $arguments = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);
                    $arguments = is_array($arguments) ? $arguments : [];

                    $parts[] = ['functionCall' => [
                        'name' => (string) ($call['function']['name'] ?? ''),
                        // An empty PHP array encodes as [] - a JSON list -
                        // but Gemini's protobuf wants an object here and
                        // rejects the whole request with "Proto field is
                        // not repeating". Half the tools take no arguments,
                        // so without this cast the fallback provider fails
                        // on almost every question.
                        'args' => $arguments === [] ? new \stdClass : (object) $arguments,
                    ]];
                }

                if ($content !== '') {
                    $parts[] = ['text' => $content];
                }

                if ($parts !== []) {
                    $contents[] = ['role' => 'model', 'parts' => $parts];
                }

                continue;
            }

            $contents[] = ['role' => 'user', 'parts' => [['text' => $content]]];
        }

        return [$system, $contents];
    }
}
