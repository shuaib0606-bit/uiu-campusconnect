<?php

namespace App\Console\Commands;

use App\Models\CalendarDate;
use App\Models\TermCalendar;
use App\Support\UiuCalendarParser;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Copies UIU's academic calendars from uiu.ac.bd into the app.
 *
 * Run it whenever UIU publishes a new calendar (or revises one):
 *
 *     php artisan uiu:import-calendars
 *
 * Every calendar on https://www.uiu.ac.bd/academics/calendar/ is read -
 * undergraduate and graduate trimesters, B.Pharm semesters - and matched by
 * the slug of its page. A calendar whose dates have not changed is left
 * alone; a revised one has its dates replaced; a new one is added. Nothing
 * is ever deleted, so older terms stay browsable.
 *
 * --file reads a JSON capture of the same pages instead of going online.
 * UIU's site runs Imunify360 bot protection, which refuses requests that do
 * not come from a browser; when that happens, export the calendars from a
 * browser with the bookmark in tools/uiu-calendar-export.md and use --file.
 * --dry-run shows what would change and writes nothing; add -v to see
 * every parsed row.
 */
class ImportAcademicCalendars extends Command
{
    protected $signature = 'uiu:import-calendars
        {--file= : Read a saved JSON capture instead of fetching uiu.ac.bd}
        {--dry-run : Show what would change without saving}';

    protected $description = 'Import the academic calendars published on uiu.ac.bd';

    private const SITE = 'https://www.uiu.ac.bd';

    private const BLOCKED = <<<'TXT'
        uiu.ac.bd turned this computer away: its bot protection (Imunify360) blocks programs
        that fetch pages automatically. Don't keep retrying - it only extends the block.

        Instead, export the calendars from your browser, where the site works normally:
          1. Open https://www.uiu.ac.bd/academics/calendar/ in your browser.
          2. Click the "UIU calendars" bookmark (see tools/uiu-calendar-export.md) and then the
             "Download uiu-calendars.json" button it shows.
          3. php artisan uiu:import-calendars --file=~/Downloads/uiu-calendars.json

        For a permanent fix, UIU's IT office can whitelist this server's address.
        TXT;

