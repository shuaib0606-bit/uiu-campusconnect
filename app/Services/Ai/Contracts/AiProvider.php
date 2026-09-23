<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\AiReply;

/**
 * One language model vendor.
 *
 * Messages are passed in OpenAI's shape because two of the three
 * providers speak it natively; the odd one out translates on the way in
 * and on the way out. Nothing above this interface knows which vendor
 * answered.
 */
interface AiProvider
{
    /** The key this provider is listed under in config/ai.php. */
    public function name(): string;

    /** Whether a key is present. An unconfigured provider is skipped, not failed. */
    public function configured(): bool;

    /**
     * @param  list<array<string, mixed>>  $messages  system / user / assistant / tool
     * @param  list<array<string, mixed>>  $tools  JSON-schema function definitions
     *
     * @throws \RuntimeException on timeout, rate limit, or a refusal by the vendor
     */
    public function chat(array $messages, array $tools = []): AiReply;
}
