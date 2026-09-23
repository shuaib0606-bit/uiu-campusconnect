<?php

namespace App\Services\Ai;

use Illuminate\Support\Str;

/**
 * The people and things an answer was about, carried to the next turn.
 *
 * The model sees earlier answers only as text. If an answer said "Nusrat
 * Jahan" and the university has two of them, a follow-up like "send me
 * her profile" cannot be resolved by any model, however good - the text
 * does not say which one. So after every answer this class walks the
 * tool results and keeps each item that has a link: its label, the link,
 * and how it related to what was asked ("seller of Casio fx-100MS").
 * The next question is sent with that list, and the model resolves "her"
 * against it instead of guessing from a name.
 *
 * Only a label and a link are kept - never an email, an ID or a phone -
 * so the list can be replayed on later turns without re-deciding who may
 * see what. Following a link to a person goes back through people(), which
 * applies every privacy rule again at that moment.
 */
class References
{
    /** Kept per answer. Enough for a list of results, small enough to replay. */
    public const PER_ANSWER = 24;

    /** Sent with a question, newest first. */
    public const IN_PROMPT = 20;

    /** Keys that name something, in order of preference. */
    private const LABELS = ['name', 'title', 'course', 'group'];

    /**
     * @param  list<array>  $results  tool results from one answer, in call order
     * @return list<array{label: string, link: string, note?: string}>
     */
    public static function collect(array $results): array
    {
        $found = [];

        foreach ($results as $result) {
            self::walk($result, null, null, $found);
        }

        // Later mentions of the same link add nothing; the first one is
        // usually the richest (a seller found via a listing, with its note).
        $unique = [];

        foreach ($found as $ref) {
            $unique[$ref['link']] ??= $ref;
        }

        return array_slice(array_values($unique), 0, self::PER_ANSWER);
    }

    /**
     * The block added to the system prompt: newest answer's refs first.
     *
     * @param  list<array{role: string, refs?: list<array>}>  $history
     */
    public static function forPrompt(array $history): string
    {
        $lines = [];
        $seen = [];

        foreach (array_reverse($history) as $turn) {
            foreach ($turn['refs'] ?? [] as $ref) {
                $link = (string) ($ref['link'] ?? '');

                if ($link === '' || isset($seen[$link]) || count($lines) >= self::IN_PROMPT) {
                    continue;
                }

                $seen[$link] = true;
                $lines[] = '- '.self::clean($ref['label'] ?? '').' → '.$link
                    .(! empty($ref['note']) ? ' ('.self::clean($ref['note']).')' : '');
            }
        }

        return implode("\n", $lines);
    }

    private static function walk(mixed $node, ?string $key, ?string $parent, array &$found): void
    {
        if (! is_array($node)) {
            return;
        }

        $label = self::label($node);
        $link = $node['link'] ?? null;

        if ($label !== null && is_string($link) && str_starts_with($link, '/') && ! str_starts_with($link, '//')) {
            $ref = ['label' => self::clean($label), 'link' => $link];

            // How this item relates to the one it sits inside: the seller
            // of a listing, the teacher of a course, an executive of a club.
            // Otherwise what tells two same-named people apart.
            $role = isset($node['role']) && is_string($node['role']) ? $node['role'] : null;

            if ($parent !== null && $key !== null) {
                $ref['note'] = self::clean(($role ?? str_replace('_', ' ', $key)).' of '.$parent);
            } elseif ($role !== null || ! empty($node['department'])) {
                $ref['note'] = self::clean(implode(', ', array_filter([$role, $node['department'] ?? null], 'is_string')));
            }

            $found[] = $ref;
            $parent = $label;
        }

        foreach ($node as $childKey => $child) {
            if (is_array($child)) {
                // A list's items take the list's key: executives[0] is an
                // "executives of UIU Computer Club".
                $childName = is_int($childKey) ? $key : (string) $childKey;
                self::walk($child, $childName, $parent, $found);
            }
        }
    }

    private static function label(array $node): ?string
    {
        foreach (self::LABELS as $key) {
            if (isset($node[$key]) && is_string($node[$key]) && trim($node[$key]) !== '') {
                return $node[$key];
            }
        }

        return null;
    }

    /**
     * Labels are text people typed - a listing title, a club name - so
     * they are flattened to one short line before they go near a prompt.
     */
    private static function clean(string $text): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($text))), 80, '…');
    }
}