    public function handle(): int
    {
        try {
            $pages = $this->option('file') ? $this->fromFile((string) $this->option('file')) : $this->fromSite();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($pages === []) {
            $this->error('No calendars found.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $counts = ['new' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];

        foreach ($pages as $page) {
            $counts[$this->importOne($page, $dry)]++;
        }

        $this->newLine();
        $this->info(($dry ? 'Dry run - nothing saved. ' : '')
            ."{$counts['new']} new, {$counts['updated']} updated, {$counts['unchanged']} unchanged, {$counts['skipped']} skipped.");

        if (! $dry) {
            $this->latestPerProgram();
        }

        return self::SUCCESS;
    }

    /** @return 'new'|'updated'|'unchanged'|'skipped' */
    private function importOne(array $page, bool $dry): string
    {
        $slug = (string) ($page['slug'] ?? '');
        $title = UiuCalendarParser::squash((string) ($page['title'] ?? $slug));
        $about = UiuCalendarParser::describe($slug, $title);

        if ($slug === '' || ! $about['program'] || ! $about['term']) {
            $this->warn("  Skipped \"{$title}\": cannot tell which program or term it is for.");

            return 'skipped';
        }

        $notes = trim((string) ($page['notes'] ?? ''));
        [$rows, $unread] = $this->rows($page['rows'] ?? [], $notes);

        if ($rows === []) {
            $this->warn("  Skipped \"{$title}\": no dates could be read.");

            return 'skipped';
        }

        $starts = min(array_column($rows, 'starts_on'));
        $ends = max(array_column($rows, 'ends_on'));
        $checksum = hash('sha256', json_encode([$title, $notes, $rows]));

        $existing = TermCalendar::where('slug', $slug)->first();
        $state = ! $existing ? 'new' : ($existing->checksum === $checksum ? 'unchanged' : 'updated');

        $this->line(sprintf('  %-9s %s  (%s, %d dates, %s to %s)', ucfirst($state), $title, $about['program'], count($rows), $starts, $ends));

        foreach ($unread as $text) {
            $this->warn("            could not read the date \"{$text}\" - that row was left out");
        }

        if ($this->output->isVerbose()) {
            foreach ($rows as $r) {
                $this->line(sprintf('            %s%s  %-12s %s%s', $r['starts_on'], $r['ends_on'] !== $r['starts_on'] ? ' to '.$r['ends_on'] : '              ', $r['type'], $r['title'], $r['note'] ? '  ['.mb_strimwidth($r['note'], 0, 60, '...').']' : ''));
            }
        }

        if ($dry || $state === 'unchanged') {
            return $state;
        }

        DB::transaction(function () use ($existing, $slug, $title, $about, $page, $notes, $starts, $ends, $checksum, $rows) {
            $calendar = $existing ?? new TermCalendar(['slug' => $slug]);

            $calendar->fill([
                'program' => $about['program'],
                'term' => $about['term'],
                'term_kind' => $about['term_kind'],
                'title' => $title,
                'source_url' => $page['link'] ?? null,
                'notes' => $notes !== '' ? $notes : null,
                'starts_on' => $starts,
                'ends_on' => $ends,
                'published_on' => ! empty($page['date']) ? Carbon::parse($page['date'])->toDateString() : null,
                'checksum' => $checksum,
                'imported_at' => now(),
            ])->save();

            CalendarDate::where('calendar_id', $calendar->id)->delete();

            $now = now();
            CalendarDate::insert(array_map(fn ($r) => $r + ['calendar_id' => $calendar->id, 'created_at' => $now, 'updated_at' => $now], $rows));
        });

        return $state;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function rows(array $rawRows, string $notes): array
    {
        $rows = [];
        $unread = [];

        foreach ($rawRows as $raw) {
            $dateText = (string) ($raw[0] ?? '');
            $dates = UiuCalendarParser::dates($dateText);

            if (! $dates) {
                $unread[] = $dateText;

                continue;
            }

            foreach (UiuCalendarParser::items($raw[2] ?? []) as $item) {
                $type = $item['type'];

                $rows[] = [
                    'starts_on' => $dates[0]->toDateString(),
                    'ends_on' => $dates[1]->toDateString(),
                    'title' => mb_substr($item['title'], 0, 500),
                    'type' => $type,
                    'important' => $item['important'],
                    'note' => $item['note'] ?? ($type === 'payment' ? UiuCalendarParser::instalmentNote($notes, $item['title']) : null),
                    'date_text' => mb_substr($dateText, 0, 80),
                    'position' => count($rows),
                ];
            }
        }

        $next = UiuCalendarParser::nextTermStart($notes);

        if ($next && ! collect($rows)->contains(fn ($r) => $r['starts_on'] === $next['start']->toDateString() && $r['type'] === 'classes')) {
            $rows[] = [
                'starts_on' => $next['start']->toDateString(),
                'ends_on' => $next['start']->toDateString(),
                'title' => $next['title'],
                'type' => 'classes',
                'important' => true,
                'note' => 'From the notes under the calendar.',
                'date_text' => $next['start']->format('M j, Y'),
                'position' => count($rows),
            ];
        }

        usort($rows, fn ($a, $b) => [$a['starts_on'], $a['position']] <=> [$b['starts_on'], $b['position']]);

        return [$rows, $unread];
    }

    private function fromFile(string $path): array
    {
        $path = preg_replace('#^~(?=/|$)#', (string) (getenv('HOME') ?: ''), $path);

        if (! is_readable($path)) {
            throw new \RuntimeException("Cannot read {$path}.");
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            throw new \RuntimeException("{$path} is not a JSON list of calendars.");
        }

        $this->info('Reading '.count($data).' calendars from '.$path);

        return array_map(fn ($c) => [
            'slug' => $c['slug'] ?? '',
            'title' => $c['title'] ?? '',
            'date' => $c['date'] ?? null,
            'link' => $c['link'] ?? null,
            'rows' => $c['rows'] ?? [],
            'notes' => $c['notes'] ?? '',
        ], $data);
    }

    private function fromSite(): array
    {
        // Says honestly what it is, and asks once: UIU's site runs bot
        // protection, and hammering it with retries only gets the address
        // blocked for longer.
        $http = Http::withHeaders([
            'User-Agent' => 'UIU-CampusConnect/1.0 (academic calendar import)',
            'Accept-Language' => 'en',
        ])->timeout(30);

        $list = $this->calendarList($http);
        $this->info('Found '.count($list).' calendars on uiu.ac.bd');

        $pages = [];

        foreach ($list as $item) {
            $page = $http->accept('text/html')->get($item['link']);
            $parsed = $page->successful() ? UiuCalendarParser::fromHtml($page->body()) : ['rows' => [], 'notes' => ''];

            if ($page->status() === 403) {
                throw new \RuntimeException(self::BLOCKED);
            }

            if ($parsed['rows'] === []) {
                $saved = $this->keep($page->body());
                $this->warn("  Could not read {$item['title']} (HTTP {$page->status()}) - left as it was. What came back is in {$saved}");

                continue;
            }

            $pages[] = $item + ['rows' => $parsed['rows'], 'notes' => $parsed['notes']];
        }

        return $pages;
    }

    /**
     * The calendars UIU lists, from WordPress's JSON API.
     *
     * @return list<array{slug: string, title: string, date: ?string, link: string}>
     */
    private function calendarList($http): array
    {
        $rest = $http->accept('application/json')->get(self::SITE.'/wp-json/wp/v2/calendars', [
            'per_page' => 100,
            '_fields' => 'slug,title,date,link',
        ]);

        // A byte-order mark or a stray notice before the JSON is common on
        // WordPress sites; start from the first bracket.
        $body = ltrim($rest->body(), "\xEF\xBB\xBF \t\r\n");
        $json = json_decode(($at = strpos($body, '[{')) !== false ? substr($body, $at) : $body, true);

        if ($rest->successful() && is_array($json) && $json !== []) {
            return array_values(array_filter(array_map(fn ($item) => [
                'slug' => (string) ($item['slug'] ?? ''),
                'title' => html_entity_decode((string) ($item['title']['rendered'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'date' => $item['date'] ?? null,
                'link' => (string) ($item['link'] ?? ''),
            ], $json), fn ($i) => $i['slug'] !== '' && str_starts_with($i['link'], self::SITE.'/')));
        }

        // The archive page builds its list with JavaScript, so there is no
        // second place to read the list from. Keep what came back instead.
        $saved = $this->keep($rest->body());

        if ($rest->status() === 403 || stripos($rest->body(), 'imunify') !== false || stripos($rest->body(), 'bot-protection') !== false) {
            throw new \RuntimeException(self::BLOCKED);
        }

        throw new \RuntimeException('uiu.ac.bd answered (HTTP '.$rest->status().', '.($rest->header('Content-Type') ?: 'no type').') but not with the calendar list. '
            .'What came back is in '.$saved.'. Meanwhile: php artisan uiu:import-calendars --file=~/Desktop/uiu-calendars.json');
    }

    /** Keeps an unexpected response so it can be looked at. */
    private function keep(string $body): string
    {
        $path = storage_path('app/calendar-import/last-response.html');
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $body);

        return $path;
    }

    /** Ends with what each program's newest calendar is, so a missing one is obvious. */
    private function latestPerProgram(): void
    {
        $this->newLine();

        foreach (['undergraduate' => 'Undergraduate', 'graduate' => 'Graduate', 'bpharm' => 'B.Pharm'] as $program => $label) {
            $latest = TermCalendar::program($program)->orderByDesc('starts_on')->first();

            $this->line(sprintf('  %-14s %s', $label, $latest
                ? $latest->label().' (runs to '.$latest->ends_on->format('M j, Y').')'
                : 'none yet'));
        }
    }
}
