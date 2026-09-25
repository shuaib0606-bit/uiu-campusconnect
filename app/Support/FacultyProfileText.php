<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Short text derived from a faculty member's website sections.
 *
 * Research interests on the About tab are shown as tags, so they are taken
 * from a website section only when that section really is a short list
 * ("Computer Networks", "Wireless Security", ...). A paragraph of prose is
 * left in the Academic tab where it belongs.
 *
 * The legacy* methods reproduce what the first version of the sync wrote
 * into bio and research interests, so the clean-up migration can recognise
 * those values - and only those - and take them back out.
 */
final class FacultyProfileText
{
    public const BIO_SECTIONS = ['profile', 'biography', 'bio', 'biodata', 'about'];

    public const RESEARCH_SECTIONS = ['research interests', 'research interest', 'research areas', 'research'];

    /**
     * Research interests as a comma list, or null when the website section
     * is not a short list.
     *
     * @param  array<string, string>  $sections  title => markdown
     */
    public static function researchTags(array $sections): ?string
    {
        $body = self::find($sections, self::RESEARCH_SECTIONS);

        if ($body === null) {
            return null;
        }

        preg_match_all('/<li>(.*?)<\/li>/s', self::html($body), $items);
        $items = array_values(array_filter(array_map([self::class, 'clean'], $items[1])));

        if (count($items) < 2 || count($items) > 15) {
            return null;
        }

        foreach ($items as $item) {
            if (mb_strlen($item) > 90) {
                return null;
            }
        }

        return Str::limit(implode(', ', $items), 700);
    }

    /** What the first sync put in bio. */
    public static function legacyBio(array $sections): ?string
    {
        return self::legacy($sections, self::BIO_SECTIONS, 1000);
    }

    /** What the first sync put in research interests. */
    public static function legacyResearch(array $sections): ?string
    {
        return self::legacy($sections, self::RESEARCH_SECTIONS, 500);
    }

    private static function legacy(array $sections, array $names, int $limit): ?string
    {
        $body = self::find($sections, $names);

        if ($body === null) {
            return null;
        }

        $html = self::html($body);

        preg_match_all('/<li>(.*?)<\/li>/s', $html, $items);
        $items = array_values(array_filter(array_map([self::class, 'clean'], $items[1])));

        if ($items !== [] && $limit <= 500) {
            return Str::limit(implode(', ', $items), $limit);
        }

        preg_match_all('/<p>(.*?)<\/p>/s', $html, $paragraphs);

        foreach (array_map([self::class, 'clean'], $paragraphs[1]) as $paragraph) {
            if (str_word_count($paragraph) >= 20 && ! preg_match('~https?://~', $paragraph)) {
                return Str::limit($paragraph, $limit);
            }
        }

        $text = self::clean($html);

        return $text !== '' && ! preg_match('~https?://~', $text) ? Str::limit($text, $limit) : null;
    }

    /** The first section whose title is one of $names, in the order of $names. */
    private static function find(array $sections, array $names): ?string
    {
        foreach ($names as $wanted) {
            foreach ($sections as $title => $body) {
                if (Str::lower(trim((string) $title)) === $wanted) {
                    return (string) $body;
                }
            }
        }

        return null;
    }

    private static function html(string $markdown): string
    {
        return Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    private static function clean(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));
    }
}
