<?php

namespace App\Services\Ai;

/**
 * One turn back from a provider.
 *
 * Either the model answered, or it asked to call tools, or both. Both at
 * once is rare but legal, and the assistant handles it by running the
 * tools and ignoring the partial text.
 */
class AiReply
{
    /**
     * @param  list<array{id: string, name: string, arguments: array<string, mixed>}>  $toolCalls
     */
    public function __construct(
        public readonly string $text = '',
        public readonly array $toolCalls = [],
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
    ) {}

    public function wantsTools(): bool
    {
        return $this->toolCalls !== [];
    }
}
