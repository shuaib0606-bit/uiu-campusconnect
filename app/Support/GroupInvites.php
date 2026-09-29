<?php

namespace App\Support;

use App\Models\CommunityGroup;
use App\Models\CourseEnrollment;
use App\Models\CourseSection;
use App\Models\GroupInvite;
use App\Models\User;
use App\Services\SocialRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who may be invited to a group, by whom, and the invitation itself.
 *
 * Rules:
 *  - Only the person who made a group invites people to it, and only to a
 *    group they made (class and club rosters come from enrolment and the
 *    club, so they are never invitation-based).
 *  - Anybody invited has to accept before they are a member.
 *  - A student may invite any student, but a faculty member or university
 *    official only when the two are connected - the same connection that
 *    lets a student message or mention them.
 *  - Nobody is invited across a block, and admin accounts are never invited.
 */
class GroupInvites
{
    /** Most people one invitation request may name, e.g. a whole class. */
    public const MAX_PER_REQUEST = 150;

    public static function canInviteTo(?User $inviter, CommunityGroup $group): bool
    {
        return $inviter !== null
            && $group->isOpen()
            && ! $group->isArchived()
            && $group->isOwnedBy($inviter);
    }

    /**
     * Why this person cannot be invited, or null when they can.
     */
    public static function reasonNotInvitable(User $inviter, CommunityGroup $group, User $target): ?string
    {
        if ((int) $target->id === (int) $inviter->id) {
            return 'That is you.';
        }
        if ($target->status !== 'active') {
            return 'This account is not active.';
        }
        if ($target->isAdmin()) {
            return 'Admin accounts cannot be invited to groups.';
        }
        if ($group->hasMember($target)) {
            return 'Already a member.';
        }
        if (SocialRules::isBlocked($inviter, $target)) {
            return 'You cannot invite this person.';
        }
        if (! self::roleAllows($inviter, $target)) {
            return 'Students can invite faculty and staff only after connecting with them.';
        }

        return null;
    }

    /** A student reaches a faculty member or official only through a connection. */
    public static function roleAllows(User $inviter, User $target): bool
    {
        if ($inviter->role !== 'student' || $target->role === 'student') {
            return true;
        }

        return SocialRules::areConnected($inviter, $target);
    }

    /**
     * People matching a search, for the invite box. Faculty and officials
     * only appear for a student when connected, so the box never offers
     * someone the server would refuse.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function search(User $inviter, CommunityGroup $group, string $term): Collection
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $prefix = str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $query = User::query()
            ->where('status', 'active')
            ->where('role', '!=', 'admin')
            ->whereKeyNot($inviter->id)
            // A block hides each side from the other, here as everywhere.
            ->whereNotIn('id', self::blockedIds($inviter) ?: [0])
            ->where(fn ($q) => $q
                ->where('full_name', 'like', $like)
                ->orWhere('student_id', 'like', $prefix)
                ->orWhere('employee_id', 'like', $prefix)
                ->orWhere('email', 'like', $prefix));

        if ($inviter->role === 'student') {
            $connected = self::connectedIds($inviter);
            $query->where(fn ($q) => $q
                ->where('role', 'student')
                ->orWhereIn('id', $connected ?: [0]));
        }

        $users = $query->orderBy('full_name')->limit(25)->get();

        return self::rows($inviter, $group, $users);
    }

    /**
     * The classes this person is taking right now, for the "From my
     * courses" picker.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function courses(User $inviter): Collection
    {
        $sectionIds = CourseEnrollment::where('user_id', $inviter->id)
            ->where('status', CourseEnrollment::ACTIVE)
            ->pluck('section_id');

        $blocked = self::blockedIds($inviter);

        return CourseSection::whereIn('id', $sectionIds)
            ->orderBy('course_code')
            ->orderBy('section_name')
            ->get()
            ->map(fn (CourseSection $s) => [
                'id' => $s->id,
                'label' => trim($s->course_code.' '.$s->section_name),
                'title' => $s->course_title ?? null,
                'students' => CourseEnrollment::where('section_id', $s->id)
                    ->where('role', CourseEnrollment::STUDENT)
                    ->where('status', CourseEnrollment::ACTIVE)
                    ->where('user_id', '!=', $inviter->id)
                    ->whereNotIn('user_id', $blocked ?: [0])
                    ->count(),
            ])
            ->values();
    }

    /**
     * Classmates in one of the inviter's own sections. Refuses a section the
     * inviter is not enrolled in, so nobody can list a class they are not in.
     *
     * @return Collection<int, array<string, mixed>>|null
     */
    public static function classmates(User $inviter, CommunityGroup $group, int $sectionId): ?Collection
    {
        $enrolled = CourseEnrollment::where('section_id', $sectionId)
            ->where('user_id', $inviter->id)
            ->where('status', CourseEnrollment::ACTIVE)
            ->exists();

        if (! $enrolled) {
            return null;
        }

        $ids = CourseEnrollment::where('section_id', $sectionId)
            ->where('role', CourseEnrollment::STUDENT)
            ->where('status', CourseEnrollment::ACTIVE)
            ->where('user_id', '!=', $inviter->id)
            ->pluck('user_id');

        $users = User::whereIn('id', $ids)
            ->whereNotIn('id', self::blockedIds($inviter) ?: [0])
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get();

        return self::rows($inviter, $group, $users);
    }

