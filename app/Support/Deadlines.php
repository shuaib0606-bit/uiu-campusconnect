<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\CommunityGroup;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything with a date on it that a person has to act on.
 *
 * For a student: work set in their classes (and whether they have handed
 * it in), events they registered for, and from their program's academic
 * calendar the exams, fee instalments and admin cut-offs.
 *
 * For whoever teaches a class: the work they set, with how many students
 * have handed in, and the grade-submission dates from the calendar.
 *
 * Someone can be both - a student who is a teaching assistant in one class
 * sees that class's work the way its teacher does. Nothing here reads a
 * submission's file or another student's state; the counts are the same
 * ones the class's assignments panel already shows its staff.
 */
class Deadlines
{
    /** Work past its deadline stays in the list this long while it can still be handed in. */
    public const OVERDUE_DAYS = 14;

    /** A teacher keeps seeing an assignment this long after it closes, to follow up on hand-ins. */
    public const RECENT_DAYS = 7;

    public const STUDENT_CALENDAR = ['exam', 'payment', 'registration', 'deadline'];

    public const TEACHER_CALENDAR = ['exam', 'grading'];

    /**
     * @return Collection<int, array{kind: string, type: string, title: string, at: Carbon, end: ?Carbon, timed: bool, url: ?string, context: ?string, state: ?string, handed_in: ?int, class_size: ?int, note: ?string}>
     */
    public static function for(User $user, int $days = 30): Collection
    {
        $now = now();
        $until = $now->copy()->addDays($days)->endOfDay();

        [$studentIn, $teaching] = self::classes($user);

        $items = collect()
            ->merge(self::toHandIn($user, $studentIn, $now, $until))
            ->merge(self::setByMe($teaching, $now, $until))
            ->merge(self::events($user, $now, $until))
            ->merge(self::calendar($user, $teaching !== [], $until));

        return $items->sortBy(fn ($item) => [
            // Overdue work first: it is the only thing that is already late.
            $item['state'] === 'overdue' ? 0 : 1,
            $item['at']->getTimestamp(),
        ])->values();
    }

    public static function isTeacher(User $user): bool
    {
        return in_array($user->role, ['faculty', 'official'], true) || self::classes($user)[1] !== [];
    }

    /**
     * Course groups the person is in, split by whether they take or run them.
     *
     * @return array{0: list<int>, 1: list<int>}
     */
    private static function classes(User $user): array
    {
        $rows = DB::table('community_group_members')
            ->join('community_groups', 'community_groups.id', '=', 'community_group_members.group_id')
            ->where('community_group_members.user_id', $user->id)
            ->where('community_group_members.role', '!=', CommunityGroup::PENDING)
            ->where('community_groups.kind', CommunityGroup::KIND_COURSE)
            ->whereNull('community_groups.archived_at')
            ->get(['community_groups.id', 'community_groups.owner_id', 'community_group_members.role']);

        $student = [];
        $teaching = [];

        foreach ($rows as $row) {
            $staff = (int) $row->owner_id === $user->id || in_array($row->role, CommunityGroup::STAFF_ROLES, true);
            $staff ? $teaching[] = (int) $row->id : $student[] = (int) $row->id;
        }

        return [$student, $teaching];
    }

