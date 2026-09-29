<?php

namespace App\Services\Ai;

use App\Models\Assignment;
use App\Models\CommunityGroup;
use App\Models\Friendship;
use App\Models\Notice;
use App\Models\User;
use App\Services\SocialRules;
use App\Support\AcademicCalendar;

/**
 * What the assistant knows about the asker before they ask anything.
 *
 * A short summary of their own state - classes and which ones have the
 * chat open, what is due, what is unread, their clubs, their next event -
 * read fresh on every question and placed in the instructions.
 *
 * Two reasons. The obvious one is that it makes the assistant feel like
 * it knows you: "which of my classes has the chat open?" is answered in
 * one step. The less obvious one is cost. Every tool call is another full
 * request, and on a free tier measured in tokens per minute a question
 * that needs three lookups can be refused. A few hundred tokens of
 * summary turns the most common personal questions into a single request.
 *
 * Everything here is the asker's own data, gathered by the same rules as
 * the tools, and it never contains anyone else's contact details. Counts
 * and titles only - no message text, no notice bodies. Those are fetched
 * by a tool only when a question actually needs them.
 */
class ContextSnapshot
{
    public static function for(User $user): string
    {
        $lines = [];

        $who = implode(', ', array_filter([
            $user->roleLabel(),
            $user->department,
            $user->year ?: null,
        ]));

        $lines[] = "Name: {$user->full_name} ({$who}).";

        [$classLines, $groupIds] = static::classes($user);

        if ($classLines === []) {
            $lines[] = 'Classes this trimester: none.';
        } else {
            $lines[] = 'Classes this trimester ('.count($classLines).'):';
            array_push($lines, ...$classLines);
        }

        if ($user->role !== 'faculty' && $groupIds !== []) {
            $lines[] = static::assignments($user, $groupIds);
        }

        $lines[] = 'Unread: '.implode(', ', [
            SocialRules::unreadNotificationCount($user).' notifications',
            ($user->isAdmin() ? 'messaging off' : SocialRules::unreadMessageCount($user).' messages'),
            Notice::unreadGroupCountFor($user).' class/club notice(s)',
        ]).'.';

        $requests = Friendship::where('status', 'pending')->where('addressee_id', $user->id)->count();

        if ($requests > 0) {
            $lines[] = "Connection requests waiting for them: {$requests}.";
        }

        $clubs = $user->clubs()->where('clubs.status', 'active')->get()
            ->map(fn ($c) => $c->name.(($r = trim((string) $c->pivot->club_role)) && ! in_array(strtolower($r), ['member', 'general member'], true) ? " ({$r})" : ''))
            ->take(8)->implode('; ');

        $lines[] = 'Clubs: '.($clubs !== '' ? $clubs : 'none').'.';

        $next = $user->registeredEvents()->where('events.start_datetime', '>=', now())
            ->orderBy('events.start_datetime')->first();

        if ($next) {
            $lines[] = 'Next registered event: '.$next->title.', '.$next->start_datetime->format('D, M j, g:i A').'.';
        }

        $program = AcademicCalendar::programFor($user);
        $calendar = AcademicCalendar::upcoming(1, $user)[0] ?? null;

        $lines[] = 'Academic calendar they follow: '.AcademicCalendar::PROGRAMS[$program]
            .(($term = AcademicCalendar::trimester($user)) !== '' ? ' ('.$term.')' : '')
            .($calendar ? '. Next: '.$calendar['label'].', '.$calendar['start']->format('D, M j').($calendar['is_range'] ? ' - '.$calendar['end']->format('D, M j') : '') : '. Nothing left on it')
            .'.';

        return implode("\n", $lines);
    }

    /**
     * One line per class, carrying the facts students ask about a class
     * that its timetable does not show.
     *
     * @return array{0: list<string>, 1: list<int>}
     */
    private static function classes(User $user): array
    {
        $groups = Scopes::courseGroups($user)->get();
        $lines = [];

        foreach ($groups as $group) {
            $section = $group->courseSection();
            $staff = $group->isStaff($user);
            $chatOpen = $group->canChat($user);
            $teacher = $staff ? null : $section?->teacher();

            $parts = array_filter([
                '['.trim(($section?->course_code ?? $group->name).' '.($section?->course_title ?? '')).']('.Links::group($group).')'
                    .($section?->section_name ? " (section {$section->section_name})" : ''),
                $staff ? 'you teach this' : ($teacher ? 'teacher: '.$teacher->full_name : null),
                $section?->scheduleLine() ?: null,
                'chat '.($chatOpen ? 'open' : 'closed'),
            ]);

            $unread = Notice::where('status', 'published')->where('group_id', $group->id)
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('notice_reads')
                    ->whereColumn('notice_reads.notice_id', 'notices.id')
                    ->where('notice_reads.user_id', $user->id))
                ->count();

            if ($unread > 0) {
                $parts[] = "{$unread} unread notice".($unread === 1 ? '' : 's');
            }

            if ($staff) {
                $parts[] = $group->members()->count().' members';
            }

            $lines[] = '- '.implode(' · ', $parts);
        }

        return [$lines, $groups->pluck('id')->map(fn ($id) => (int) $id)->all()];
    }

    /** @param list<int> $groupIds */
    private static function assignments(User $user, array $groupIds): string
    {
        $outstanding = Assignment::whereIn('group_id', $groupIds)
            ->where('status', Assignment::OPEN)
            ->whereDoesntHave('submissions', fn ($q) => $q->where('user_id', $user->id))
            ->with('group.section')
            ->orderByRaw('due_at IS NULL')->orderBy('due_at')
            ->get();

        $due = $outstanding->filter(fn ($a) => ! $a->isOverdue());
        $overdue = $outstanding->filter(fn ($a) => $a->isOverdue());

        if ($outstanding->isEmpty()) {
            return 'Assignments: nothing open that they still need to hand in.';
        }

        $next = $due->first();
        $nextText = $next
            ? ' Next: "'.$next->title.'" ('.($next->group?->courseSection()?->course_code ?? $next->group?->name).'), '
                .($next->due_at ? 'due '.$next->dueLabel().' - '.$next->dueRelative() : 'no deadline').'.'
            : '';

        return 'Assignments still to hand in: '.$due->count()
            .($overdue->count() ? ', plus '.$overdue->count().' past the deadline' : '')
            .'.'.$nextText;
    }
}
