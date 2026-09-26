<?php

namespace App\Services\Ai\Tools;

use App\Models\CourseSection;
use App\Models\Trimester;
use App\Support\AcademicCalendar;
use App\Support\ShuttleSchedule;

/**
 * Reference information that is the same for everyone: the academic
 * calendar, the shuttle timetable, and the trimester's course sections.
 */
class CampusInfoTool extends Tool
{
    public function name(): string
    {
        return 'campus_info';
    }

    public function description(): string
    {
        return 'Academic calendar (exams, fees, holidays), the shuttle bus timetable, or this trimester\'s course sections with teachers, times and rooms.';
    }

    public function parameters(): array
    {
        return self::object([
            'topic' => self::choice(['academic_calendar', 'shuttle', 'sections'], 'Which reference.'),
            'query' => self::text('For sections: a course code or title.'),
            'program' => self::choice(['mine', 'undergraduate', 'graduate', 'bpharm'], 'For the calendar: whose calendar. "mine" (the asker\'s own program) unless they ask about another - graduate means masters programs, bpharm is Pharmacy.'),
        ], ['topic']);
    }

    public function run(array $arguments): array
    {
        return match ($this->arg($arguments, 'topic')) {
            'shuttle' => $this->shuttle(),
            'sections' => $this->sections($this->arg($arguments, 'query')),
            default => $this->calendar($this->arg($arguments, 'program')),
        };
    }

    private function calendar(string $program): array
    {
        $program = array_key_exists($program, AcademicCalendar::PROGRAMS) ? $program : AcademicCalendar::programFor($this->viewer);

        return [
            'program' => AcademicCalendar::PROGRAMS[$program],
            'term' => AcademicCalendar::trimester(program: $program),
            'full_calendar' => route('calendar.index', ['program' => $program], false),
            'dates' => array_map(fn ($row) => array_filter([
                'what' => $row['label'],
                'type' => $row['type'],
                'from' => $row['start']->format('D, M j, Y'),
                'to' => $row['is_range'] ? $row['end']->format('D, M j, Y') : null,
                'status' => $row['in_progress'] ? 'happening now' : ($row['days_away'] === 1 ? 'tomorrow' : 'in '.$row['days_away'].' days'),
                'note' => $row['note'] ?: null,
            ]), AcademicCalendar::upcoming(12, program: $program)),
        ];
    }

    /**
     * The published timetable: the free shuttles, and the AC bus routes
     * (registered students only, return trips only, times tentative). A
     * route flagged provisional would carry made-up times, so it says so.
     */
    private function shuttle(): array
    {
        return [
            'routes' => array_map(fn ($route) => array_filter([
                'route' => $route['name'],
                'type' => $route['kind'] === 'ac' ? 'AC bus, registered students only' : 'shuttle',
                'confirmed_times' => ! $route['provisional'],
                'stops' => implode(' - ', $route['stops']),
                'note' => $route['note'] ?? null,
                'next' => array_map(fn ($d) => $d['label'].': '
                    .($d['running'] ? 'running now until '.$d['window_until'] : $d['depart']->format('D g:i A').($d['window_until'] ? ' - '.$d['window_until'] : ''))
                    .' from '.$d['from'].($d['continuous'] ? ' (continuous)' : '').($d['tentative'] ? ' (tentative)' : ''),
                    $route['departures']),
            ], fn ($v) => $v !== null), ShuttleSchedule::allRoutes(3)),
            'ac_bus' => config('shuttle.ac_note'),
            'no_times_published' => array_column(config('shuttle.other_services', []), 'name'),
        ];
    }

    /**
     * The trimester's sections - the /administration page, which every
     * signed-in user can already open. Teacher names, times and rooms; no
     * student lists.
     */
    private function sections(string $query): array
    {
        if (mb_strlen($query) < 2) {
            return ['error' => 'Name a course code or title.'];
        }

        $trimester = Trimester::latest();
        $like = '%'.addcslashes($query, '%_\\').'%';

        $sections = CourseSection::query()
            ->when($trimester, fn ($q) => $q->where('trimester_id', $trimester->id))
            ->where(fn ($q) => $q
                ->where('course_code', 'like', $like)
                ->orWhere('course_title', 'like', $like)
                // "CSE3411" should find "CSE 3411".
                ->orWhereRaw("REPLACE(course_code, ' ', '') LIKE ?", ['%'.str_replace(' ', '', addcslashes($query, '%_\\')).'%']))
            ->orderBy('course_code')->orderBy('section_name')
            ->limit(15)
            ->get();

        return [
            'trimester' => $trimester?->name,
            'sections' => $sections->map(fn (CourseSection $s) => array_filter([
                'course' => $s->course_code.' '.$s->course_title,
                'section' => $s->section_name,
                'teacher' => $s->teacher()?->full_name,
                'schedule' => $s->scheduleLine(),
                'room' => $s->room,
            ]))->values()->all(),
        ];
    }
}
