<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Carbon;

/**
 * Turns one of UIU's academic calendar pages into rows we can store.
 *
 * The pages are typed by hand, so the dates come in many shapes:
 * "Jul 4 - 6, 2026", "July 10, 2026", "Sep 26 - Oct 10, 2026",
 * "Dec 12-18, 2026", "Dec 19, 2026 - Jan 5, 2027", "Jan 30- Feb 9 2026",
 * with hyphens, en dashes or em dashes. One table cell can hold several
 * things happening that day (one <p> each), and a "Note:" paragraph belongs
 * to the item before it.
 *
 * Nothing here touches the database; the import command does that.
 */
class UiuCalendarParser
{
    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    /**
     * What a calendar is for, from its slug or title.
     *
     * @return array{program: ?string, term: ?string, term_kind: string}
     */
    public static function describe(string $slug, string $title): array
    {
        $text = strtolower($slug.' '.$title);

        $program = match (true) {
            str_contains($text, 'pharm') => 'bpharm',
            str_contains($text, 'undergraduate') => 'undergraduate',
            str_contains($text, 'graduate') => 'graduate',
            default => null,
        };

        preg_match('/\b(spring|summer|fall)[\s-]+(\d{4})\b/i', $slug.' '.$title, $m);

        return [
            'program' => $program,
            'term' => $m ? ucfirst(strtolower($m[1])).' '.$m[2] : null,
            'term_kind' => str_contains($text, 'semester') ? 'semester' : 'trimester',
        ];
    }

    /**
     * First and last day of a date as UIU writes it, or null when it cannot
     * be read - never a guess.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public static function dates(string $text): ?array
    {
        $text = trim(preg_replace('/\s+/u', ' ', str_replace(["\u{2013}", "\u{2014}", "\u{2012}", "\u{00a0}"], ['-', '-', '-', ' '], $text)));

        $pattern = '/^(?<m1>[A-Za-z]{3,9})\.?\s*(?<d1>\d{1,2})(?:,?\s*(?<y1>\d{4}))?'
            .'(?:\s*-\s*(?:(?<m2>[A-Za-z]{3,9})\.?\s*)?(?<d2>\d{1,2}))?'
            .'(?:,?\s*(?<y2>\d{4}))?$/';

        if (! preg_match($pattern, $text, $m)) {
            return null;
        }

        $m1 = self::month($m['m1']);
        $m2 = ($m['m2'] ?? '') !== '' ? self::month($m['m2']) : $m1;
        $y1 = ($m['y1'] ?? '') !== '' ? (int) $m['y1'] : null;
        $y2 = ($m['y2'] ?? '') !== '' ? (int) $m['y2'] : null;

        if ($m1 === null || $m2 === null || ($y1 === null && $y2 === null)) {
            return null;
        }

        $d1 = (int) $m['d1'];
        $d2 = ($m['d2'] ?? '') !== '' ? (int) $m['d2'] : $d1;

        $endYear = $y2 ?? $y1;
        // "Dec 28 - Jan 3, 2027": the start is in the year before.
        $startYear = $y1 ?? ($m1 > $m2 ? $endYear - 1 : $endYear);

        if (! checkdate($m1, $d1, $startYear) || ! checkdate($m2, $d2, $endYear)) {
            return null;
        }

        $start = Carbon::create($startYear, $m1, $d1)->startOfDay();
        $end = Carbon::create($endYear, $m2, $d2)->startOfDay();

        return $end->lessThan($start) ? null : [$start, $end];
    }

    /**
     * The things listed in one table cell, tidied, with notes attached to
     * the item they belong to.
     *
     * @param  list<array{t: string, b: bool}>  $paragraphs
     * @return list<array{title: string, important: bool, note: ?string, type: string}>
     */
    public static function items(array $paragraphs): array
    {
        $items = [];

        foreach ($paragraphs as $p) {
            $text = self::squash((string) ($p['t'] ?? ''));
            $bold = (bool) ($p['b'] ?? false);

            if ($text === '') {
                continue;
            }

            // "# First thing # Second thing" in one paragraph.
            $parts = str_contains($text, '#') ? preg_split('/\s*#\s*/', $text, -1, PREG_SPLIT_NO_EMPTY) : [$text];

            foreach ($parts as $part) {
                $part = trim($part);

                if ($items !== [] && preg_match('/^\*?\s*(note\b|transportation fee|gymnasium fee)/i', $part)) {
                    $last = array_key_last($items);
                    $items[$last]['note'] = trim(($items[$last]['note'] ?? '').' '.self::stripNoteLabel($part));

                    continue;
                }

                $note = null;

                // "Last day to apply ... Trimester. Note: No application ..."
                if (preg_match('/^(.*?[.!])\s+(note\s*:.*)$/is', $part, $m)) {
                    [$part, $note] = [trim($m[1]), self::stripNoteLabel($m[2])];
                }

                $source = $part;

                // "1st installment: A fine of Tk. 500/- will be imposed if 25% ..."
                if (preg_match('/^(\d)\s*(st|nd|rd|th)\s+install?ment\s*:\s*(.+)$/i', $part, $m)) {
                    $part = 'Last date of '.$m[1].strtolower($m[2]).' installment';
                    $note = trim($m[3]);
                }

                $items[] = ['title' => self::title($part), 'important' => $bold, 'note' => $note ? rtrim($note) : null, 'type' => self::classify($source)];
            }
        }

        return $items;
    }

