<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\OpenAiCompatibleProvider;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The providers in config order, tried until one answers.
 *
 * Worth being precise about what this does and does not buy. It covers
 * an outage, a rate limit, a revoked key and a timeout - the whole class
 * of "this vendor is not answering right now". It does not improve a
 * poor answer, because nothing here can tell a poor answer from a good
 * one. Three vendors is resilience, not a committee.
 *
 * A provider with no key is skipped rather than counted as a failure, so
 * the chain can be developed against whichever key arrived first.
 */
class ProviderChain
{
    /** @var list<AiProvider>|null */
    private ?array $providers = null;

    /** @var array<string, string> reasons from the most recent call */
    private array $lastFailures = [];

    private ?string $lastProvider = null;

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     *
     * @throws AiUnavailable when every configured provider failed
     */
    public function chat(array $messages, array $tools = []): AiReply
    {
        $this->lastFailures = [];

        foreach ($this->providers() as $provider) {
            if (! $provider->configured()) {
                $this->lastFailures[$provider->name()] = 'no API key configured';

                continue;
            }

            try {
                $reply = $provider->chat($messages, $tools);
                $this->lastProvider = $provider->name();

                return $reply;
            } catch (Throwable $e) {
                $this->lastFailures[$provider->name()] = $e->getMessage();

                // One provider being down is ordinary and the next one is
                // about to be tried, so this is a warning and not an error.
                Log::warning('Assistant provider failed, falling through.', [
                    'provider' => $provider->name(),
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        throw new AiUnavailable($this->lastFailures);
    }

    /** Which provider answered most recently, for the audit trail. */
    public function lastProvider(): ?string
    {
        return $this->lastProvider;
    }

    /** @return array<string, string> */
    public function lastFailures(): array
    {
        return $this->lastFailures;
    }

    /** Whether any provider in the order has a key. */
    public function available(): bool
    {
        foreach ($this->providers() as $provider) {
            if ($provider->configured()) {
                return true;
            }
        }

        return false;
    }

    /** @return list<AiProvider> */
    private function providers(): array
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        $timeout = (int) config('ai.timeout', 20);
        $built = [];

        foreach ((array) config('ai.order', []) as $name) {
            $settings = config('ai.providers.'.$name);

            if (! is_array($settings)) {
                // A name in the order with no matching entry is a typo in
                // .env, which is worth saying out loud rather than silently
                // running with two providers when three were meant.
                Log::warning('Unknown assistant provider named in AI_PROVIDER_ORDER.', ['name' => $name]);

                continue;
            }

            $built[] = match ($settings['driver'] ?? 'openai') {
                'gemini' => new GeminiProvider(
                    $name,
                    $settings['key'] ?? null,
                    (string) $settings['base_url'],
                    (string) $settings['model'],
                    $timeout,
                ),
                default => new OpenAiCompatibleProvider(
                    $name,
                    $settings['key'] ?? null,
                    (string) $settings['base_url'],
                    (string) $settings['model'],
                    $timeout,
                ),
            };
        }

        return $this->providers = $built;
    }
}
