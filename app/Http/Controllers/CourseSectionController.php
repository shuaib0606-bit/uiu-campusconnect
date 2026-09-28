<?php

namespace App\Http\Controllers;

use App\Models\CourseEnrollment;
use App\Models\CourseSection;
use App\Models\Trimester;
use App\Models\User;
use App\Support\GroupSync;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The registrar's view of a trimester: which sections are running, who teaches
 * them, and how many students are in each.
 *
 * This replaces the old section_announcements page, which let the registrar
 * type a course code, room and seat count that became nothing - a notice about
 * a section rather than the section itself. Now the same form writes a real
 * course_sections row, which is what the class groups, enrolment and the CSV
 * import all already run on, so there is one place a section exists.
 *
 * Announcing something *about* a class is a different job, and the class
 * group's Notices already do it: those reach exactly the students enrolled.
 */
class CourseSectionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Faculty, officials and admins can view the section list; students
        // cannot. The menu hides the link, but this check is the real lock -
        // without it anyone could open the page by typing the address.
        abort_unless(
            $user->isFaculty() || $user->isOfficial() || $user->isAdmin(),
            403,
            'Course sections are for faculty and university staff.'
        );

        $trimesters = Trimester::orderByDesc('ends_on')->get();
        $current = $this->chosenTrimester($request, $trimesters);

        $sections = $current
            ? CourseSection::where('trimester_id', $current->id)
                ->with('group')
                ->withCount([
                    'enrollments as student_count' => fn ($q) => $q
                        ->where('role', CourseEnrollment::STUDENT)
                        ->where('status', CourseEnrollment::ACTIVE),
                ])
                ->orderBy('course_code')
                ->orderBy('section_name')
                ->get()
            : collect();

        return Inertia::render('Administration/Index', [
            'trimesters' => $trimesters->map(fn (Trimester $t) => [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'range' => $t->starts_on->format('M j, Y').' - '.$t->ends_on->format('M j, Y'),
                'has_ended' => $t->hasEnded(),
            ])->values(),

            'trimester' => $current ? [
                'id' => $current->id,
                'name' => $current->name,
                'code' => $current->code,
                'range' => $current->starts_on->format('M j, Y').' - '.$current->ends_on->format('M j, Y'),
                'has_ended' => $current->hasEnded(),
                'archives_on' => $current->archivesOn()->format('M j, Y'),
                'days_until_archive' => $current->daysUntilArchive(),
            ] : null,

            'sections' => $sections->map(fn (CourseSection $section) => $this->card($section))->values(),

            'totals' => [
                'sections' => $sections->count(),
                'students' => $sections->sum('student_count'),
                'untaught' => $sections->filter(fn ($s) => $s->teacher() === null)->count(),
            ],

            'canManage' => $user->canIssueSectionAnnouncements(),
        ]);
    }

    /** One section, with everything the page would otherwise work out. */
    private function card(CourseSection $section): array
    {
        $teacher = $section->teacher();

        return [
            'id' => $section->id,
            'course_code' => $section->course_code,
            'course_title' => $section->course_title,
            'section_name' => $section->section_name,
            'code_badge' => strtoupper(substr($section->course_code, 0, 3)),
            'schedule_pattern' => $section->schedule_pattern,
            'start_time' => substr((string) $section->start_time, 0, 5),
            'end_time' => substr((string) $section->end_time, 0, 5),
            'schedule' => $section->scheduleLine(),
            'room' => $section->room,
            'seat_limit' => $section->seat_limit,
            'student_count' => (int) $section->student_count,
            'teacher' => $teacher?->full_name,
            'source' => $section->source,
            // A section with no group has nobody enrolled yet.
            'group_url' => $section->group ? route('groups.show', $section->group) : null,
            'update_url' => route('administration.update', $section),
        ];
    }

    /** The trimester being looked at: the one asked for, else the newest. */
    private function chosenTrimester(Request $request, $trimesters): ?Trimester
    {
        $asked = (int) $request->get('trimester', 0);

        if ($asked > 0) {
            $match = $trimesters->firstWhere('id', $asked);

            if ($match) {
                return $match;
            }
        }

        return Trimester::latest();
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->canIssueSectionAnnouncements(), 403);

        $data = $this->validated($request, [
            'trimester_id' => ['required', 'integer', 'exists:trimesters,id'],
            'course_code' => ['required', 'string', 'max:30'],
            'section_name' => ['required', 'string', 'max:30'],
        ]);

        $exists = CourseSection::where('trimester_id', $data['trimester_id'])
            ->where('course_code', $data['course_code'])
            ->where('section_name', $data['section_name'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'That section already exists in this trimester.');
        }

        $section = CourseSection::create([...$data, 'source' => 'manual']);

        $this->attachTeacher($section, $request->input('teacher_identifier'));

        // A section with a teacher gets its class group straight away; one
        // without waits, because a group with no owner has nobody to run it.
        if ($section->teacher()) {
            GroupSync::section($section->fresh());
        }

        return back()->with('success', $section->label().' added.');
    }

    public function update(Request $request, CourseSection $section)
    {
        abort_unless($request->user()->canIssueSectionAnnouncements(), 403);

        $section->update($this->validated($request));

        $this->attachTeacher($section, $request->input('teacher_identifier'));

        if ($section->teacher()) {
            // Keeps the group's name and schedule line in step with the row.
            GroupSync::section($section->fresh());
        }

        return back()->with('success', $section->label().' updated.');
    }

    /**
     * The fields shared by create and update.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $extra = []): array
    {
        return $request->validate([
            ...$extra,
            'course_title' => ['required', 'string', 'max:190'],
            'schedule_pattern' => ['nullable', 'string', 'max:60'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'room' => ['nullable', 'string', 'max:120'],
            'seat_limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
    }

    /**
     * Names the teacher of record by student ID, employee ID or email - the
     * same identifiers the roster CSV uses, so the registrar types one thing
     * whichever way a section was created.
     */
    private function attachTeacher(CourseSection $section, ?string $identifier): void
    {
        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            return;
        }

        $teacher = User::where('employee_id', $identifier)
            ->orWhere('email', $identifier)
            ->orWhere('student_id', $identifier)
            ->first();

        if (! $teacher) {
            return;
        }

        // One teacher of record: whoever held it steps down first.
        CourseEnrollment::where('section_id', $section->id)
            ->where('role', CourseEnrollment::TEACHER)
            ->where('user_id', '!=', $teacher->id)
            ->update(['status' => CourseEnrollment::DROPPED, 'dropped_at' => now()]);

        CourseEnrollment::updateOrCreate(
            ['section_id' => $section->id, 'user_id' => $teacher->id],
            [
                'role' => CourseEnrollment::TEACHER,
                'status' => CourseEnrollment::ACTIVE,
                'source' => 'manual',
                'dropped_at' => null,
            ]
        );
    }
}
