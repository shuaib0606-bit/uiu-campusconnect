<?php

namespace App\Services\Ai\Tools;

use App\Models\Assignment;
use App\Models\CommunityGroup;
use App\Models\CommunityGroupFile;
use App\Models\GroupMessage;
use App\Models\Notice;
use App\Models\Post;
use App\Services\Ai\Links;
use App\Services\Ai\Scopes;

/**
 * Inside one of the viewer's own groups: a class, a club group or an open
 * group they have joined.
 *
 * The group is found with Scopes::findOwnGroup, which searches only the
 * viewer's memberships. A group they are not in is indistinguishable from
 * one that does not exist, so this tool cannot read another class's
 * notices, and cannot even confirm that a class exists.
 */
class GroupTool extends Tool
{
    private const ASPECTS = ['overview', 'announcements', 'materials', 'assignments', 'classmates', 'chat', 'wall'];

    public function name(): string
    {
        return 'group';
    }

    public function description(): string
    {
        return "Inside one of the asker's own classes or groups: announcements, materials, assignments, classmates, the class chat, or the wall.";
    }

    public function parameters(): array
    {
        return self::object([
            'group' => self::text('Course code, course title or group name, e.g. "CSE 3411".'),
            'aspect' => self::choice(self::ASPECTS, 'What to look at.'),
        ], ['group', 'aspect']);
    }

    public function run(array $arguments): array
    {
        $name = $this->arg($arguments, 'group');
        $group = Scopes::findOwnGroup($this->viewer, $name);

        if (! $group) {
            return ['error' => 'The asker is not in a class or group matching "'.$name.'".'];
        }

        return ['group' => $this->label($group), 'link' => Links::group($group)] + match ($this->arg($arguments, 'aspect')) {
            'announcements' => $this->announcements($group),
            'materials' => $this->materials($group),
            'assignments' => $this->assignments($group),
            'classmates' => $this->classmates($group),
            'chat' => $this->chat($group),
            'wall' => $this->wall($group),
            default => $this->overview($group),
        };
    }

