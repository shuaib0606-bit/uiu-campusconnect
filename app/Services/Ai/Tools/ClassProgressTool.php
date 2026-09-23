<?php

namespace App\Services\Ai\Tools;

use App\Models\Assignment;
use App\Models\CommunityGroup;
use App\Models\User;
use App\Services\Ai\Scopes;

/**
 * For teachers: who has handed in, and who has not.
 *
 * The same information as View submissions on the assignments panel,
 * which only a class's staff can open. The tool is offered only to people
 * who are staff somewhere, and run() checks staff status on the specific
 * class again - being left out of the list is a courtesy that saves
 * tokens, not the protection.
 */
class ClassProgressTool extends Tool
{
    public function name(): string
    {
        return 'class_progress';
    }

    public function description(): string
    {
        return 'For teachers only: hand-in progress in a class they teach - who has submitted, who has not, who was late.';
    }

    public function parameters(): array
    {
        return self::object([
            'group' => self::text('Course code or title of a class the asker teaches.'),
            'assignment' => self::text('Optional words from the assignment title. Empty for all assignments.'),
        ], ['group']);
    }

    public function availableTo(User $user): bool
    {
        return $user->isAdmin() || Scopes::memberGroups($user)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('community_group_members as staff')
                ->whereColumn('staff.group_id', 'community_groups.id')
                ->where('staff.user_id', $user->id)
                ->whereIn('staff.role', CommunityGroup::STAFF_ROLES))
            ->exists();
    }

    public function run(array $arguments): array
    {
        $group = Scopes::findOwnGroup($this->viewer, $this->arg($arguments, 'group'));

        if (! $group || ! ($group->isStaff($this->viewer) || $this->viewer->isAdmin())) {
            return ['error' => 'Hand-in progress is only available to the teachers of a class.'];
        }

        $wanted = $this->arg($arguments, 'assignment');

        $assignments = Assignment::where('group_id', $group->id)
            ->when($wanted !== '', fn ($q) => $q->where('title', 'like', '%'.addcslashes($wanted, '%_\\').'%'))
            ->with('submissions.student:id,full_name,student_id')
            ->orderByRaw('due_at IS NULL')->orderBy('due_at')
            ->get();

        if ($assignments->isEmpty()) {
            return ['error' => 'No assignment'.($wanted !== '' ? ' matching "'.$wanted.'"' : '').' in this class.'];
        }

        // Students only: staff are members of the class but do not hand in.
        $students = $group->members()->wherePivotNotIn('role', CommunityGroup::STAFF_ROLES)
            ->where('users.status', 'active')->orderBy('full_name')->get(['users.id', 'full_name', 'student_id']);

        $label = fn ($u) => trim($u->full_name.($u->student_id ? ' ('.$u->student_id.')' : ''));
        $detail = $assignments->count() === 1;

        return ['class' => $group->courseSection()?->course_code ?? $group->name, 'students' => $students->count(),
            'assignments' => $assignments->map(function (Assignment $a) use ($students, $label, $detail) {
                $handedIn = $a->submissions->keyBy('user_id');
                $missing = $students->reject(fn ($s) => $handedIn->has($s->id));

                $row = [
                    'title' => $a->title,
                    'due' => $a->dueLabel() ?? 'no deadline',
                    'status' => $a->status,
                    'handed_in' => $handedIn->count(),
                    'late' => $a->submissions->where('is_late', true)->count(),
                    'not_handed_in' => $missing->count(),
                ];

                // Names when the question is about one assignment.
                if ($detail) {
                    $row['not_handed_in_names'] = $missing->map($label)->values()->all();
                    $row['late_names'] = $a->submissions->where('is_late', true)
                        ->map(fn ($s) => $s->student ? $label($s->student) : null)->filter()->values()->all();
                }

                return $row;
            })->values()->all()];
    }
}
