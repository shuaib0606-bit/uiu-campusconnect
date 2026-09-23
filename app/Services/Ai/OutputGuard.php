<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * The last check before an answer reaches the student.
 *
 * This is the third layer and the weakest one, which is worth saying
 * plainly rather than letting it look like the defence. The real defence
 * is CampusTools: the assistant cannot leak a password hash because no
 * tool selects the column. This class exists for the case where that
 * reasoning is wrong somewhere - a tool added later that returns more
 * than it should, a field that grows a sensitive meaning, a model that
 * echoes back part of a prompt.
 *
 * A guard you are relying on is a design failure. A guard you expect
 * never to fire is cheap insurance. This is the second kind, and every
 * time it does fire that gets logged, because it means something above
 * it is wrong.
 */
class OutputGuard
{
    /** What the student sees when something was removed. */
    private const REDACTED = '[removed]';

    /**
     * bcrypt and argon hashes. Laravel writes $2y$ for bcrypt; the argon
     * variants are here in case the driver is ever changed.
     */
    private const HASH = '/\$(?:2[aby]|argon2i|argon2id)\$[^\s"\']{10,}/i';

    /** Anything shaped like an API key for the providers we call. */
    private const API_KEY = '/\b(?:xai-|sk-[a-zA-Z0-9]{8,}|AIza[0-9A-Za-z_\-]{20,})[A-Za-z0-9_\-]*/';

    private const EMAIL = '/\b[\w.+-]+@[\w-]+\.[\w.-]+\b/';

    /**
     * An address with a database id in it. People's pages use random
     * public ids now, so a number here can only have been made up or
     * copied from an old answer - and a number invites counting.
     */
    private const NUMERIC_PERSON_LINK = '~(?:https?://[^\s)\]]+)?/(?:profile|messages|network)/\d+\b[^\s)\]]*~i';

    /**
     * Vendor names the persona should not surface. Matched as whole words
     * so ordinary text is untouched - "grok" only matters next to a claim
     * about what this assistant is.
     */
    private const VENDORS = '/\b(grok|xai|x\.ai|gemini|google ai|deepseek|openai|chatgpt|gpt-[0-9]|claude|anthropic|llama)\b/i';

    public function __construct(private readonly User $user) {}

    /**
     * @param  list<string>  $allowedEmails  addresses the tools returned this turn
     * @return array{text: string, redactions: list<string>}
     */
    public function clean(string $answer, array $allowedEmails = []): array
    {
        $redactions = [];
        $allowed = array_flip(array_map('strtolower', $allowedEmails));

        $answer = $this->strip($answer, self::HASH, 'password hash', $redactions);
        $answer = $this->strip($answer, self::API_KEY, 'api key', $redactions);
        $answer = $this->strip($answer, self::NUMERIC_PERSON_LINK, 'numeric profile link', $redactions);

        // Email addresses. The tools decide whose address may be shown -
        // following each person's own show_email switch - so an address a
        // tool returned this turn is one the asker could see on the site.
        // Any other address came from somewhere else: the model's memory,
        // a guess, or text echoed from a prompt. Those are removed.
        //
        // An earlier version let every uiu.ac.bd address through, which
        // quietly included student emails their owners had chosen to hide.
        $answer = preg_replace_callback(self::EMAIL, function (array $match) use (&$redactions, $allowed) {
            $address = $match[0];

            if (strcasecmp($address, (string) $this->user->email) === 0
                || isset($allowed[strtolower($address)])) {
                return $address;
            }

            $redactions[] = 'unverified email';

            return self::REDACTED;
        }, $answer) ?? $answer;

        $answer = $this->softenVendorClaims($answer, $redactions);

        if ($redactions !== []) {
            // Not a warning for the student - a signal for whoever
            // maintains this that a layer above did not hold.
            Log::warning('Assistant output guard fired.', [
                'user_id' => $this->user->id,
                'redactions' => array_values(array_unique($redactions)),
            ]);
        }

        return ['text' => trim($answer), 'redactions' => array_values(array_unique($redactions))];
    }

    private function strip(string $text, string $pattern, string $label, array &$redactions): string
    {
        $cleaned = preg_replace($pattern, self::REDACTED, $text, -1, $count);

        if ($count > 0) {
            $redactions[] = $label;
        }

        return $cleaned ?? $text;
    }

    /**
     * Replaces a sentence that names the underlying vendor.
     *
     * Only sentences that are about the assistant itself are touched, so
     * a student asking "what is the difference between Gemini and
     * DeepSeek" still gets a real answer - that is a general knowledge
     * question and nothing to do with this product's identity.
     */
    private function softenVendorClaims(string $text, array &$redactions): string
    {
        $selfReferential = '/(?:^|(?<=[.!?]\s))[^.!?]*\b(?:I am|I\'m|I was (?:built|trained|created|made)|my model|powered by|running on|based on|underneath)\b[^.!?]*[.!?]/im';

        return preg_replace_callback($selfReferential, function (array $match) use (&$redactions) {
            if (! preg_match(self::VENDORS, $match[0])) {
                return $match[0];
            }

            $redactions[] = 'model identity';

            return 'I am '.config('ai.persona_name').', the assistant built into UIU CampusConnect.';
        }, $text) ?? $text;
    }
}
