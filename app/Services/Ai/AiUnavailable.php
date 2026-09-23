<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Every provider in the chain failed, or none was configured.
 *
 * Carries the per-provider reasons so the log says which one refused and
 * why, rather than leaving a blank where a diagnosis should be. What the
 * student sees is a plain sentence; this is for whoever reads the log.
 */
class AiUnavailable extends RuntimeException
{
    /** @param array<string, string> $reasons keyed by provider name */
    public function __construct(public readonly array $reasons = [])
    {
        $summary = $reasons === []
            ? 'no provider is configured'
            : implode('; ', array_map(
                fn ($name, $why) => "{$name}: {$why}",
                array_keys($reasons),
                $reasons,
            ));

        parent::__construct('The assistant could not reach a provider ('.$summary.').');
    }

    /**
     * Whether the leading provider was merely busy rather than broken.
     *
     * Worth separating, because the two deserve different words. "Could
     * not be reached" reads like something is wrong with the site; a rate
     * limit means it is working and the free tier's minute is spent, and
     * waiting really does fix it.
     */
    public function isRateLimit(): bool
    {
        foreach ($this->reasons as $reason) {
            if (str_contains($reason, '429') || stripos($reason, 'rate limit') !== false) {
                return true;
            }
        }

        return false;
    }
}