    private static function toHandIn(User $user, array $groupIds, Carbon $now, Carbon $until): array
    {
        if ($groupIds === []) {
            return [];
        }

        $assignments = Assignment::whereIn('group_id', $groupIds)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$now->copy()->subDays(self::OVERDUE_DAYS), $until])
            ->with(['group.section', 'submissions' => fn ($q) => $q->where('user_id', $user->id)])
            ->get();

        $items = [];

        foreach ($assignments as $a) {
            $mine = $a->submissions->first();
            $past = $a->due_at->lessThan($now);

            if ($past && ($mine || $a->status !== Assignment::OPEN || ! $a->allow_late)) {
                // Handed in, or can no longer be: nothing left to do.
                continue;
            }

            if (! $past && ! $mine && $a->status !== Assignment::OPEN) {
                continue;
            }

            $items[] = self::item('assignment', 'assignment', $a->title, $a->due_at, [
                'timed' => true,
                'url' => route('groups.show', [$a->group, 'panel' => 'assignments']),
                'context' => self::course($a),
                'state' => match (true) {
                    $mine !== null => $mine->is_late ? 'late' : 'submitted',
                    $past => 'overdue',
                    default => null,
                },
            ]);
        }

        return $items;
    }

    private static function setByMe(array $groupIds, Carbon $now, Carbon $until): array
    {
        if ($groupIds === []) {
            return [];
        }

        $assignments = Assignment::whereIn('group_id', $groupIds)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$now->copy()->subDays(self::RECENT_DAYS), $until])
            ->withCount('submissions')
            ->with('group.section')
            ->get();

        // Class size = members who are not staff, counted once per class.
        $sizes = DB::table('community_group_members')
            ->join('community_groups', 'community_groups.id', '=', 'community_group_members.group_id')
            ->join('users', 'users.id', '=', 'community_group_members.user_id')
            ->whereIn('community_group_members.group_id', $assignments->pluck('group_id')->unique())
            ->where('community_group_members.role', '!=', CommunityGroup::PENDING)
            ->whereNotIn('community_group_members.role', CommunityGroup::STAFF_ROLES)
            ->whereColumn('community_group_members.user_id', '!=', 'community_groups.owner_id')
            ->where('users.status', 'active')
            ->groupBy('community_group_members.group_id')
            ->selectRaw('community_group_members.group_id as gid, count(*) as n')
            ->pluck('n', 'gid');

        return $assignments->map(fn (Assignment $a) => self::item('assignment', 'assignment', $a->title, $a->due_at, [
            'timed' => true,
            'url' => route('groups.show', [$a->group, 'panel' => 'assignments']),
            'context' => self::course($a),
            'state' => $a->due_at->lessThan($now) ? 'past' : ($a->status === Assignment::OPEN ? null : 'closed'),
            'handed_in' => (int) $a->submissions_count,
            'class_size' => (int) ($sizes[$a->group_id] ?? 0),
        ]))->all();
    }

    private static function events(User $user, Carbon $now, Carbon $until): array
    {
        return $user->registeredEvents()
            ->where('events.status', 'published')
            ->where('events.end_datetime', '>=', $now)
            ->where('events.start_datetime', '<=', $until)
            ->orderBy('events.start_datetime')
            ->limit(10)
            ->get()
            ->map(fn (Event $e) => self::item('event', 'event', $e->title, $e->start_datetime, [
                'timed' => true,
                'url' => route('events.show', $e),
                'context' => $e->location ?: 'Event you registered for',
                'state' => $e->start_datetime->lessThanOrEqualTo($now) ? 'now' : null,
            ]))->all();
    }

    private static function calendar(User $user, bool $teaches, Carbon $until): array
    {
        $types = $teaches || in_array($user->role, ['faculty', 'official'], true)
            ? self::TEACHER_CALENDAR
            : self::STUDENT_CALENDAR;

        return array_map(fn ($row) => self::item('calendar', $row['type'], $row['label'], $row['start'], [
            'end' => $row['is_range'] ? $row['end'] : null,
            'url' => route('calendar.index'),
            'context' => 'Academic calendar',
            'state' => $row['in_progress'] ? 'now' : null,
            'note' => $row['note'],
        ]), AcademicCalendar::upcoming(20, $user, $types, until: $until));
    }

    private static function course(Assignment $a): ?string
    {
        $section = $a->group?->courseSection();

        return $section ? trim($section->course_code.($section->section_name ? ' · '.$section->section_name : '')) : $a->group?->name;
    }

    private static function item(string $kind, string $type, string $title, Carbon $at, array $extra = []): array
    {
        return [
            'kind' => $kind,
            'type' => $type,
            'title' => $title,
            'at' => $at,
            'end' => $extra['end'] ?? null,
            'timed' => $extra['timed'] ?? false,
            'url' => $extra['url'] ?? null,
            'context' => $extra['context'] ?? null,
            'state' => $extra['state'] ?? null,
            'handed_in' => $extra['handed_in'] ?? null,
            'class_size' => $extra['class_size'] ?? null,
            'note' => $extra['note'] ?? null,
        ];
    }

    /** "Today, 11:59 PM", "Tomorrow", "Thu, Oct 2", "2 days overdue". */
    public static function when(array $item): string
    {
        $at = $item['at'];
        $today = Carbon::today();
        $time = $item['timed'] ? ', '.$at->format('g:i A') : '';

        if ($item['state'] === 'overdue') {
            return 'Was due '.$at->format('M j').' - '.$at->diffForHumans(null, true).' overdue';
        }

        if ($item['state'] === 'past') {
            return 'Was due '.$at->format('D, M j');
        }

        if ($item['end'] && $item['state'] === 'now') {
            return 'Until '.$item['end']->format('D, M j');
        }

        $day = $at->copy()->startOfDay();

        $label = match (true) {
            $day->equalTo($today) => 'Today',
            $day->equalTo($today->copy()->addDay()) => 'Tomorrow',
            $day->equalTo($today->copy()->subDay()) => 'Yesterday',
            $day->lessThan($today->copy()->addDays(7)) && $day->greaterThan($today) => $at->format('l'),
            default => $at->format('D, M j'),
        };

        return $label.$time.($item['end'] ? ' - '.$item['end']->format('M j') : '');
    }

    /** Whole days until it is due, for colouring; negative once past. */
    public static function daysLeft(array $item): int
    {
        return (int) Carbon::today()->diffInDays($item['at']->copy()->startOfDay(), false);
    }
}