    /** Footnote markers and the "Holiday:" prefix removed; the type says it's a holiday. */
    public static function title(string $text): string
    {
        $text = preg_replace('/\*+/', '', $text);
        $text = preg_replace('/^\s*holiday\s*:\s*/i', '', $text);
        $text = preg_replace('/\s+\/\s*/', ' / ', $text);
        $text = rtrim(self::squash($text), ' .');

        return $text !== '' ? mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1) : $text;
    }

    /** Decides the colour and which lists an item turns up in. */
    public static function classify(string $original): string
    {
        $t = strtolower($original);

        return match (true) {
            str_starts_with(ltrim($t, '* '), 'holiday'), str_contains($t, 'remain closed'), str_contains($t, 'remained closed'), str_contains($t, 'trimester break'), str_contains($t, 'semester break') => 'holiday',
            str_contains($t, 'suspended'), str_contains($t, 'classes begin'), str_contains($t, 'classes resume'), str_contains($t, 'make-up'), str_contains($t, 'make up'), (bool) preg_match('/regular \w+ classes/', $t) => 'classes',
            str_contains($t, 'exam') || str_contains($t, 'oral assessment') => 'exam',
            str_contains($t, 'installment'), str_contains($t, 'instalment'), str_contains($t, 'tuition fee') => 'payment',
            str_contains($t, 'grade submission') => 'grading',
            str_contains($t, 'advising'), str_contains($t, 'registration') => 'registration',
            str_contains($t, 'orientation'), str_contains($t, 'open day') => 'event',
            str_contains($t, 'last day'), str_contains($t, 'last date'), str_contains($t, 'withdrawal'), str_contains($t, 'drop course') => 'deadline',
            default => 'academic',
        };
    }

    /**
     * "Classes of Fall 2026 Trimester will Begin: Oct 27, 2026 (Tue)" sits in
     * the footnotes, not the table. It is the one date students want once a
     * term ends, so it becomes a row of its own.
     *
     * @return array{title: string, start: Carbon}|null
     */
    public static function nextTermStart(?string $notes): ?array
    {
        $notes = $notes ? self::squash($notes) : null;

        if (! $notes || ! preg_match('/Classes of\s+(.+?)\s+(?:will\s+)?Begin\s*:\s*([A-Za-z]{3,9}\.?\s*\d{1,2},?\s*\d{4})/i', $notes, $m)) {
            return null;
        }

        $dates = self::dates($m[2]);

        return $dates ? ['title' => trim($m[1]).' classes begin', 'start' => $dates[0]] : null;
    }

    /**
     * The fine print for "Last date of 2nd installment": the percentage due
     * and the fine, taken from the footnotes when the row itself says nothing.
     */
    public static function instalmentNote(?string $notes, string $title): ?string
    {
        if (! $notes || ! preg_match('/(\d)\s*(?:st|nd|rd|th)\s+install?ment/i', $title, $which)) {
            return null;
        }

        $pattern = '/'.$which[1].'\s*(?:st|nd|rd|th)\s+install?ment\s*:\s*(.+?)(?=\s*\*|\n|$)/i';

        return preg_match($pattern, $notes, $m) ? rtrim(self::squash($m[1]), ' ') : null;
    }

    /**
     * Reads a calendar page's HTML into the same shape the browser capture
     * used: rows of [date text, day, paragraphs] plus the footnotes.
     *
     * @return array{rows: list<array{0: string, 1: string, 2: list<array{t: string, b: bool}>}>, notes: string, pdf: ?string}
     */
    public static function fromHtml(string $html): array
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);
        $class = fn (string $name) => "contains(concat(' ', normalize-space(@class), ' '), ' {$name} ')";
        $rows = [];

        foreach ($xpath->query("//*[{$class('calender-table')}]//tr") as $tr) {
            $cells = $xpath->query('./td', $tr);

            if ($cells->length < 3) {
                continue;
            }

            /** @var DOMElement $cell */
            $cell = $cells->item(2);
            $paragraphs = [];

            foreach ($xpath->query('.//p', $cell) as $p) {
                $paragraphs[] = ['t' => self::squash($p->textContent), 'b' => $xpath->query('.//strong|.//b', $p)->length > 0];
            }

            if ($paragraphs === []) {
                $paragraphs[] = ['t' => self::squash($cell->textContent), 'b' => $xpath->query('.//strong|.//b', $cell)->length > 0];
            }

            $rows[] = [self::squash($cells->item(0)->textContent), self::squash($cells->item(1)->textContent), $paragraphs];
        }

        $notes = [];

        foreach ($xpath->query("//*[{$class('calender-extra-details')}]") as $box) {
            $blocks = $xpath->query('.//p|.//li', $box);

            if ($blocks->length === 0) {
                $notes[] = self::squash($box->textContent);
            }

            foreach ($blocks as $block) {
                $notes[] = self::squash($block->textContent);
            }
        }

        $pdf = $xpath->query("//*[{$class('calender-links')}]//a[@href]")->item(0);

        return [
            'rows' => $rows,
            'notes' => implode("\n\n", array_filter($notes)),
            'pdf' => $pdf instanceof DOMElement ? $pdf->getAttribute('href') : null,
        ];
    }

    public static function squash(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(str_replace("\u{00a0}", ' ', $text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private static function stripNoteLabel(string $text): string
    {
        return trim(preg_replace('/^\*?\s*note\s*:\s*/i', '', $text));
    }

    private static function month(string $name): ?int
    {
        return self::MONTHS[strtolower(substr($name, 0, 3))] ?? null;
    }
}
