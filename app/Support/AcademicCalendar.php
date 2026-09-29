<?php

namespace App\Support;

use App\Models\CalendarDate;
use App\Models\TermCalendar;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * UIU's academic calendars, per program.
 *
 * UIU publishes a separate calendar for undergraduate trimesters, graduate
 * (masters) trimesters and B.Pharm semesters; `php artisan
 * uiu:import-calendars` copies them into academic_calendars. Everyone sees
 * the calendar of their own program - the one they picked, or else the one
 * their department implies.
 *
 * Until the first import has run, the hand-typed list in config/academic.php
 * is used instead, so nothing goes blank on a fresh install.
 */
class AcademicCalendar
{
    public const UNDERGRADUATE = 'undergraduate';

    public const GRADUATE = 'graduate';

    public const BPHARM = 'bpharm';

    public const PROGRAMS = [
        self::UNDERGRADUATE => 'Undergraduate',
        self::GRADUATE => 'Graduate',
        self::BPHARM => 'B.Pharm',
    ];

    public const PROGRAM_HINTS = [
        self::UNDERGRADUATE => 'Bachelor programs, by trimester',
        self::GRADUATE => "Master's programs, by trimester",
        self::BPHARM => 'Bachelor of Pharmacy, by semester',
    ];

    /**
     * Colour and icon for each kind of date. Written out in full so
     * Tailwind finds every class.
     */
    public const TYPES = [
        'exam' => ['one' => 'Exam', 'label' => 'Exams', 'icon' => 'exam', 'chip' => 'bg-red-500/10 text-red-600 dark:text-red-300', 'dot' => 'bg-red-500'],
        'payment' => ['one' => 'Fee', 'label' => 'Fees', 'icon' => 'banknote', 'chip' => 'bg-amber-500/10 text-amber-700 dark:text-amber-300', 'dot' => 'bg-amber-500'],
        'deadline' => ['one' => 'Deadline', 'label' => 'Deadlines', 'icon' => 'hourglass', 'chip' => 'bg-orange-500/10 text-orange-600 dark:text-orange-300', 'dot' => 'bg-orange-500'],
        'registration' => ['one' => 'Registration', 'label' => 'Registration', 'icon' => 'register', 'chip' => 'bg-sky-500/10 text-sky-700 dark:text-sky-300', 'dot' => 'bg-sky-500'],
        'classes' => ['one' => 'Classes', 'label' => 'Classes', 'icon' => 'class', 'chip' => 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-300', 'dot' => 'bg-indigo-500'],
        'holiday' => ['one' => 'Holiday', 'label' => 'Holidays', 'icon' => 'umbrella', 'chip' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300', 'dot' => 'bg-emerald-500'],
        'grading' => ['one' => 'Grading', 'label' => 'Grading', 'icon' => 'check-badge', 'chip' => 'bg-violet-500/10 text-violet-600 dark:text-violet-300', 'dot' => 'bg-violet-500'],
        'event' => ['one' => 'Event', 'label' => 'Events', 'icon' => 'ticket', 'chip' => 'bg-pink-500/10 text-pink-600 dark:text-pink-300', 'dot' => 'bg-pink-500'],
        'academic' => ['one' => 'Other', 'label' => 'Other', 'icon' => 'info', 'chip' => 'bg-primary-500/10 text-primary-600 dark:text-primary-300', 'dot' => 'bg-primary-500'],
    ];

    private static ?bool $imported = null;

    /** The program whose calendar this person sees by default. */
    public static function programFor(?User $user): string
    {
        $picked = $user?->calendar_program;

        if ($picked && isset(self::PROGRAMS[$picked])) {
            return $picked;
        }

        return $user && str_contains(strtolower((string) $user->department), 'pharm')
            ? self::BPHARM
            : self::UNDERGRADUATE;
    }

    public static function type(string $type): array
    {
        return self::TYPES[$type] ?? self::TYPES['academic'];
    }

    /** Whether any calendar has been imported yet. */
    public static function imported(): bool
    {
        if (self::$imported === null) {
            try {
                self::$imported = Schema::hasTable('academic_calendars') && TermCalendar::query()->exists();
            } catch (Throwable) {
                self::$imported = false;
            }
        }

        return self::$imported;
    }

    /** For tests and the importer: forget what was checked this request. */
    public static function flush(): void
    {
        self::$imported = null;
    }

    /**
     * The calendar that is running now for a program, or the next one to
     * start; failing both, the most recent one.
     */
    public static function current(string $program): ?TermCalendar
    {
        if (! self::imported()) {
            return null;
        }

        $today = Carbon::today()->toDateString();

        return TermCalendar::program($program)->whereDate('ends_on', '>=', $today)->orderBy('starts_on')->first()
            ?? TermCalendar::program($program)->orderByDesc('starts_on')->first();
    }

    /**
     * Dates that are upcoming or still running, soonest first. A multi-day
     * period stays in the list until its last day has passed.
     *
     * @param  list<string>|null  $types  only these kinds of date
     * @return list<array{label: string, type: string, start: Carbon, end: Carbon, is_range: bool, in_progress: bool, days_away: int, important: bool, note: ?string, term: ?string}>
     */
    public static function upcoming(int $limit = 4, ?User $user = null, ?array $types = null, ?string $program = null, ?Carbon $until = null): array
    {
        if (! self::imported()) {
            return self::fromConfig($limit, $types, $until);
        }

        $program ??= self::programFor($user);
        $today = Carbon::today();

        $dates = CalendarDate::query()
            ->join('academic_calendars', 'academic_calendars.id', '=', 'academic_calendar_events.calendar_id')
            ->where('academic_calendars.program', $program)
            ->whereDate('academic_calendar_events.ends_on', '>=', $today->toDateString())
            ->when($types, fn ($q) => $q->whereIn('academic_calendar_events.type', $types))
            ->when($until, fn ($q) => $q->whereDate('academic_calendar_events.starts_on', '<=', $until->toDateString()))
            ->orderBy('academic_calendar_events.starts_on')
            ->orderBy('academic_calendars.starts_on')
            ->orderBy('academic_calendar_events.position')
            ->limit($limit * 3 + 10)
            ->get(['academic_calendar_events.*', 'academic_calendars.term as calendar_term', 'academic_calendars.term_kind as calendar_kind']);

        $rows = [];
        $seen = [];

        foreach ($dates as $date) {
            // The same first day of classes is announced at the foot of the
            // previous calendar and again at the top of the next one.
            $key = $date->type === 'classes' && str_contains(strtolower($date->title), 'begin')
                ? 'begin|'.$date->starts_on->toDateString()
                : strtolower($date->title).'|'.$date->starts_on->toDateString();

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $rows[] = self::row($date->title, $date->type, $date->starts_on, $date->ends_on, $today, [
                'important' => (bool) $date->important,
                'note' => $date->note,
                'term' => trim($date->calendar_term.' '.ucfirst((string) $date->calendar_kind)),
            ]);

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /** "Summer 2026 Trimester" - the term the program is in now. */
    public static function trimester(?User $user = null, ?string $program = null): string
    {
        if (! self::imported()) {
            return (string) config('academic.trimester', '');
        }

        return self::current($program ?? self::programFor($user))?->label() ?? '';
    }

    /** Human phrasing for how far away a date is. */
    public static function relative(array $row): string
    {
        if ($row['in_progress']) {
            return $row['is_range'] ? 'In progress' : 'Today';
        }

        return match (true) {
            $row['days_away'] < 0 => abs($row['days_away']) === 1 ? 'Yesterday' : abs($row['days_away']).' days ago',
            $row['days_away'] === 0 => 'Today',
            $row['days_away'] === 1 => 'Tomorrow',
            default => "In {$row['days_away']} days",
        };
    }

    public static function row(string $label, string $type, Carbon $start, Carbon $end, ?Carbon $today = null, array $extra = []): array
    {
        $today ??= Carbon::today();
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        return [
            'label' => $label,
            'type' => $type,
            'start' => $start,
            'end' => $end,
            'is_range' => $end->greaterThan($start),
            'in_progress' => $start->lessThanOrEqualTo($today) && $end->greaterThanOrEqualTo($today),
            'days_away' => (int) $today->diffInDays($start, false),
            'important' => $extra['important'] ?? false,
            'note' => $extra['note'] ?? null,
            'term' => $extra['term'] ?? null,
        ];
    }

    /** The hand-typed list, used until the first import. */
    private static function fromConfig(int $limit, ?array $types, ?Carbon $until): array
    {
        $today = Carbon::today();
        $rows = [];

        foreach (config('academic.milestones', []) as $m) {
            if (empty($m['date'])) {
                continue;
            }

            $type = $m['type'] ?? 'academic';
            $start = Carbon::parse($m['date']);
            $end = ! empty($m['end_date']) ? Carbon::parse($m['end_date']) : $start->copy();

            if ($end->lessThan($today) || ($types && ! in_array($type, $types, true)) || ($until && $start->greaterThan($until))) {
                continue;
            }

            $rows[] = self::row($m['label'] ?? 'Untitled', $type, $start, $end, $today, ['term' => config('academic.trimester')]);
        }

        usort($rows, fn ($a, $b) => $a['start']->getTimestamp() <=> $b['start']->getTimestamp());

        return array_slice($rows, 0, $limit);
    }
}
