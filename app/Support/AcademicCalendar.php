<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** Reads the trimester calendar in config/academic.php. A multi-day period stays current until its final day has passed. */
class AcademicCalendar
{
    /**
     * Milestones that are upcoming or currently running, soonest first.
     *
     * @return array<int, array{label:string,type:string,start:Carbon,end:Carbon,is_range:bool,in_progress:bool,days_away:int}>
     */
    public static function upcoming(int $limit = 4): array
    {
        $today = Carbon::today();
        $rows = [];

        foreach (config('academic.milestones', []) as $m) {
            if (empty($m['date'])) {
                continue;
            }

            $start = Carbon::parse($m['date'])->startOfDay();
            $end = ! empty($m['end_date'])
                ? Carbon::parse($m['end_date'])->startOfDay()
                : $start->copy();

            if ($end->lessThan($today)) {
                continue;
            }

            $rows[] = [
                'label' => $m['label'] ?? 'Untitled',
                'type' => $m['type'] ?? 'academic',
                'start' => $start,
                'end' => $end,
                'is_range' => $end->greaterThan($start),
                'in_progress' => $start->lessThanOrEqualTo($today) && $end->greaterThanOrEqualTo($today),
                'days_away' => (int) $today->diffInDays($start, false),
            ];
        }

        usort($rows, fn ($a, $b) => $a['start']->getTimestamp() <=> $b['start']->getTimestamp());

        return array_slice($rows, 0, $limit);
    }

    public static function trimester(): string
    {
        return config('academic.trimester', '');
    }

    /** Human phrasing for how far away a milestone is. */
    public static function relative(array $row): string
    {
        if ($row['in_progress']) {
            return $row['is_range'] ? 'In progress' : 'Today';
        }

        return match (true) {
            $row['days_away'] === 0 => 'Today',
            $row['days_away'] === 1 => 'Tomorrow',
            $row['days_away'] < 7 => "In {$row['days_away']} days",
            $row['days_away'] < 14 => 'Next week',
            default => "In {$row['days_away']} days",
        };
    }
}