    private function overview(CommunityGroup $group): array
    {
        $section = $group->courseSection();

        return array_filter([
            'kind' => $group->kind,
            'section' => $section?->section_name,
            'teacher' => Links::person($section?->teacher()),
            'schedule' => $section?->scheduleLine(),
            'room' => $section?->room,
            'trimester' => $section?->trimester?->name,
            'members' => $group->members()->count(),
            'chat' => $group->posting_mode === CommunityGroup::POSTING_EVERYONE
                ? 'open to students'
                : 'closed - only staff can post',
            'your_role' => $group->roleOf($this->viewer),
            'archived' => $group->archived_at ? 'read-only since '.$group->archived_at->toFormattedDateString() : null,
            'materials' => CommunityGroupFile::where('group_id', $group->id)->where('kind', CommunityGroupFile::MATERIAL)->count(),
            'assignments' => Assignment::where('group_id', $group->id)->count(),
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Class notices live in the notices table with group_id set, and older
     * announcements as notice-kind group files. Both are read here, so a
     * question about "what did the teacher announce" finds either.
     */
    private function announcements(CommunityGroup $group): array
    {
        $notices = Notice::where('status', 'published')
            ->where('group_id', $group->id)
            ->with('issuer:id,full_name')
            ->latest()
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Notice $n) => [
                'title' => $n->title,
                'text' => $this->excerpt($n->body),
                'by' => $n->issuer?->full_name,
                'posted' => $n->created_at?->toDayDateTimeString(),
                'urgent' => (bool) $n->is_urgent,
            ]);

        $older = CommunityGroupFile::where('group_id', $group->id)
            ->where('kind', CommunityGroupFile::NOTICE)
            ->with('uploader:id,full_name')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get()
            ->map(fn ($f) => [
                'title' => $f->title,
                'text' => $this->excerpt($f->body),
                'by' => $f->uploader?->full_name,
                'posted' => $f->created_at?->toDayDateTimeString(),
            ]);

        return ['announcements' => $notices->concat($older)->values()->all()];
    }

    private function materials(CommunityGroup $group): array
    {
        return ['materials' => CommunityGroupFile::where('group_id', $group->id)
            ->where('kind', CommunityGroupFile::MATERIAL)
            ->with('uploader:id,full_name')
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->map(fn ($f) => array_filter([
                'title' => $f->title,
                'file' => $f->original_name,
                'size' => $f->sizeLabel() ?: null,
                'note' => $this->excerpt($f->body, 150),
                'by' => $f->uploader?->full_name,
                'added' => $f->created_at?->toFormattedDateString(),
            ]))->values()->all()];
    }

    private function assignments(CommunityGroup $group): array
    {
        $staff = $group->isStaff($this->viewer);

        return ['assignments' => Assignment::where('group_id', $group->id)
            ->with('attachments')
            ->withCount('submissions')
            ->orderByRaw('due_at IS NULL')->orderBy('due_at')
            ->get()
            ->map(function (Assignment $a) use ($staff) {
                $mine = $staff ? null : $a->submissionFor($this->viewer);

                return array_filter([
                    'title' => $a->title,
                    'due' => $a->dueLabel() ?? 'no deadline',
                    'time_left' => $a->dueRelative(),
                    'status' => $a->status,
                    'instructions' => $this->excerpt($a->instructions, 200),
                    'handouts' => $a->attachments->pluck('original_name')->all() ?: null,
                    // Staff see how many have handed in, as on the page.
                    // A student sees only their own state, never a count
                    // that would hint at how the rest of the class is doing.
                    'handed_in' => $staff ? $a->submissions_count : null,
                    'you' => $staff ? null : ($mine
                        ? ($mine->is_late ? 'handed in late' : 'handed in')
                        : 'not handed in'),
                ]);
            })->values()->all()];
    }

    /**
     * The class list. Members see it on the group page, so the assistant
     * may list it - but with each person's own privacy settings applied,
     * which the page does not do for student IDs. Staff get IDs for their
     * own class, because the submissions list already shows them to staff
     * and a teacher needs them to match work to students.
     */
    private function classmates(CommunityGroup $group): array
    {
        // Only the teacher of a class gets the full roster: every student,
        // with IDs, whatever their privacy settings, because marking and
        // attendance need it. A club officer is "staff" of the club group
        // but is still a fellow student, so they read the member list under
        // the same rules as everyone else - each person's show_student_id
        // switch, and blocks hiding each side from the other.
        $teaches = $this->teaches($group);

        $query = $group->members()->where('users.status', 'active')->with('profile')
            ->orderBy('full_name')->limit(200);

        if (! $teaches) {
            Scopes::withoutBlocks($query, $this->viewer);
        }

        $people = $query->get();

        return [
            'count' => $group->members()->count(),
            'people' => $people->map(function ($person) use ($teaches) {
                $row = ['name' => $person->full_name, 'link' => Links::profile($person), 'role' => $person->pivot->role];
                $profile = $person->profile;
                $self = (int) $person->id === (int) $this->viewer->id;

                if ($self || $teaches || ! $profile || $profile->show_student_id) {
                    if ($person->student_id) {
                        $row['student_id'] = $person->student_id;
                    }
                }

                return $row;
            })->values()->all(),
        ];
    }

    /**
     * The class chat, under exactly the rule GroupChatController uses:
     * staff always, students only while the teacher has opened it.
     */
    private function chat(CommunityGroup $group): array
    {
        $open = $group->posting_mode === CommunityGroup::POSTING_EVERYONE;

        if (! $open && ! $group->isStaff($this->viewer)) {
            return ['chat' => 'closed', 'note' => 'The teacher has not opened this class to student conversation, so its chat is not available to students.'];
        }

        $lines = GroupMessage::where('group_id', $group->id)
            ->where('is_deleted', false)
            // The chat page itself shows the whole room. Here a block hides
            // the other person's lines, as it hides their posts on the wall,
            // except for the class's teacher, who has to see everything
            // said in a room they moderate.
            ->when(! $this->teaches($group), fn ($q) => $q->whereNotIn('user_id', Scopes::blockedIds($this->viewer)))
            ->with('author:id,full_name')
            ->latest('id')
            ->limit(15)
            ->get()
            ->reverse();

        return [
            'chat' => $open ? 'open to students' : 'closed to students (you are staff)',
            'chat_link' => Links::chat($group),
            'recent' => $lines->map(fn (GroupMessage $m) => [
                'from' => (int) $m->user_id === (int) $this->viewer->id ? 'you' : $m->author?->full_name,
                'text' => $this->excerpt($m->body, 200),
                'at' => $m->created_at?->format('D g:i A'),
            ])->values()->all(),
        ];
    }

    /** The group wall, through the feed's own viewer filter. */
    private function wall(CommunityGroup $group): array
    {
        return ['posts' => Post::where('posts.group_id', $group->id)
            ->where('posts.is_deleted', false)
            ->forViewer($this->viewer)
            ->with('user:id,full_name')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Post $p) => [
                'by' => $p->user?->full_name,
                'link' => Links::post($p),
                'text' => $this->excerpt($p->body, 200),
                'posted' => $p->created_at?->diffForHumans(),
            ])->values()->all()];
    }

    /** The teacher of this class (or an admin), as opposed to a club officer. */
    private function teaches(CommunityGroup $group): bool
    {
        return $this->viewer->isAdmin() || ($group->isCourse() && $group->isStaff($this->viewer));
    }

    private function label(CommunityGroup $group): string
    {
        $section = $group->courseSection();

        return $section
            ? trim($section->course_code.' '.$section->course_title)
            : $group->name;
    }
}