    /**
     * Sends invitations. Anybody who cannot be invited, or already has an
     * invitation waiting, is skipped rather than failing the whole request.
     *
     * @param  int[]  $userIds
     * @return array{sent: int, skipped: array<int, string>}
     */
    public static function send(User $inviter, CommunityGroup $group, array $userIds): array
    {
        $userIds = array_slice(array_values(array_unique(array_map('intval', $userIds))), 0, self::MAX_PER_REQUEST);
        $targets = User::whereIn('id', $userIds)->get();

        $sent = 0;
        $skipped = [];

        foreach ($targets as $target) {
            if ($reason = self::reasonNotInvitable($inviter, $group, $target)) {
                $skipped[$target->id] = $target->full_name.': '.$reason;

                continue;
            }

            $existing = GroupInvite::where('group_id', $group->id)->where('user_id', $target->id)->first();

            if ($existing && $existing->isPending()) {
                $skipped[$target->id] = $target->full_name.': already invited.';

                continue;
            }

            $invite = GroupInvite::updateOrCreate(
                ['group_id' => $group->id, 'user_id' => $target->id],
                ['invited_by' => $inviter->id, 'status' => GroupInvite::PENDING, 'responded_at' => null],
            );
            // A re-invitation should read as new, not as the old one.
            $invite->touch();

            Notifier::send(
                $target,
                $inviter,
                Notifier::GROUP_INVITE,
                'group_invite',
                $invite->id,
                'invited you to join '.$group->name.'.'
            );

            $sent++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * Accepting makes the person a member. If they had asked to join
     * already, that request simply becomes the membership.
     */
    public static function accept(GroupInvite $invite): void
    {
        DB::transaction(function () use ($invite) {
            DB::table('community_group_members')->updateOrInsert(
                ['group_id' => $invite->group_id, 'user_id' => $invite->user_id],
                ['role' => 'member', 'joined_at' => now()]
            );

            $invite->update(['status' => GroupInvite::ACCEPTED, 'responded_at' => now()]);
        });

        Notifier::send(
            $invite->invited_by,
            $invite->user,
            Notifier::GROUP_INVITE_ACCEPTED,
            'group',
            $invite->group_id,
            'accepted your invitation to '.$invite->group->name.'.'
        );
    }

    public static function decline(GroupInvite $invite): void
    {
        $invite->update(['status' => GroupInvite::DECLINED, 'responded_at' => now()]);
    }

    /** Everyone this person has blocked or been blocked by. */
    public static function blockedIds(User $user): array
    {
        return DB::table('user_blocks')->where('blocker_id', $user->id)->pluck('blocked_id')
            ->merge(DB::table('user_blocks')->where('blocked_id', $user->id)->pluck('blocker_id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** IDs of everyone this person has an accepted connection with. */
    public static function connectedIds(User $user): array
    {
        return DB::table('friendships')
            ->where('status', 'accepted')
            ->where(fn ($q) => $q->where('requester_id', $user->id)->orWhere('addressee_id', $user->id))
            ->get(['requester_id', 'addressee_id'])
            ->map(fn ($f) => (int) $f->requester_id === (int) $user->id ? (int) $f->addressee_id : (int) $f->requester_id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * One row per person for the picker, with whether they can be invited
     * and, if not, why.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private static function rows(User $inviter, CommunityGroup $group, $users): Collection
    {
        $ids = $users->pluck('id')->all();

        $members = DB::table('community_group_members')
            ->where('group_id', $group->id)
            ->whereIn('user_id', $ids ?: [0])
            ->where('role', '!=', CommunityGroup::PENDING)
            ->pluck('user_id')->map(fn ($v) => (int) $v)->all();

        $invited = GroupInvite::where('group_id', $group->id)
            ->whereIn('user_id', $ids ?: [0])
            ->where('status', GroupInvite::PENDING)
            ->pluck('user_id')->map(fn ($v) => (int) $v)->all();

        return $users->map(function (User $u) use ($inviter, $group, $members, $invited) {
            $state = in_array((int) $u->id, $members, true) ? 'member'
                : (in_array((int) $u->id, $invited, true) ? 'invited' : null);

            $reason = $state ? null : self::reasonNotInvitable($inviter, $group, $u);

            return [
                'id' => $u->id,
                'name' => $u->full_name,
                'subtitle' => implode(' · ', array_filter([
                    $u->roleLabel(),
                    $u->role === 'student' ? $u->student_id : $u->designation,
                    $u->department,
                ])),
                'avatar_url' => $u->avatar_url,
                'initials' => $u->avatar_initials,
                'state' => $state,
                'can_invite' => $state === null && $reason === null,
                'reason' => $reason,
            ];
        })->values();
    }
}
