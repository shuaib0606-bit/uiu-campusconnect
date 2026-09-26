<?php

namespace App\Http\Controllers;

use App\Models\TermCalendar;
use App\Support\AcademicCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The full academic calendar: every date of one program's term, grouped by
 * month and coloured by what has passed, what is happening and what is next.
 * Anyone signed in can look at any program's calendar; which one they see
 * first is their own.
 */
class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $mine = AcademicCalendar::programFor($user);
        $program = array_key_exists((string) $request->query('program'), AcademicCalendar::PROGRAMS)
            ? (string) $request->query('program')
            : $mine;

        $terms = AcademicCalendar::imported()
            ? TermCalendar::program($program)->orderByDesc('starts_on')->get()
            : collect();

        $calendar = $terms->firstWhere('slug', (string) $request->query('term')) ?? AcademicCalendar::current($program);
        $today = Carbon::today();
        $months = collect();
        $nextId = null;

        if ($calendar) {
            $rows = $calendar->dates->map(function ($d) use ($today) {
                $row = AcademicCalendar::row($d->title, $d->type, $d->starts_on, $d->ends_on, $today, [
                    'important' => $d->important, 'note' => $d->note,
                ]);

                $row['id'] = $d->id;
                $row['state'] = match (true) {
                    $row['in_progress'] => 'now',
                    $row['end']->lessThan($today) => 'past',
                    default => 'upcoming',
                };

                return $row;
            });

            $nextId = $rows->firstWhere('state', 'upcoming')['id'] ?? null;
            $months = $rows->groupBy(fn ($r) => $r['start']->format('Y-m'));
        }

        return view('calendar.index', [
            'programs' => AcademicCalendar::PROGRAMS,
            'hints' => AcademicCalendar::PROGRAM_HINTS,
            'program' => $program,
            'mine' => $mine,
            'picked' => $user->calendar_program,
            'terms' => $terms,
            'calendar' => $calendar,
            'months' => $months,
            'nextId' => $nextId,
            'today' => $today,
            'types' => AcademicCalendar::TYPES,
            // Nothing newer has been published, and this one is over.
            'awaitingNext' => $calendar && $terms->first()?->is($calendar) && $calendar->ends_on?->lessThan($today),
        ]);
    }

    /** Which calendar the feed shows. "auto" goes back to following the department. */
    public function program(Request $request)
    {
        $data = $request->validate([
            'program' => ['required', Rule::in([...array_keys(AcademicCalendar::PROGRAMS), 'auto'])],
        ]);

        $user = $request->user();
        $user->forceFill(['calendar_program' => $data['program'] === 'auto' ? null : $data['program']])->save();

        $program = AcademicCalendar::programFor($user);

        return redirect()->route('calendar.index', ['program' => $program])
            ->with('success', 'Your feed now shows the '.AcademicCalendar::PROGRAMS[$program].' calendar.');
    }
}
