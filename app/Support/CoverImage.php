<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Builds the cover shown when a listing has no photo of its own. The result is
 * an inline SVG, so nothing is written to disk and the same title always draws
 * the same picture.
 */
class CoverImage
{
    /** Gradient pairs, all built around the site's terracotta. */
    private const PALETTES = [
        ['#c15f3c', '#8b3d24'],
        ['#d9834f', '#a8502c'],
        ['#7d5a45', '#453127'],
        ['#40695f', '#22423b'],
        ['#6a5a8c', '#3c3459'],
        ['#a87a3c', '#6b4820'],
    ];

    /** A data URI ready for src="" or background-image. */
    public static function dataUri(string $seed, ?string $label = null): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($seed, $label));
    }

    public static function svg(string $seed, ?string $label = null): string
    {
        $hash = crc32($seed !== '' ? $seed : 'campusconnect');
        [$from, $to] = self::PALETTES[$hash % count(self::PALETTES)];

        $monogram = self::monogram($label ?? $seed);
        $angle = 15 * ($hash % 4);
        $glare = number_format(0.18 + (($hash >> 3) % 24) / 100, 2);

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 630" width="1200" height="630" role="img">
          <defs>
            <linearGradient id="g" x1="0" y1="0" x2="1" y2="1" gradientTransform="rotate({$angle} 0.5 0.5)">
              <stop offset="0" stop-color="{$from}"/>
              <stop offset="1" stop-color="{$to}"/>
            </linearGradient>
            <radialGradient id="h" cx="{$glare}" cy="0.25" r="0.8">
              <stop offset="0" stop-color="#ffffff" stop-opacity="0.22"/>
              <stop offset="1" stop-color="#ffffff" stop-opacity="0"/>
            </radialGradient>
          </defs>
          <rect width="1200" height="630" fill="url(#g)"/>
          <rect width="1200" height="630" fill="url(#h)"/>
          <g fill="none" stroke="#ffffff" stroke-opacity="0.12" stroke-width="2">
            <circle cx="1040" cy="120" r="180"/>
            <circle cx="1040" cy="120" r="280"/>
            <circle cx="160" cy="560" r="150"/>
          </g>
          <text x="600" y="315" text-anchor="middle" dominant-baseline="central"
                font-family="Inter, Segoe UI, Helvetica, Arial, sans-serif"
                font-size="210" font-weight="600" fill="#ffffff" fill-opacity="0.9"
                letter-spacing="6">{$monogram}</text>
        </svg>
        SVG;
    }

    /** Words that carry no meaning in a monogram. */
    private const SKIP = ['uiu', 'of', 'the', 'and', 'for', 'a', 'an'];

    /** Up to three initials from the title, falling back to the site mark. */
    private static function monogram(string $text): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Almost every club here is called "UIU Something Forum", so taking
        // the first two words gave two dozen badges reading "UA", "UB", "UC".
        // Dropping the university's own name and the joining words leaves
        // the part that actually distinguishes one from another.
        $meaningful = array_values(array_filter(
            $words,
            fn (string $word) => ! in_array(Str::lower($word), self::SKIP, true)
        ));

        $words = $meaningful !== [] ? $meaningful : $words;

        $letters = '';
        foreach (array_slice($words, 0, 3) as $word) {
            $letters .= Str::upper(Str::substr($word, 0, 1));
        }

        // Two words beginning with the same letter as another club's two
        // words give the same badge - "Accounting Forum" and "App Forum"
        // both reduced to AF and sat side by side in the directory looking
        // like a duplicated row. A second letter from the first word tells
        // them apart: AcF and ApF.
        if (count($words) === 2 && Str::length($words[0]) > 1) {
            $letters = Str::upper(Str::substr($words[0], 0, 1))
                .Str::lower(Str::substr($words[0], 1, 1))
                .Str::upper(Str::substr($words[1], 0, 1));
        }

        return $letters !== '' ? e($letters) : 'UC';
    }
}
