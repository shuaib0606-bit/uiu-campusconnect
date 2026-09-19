<?php

namespace App\Support;

use App\Models\Club;
use App\Models\CommunityGroup;
use App\Models\CourseEnrollment;
use App\Models\CourseSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Keeps the derived groups in step with the records they stand for.
 *
 * A course group is not something anybody creates or joins: it exists because
 * a section exists, and you are in it because you are enrolled. This class is
 * the single place that turns academic and club records into group rows and
 * membership, so whatever writes those records - today a CSV, tomorrow an API
 * from the university - gets the same groups out of it.
 *
 * Every method here is safe to run again. Re-running reconciles.
 */
class GroupSync
{
    /**
     * Creates or refreshes the group for one section, and makes its membership
     * match the active enrolments exactly.
     */
    public static function section(CourseSection $section): CommunityGroup
    {
        $group = CommunityGroup::firstOrNew([
            'kind' => CommunityGroup::KIND_COURSE,
            'source_id' => $section->id,
        ]);

        $teacher = $section->teacher();

        $group->fill([
            'name' => $section->title(),
            'slug' => $group->slug ?: static::slug($section->label().'-'.$section->trimester_id),
            'group_type' => 'course',
            'description' => $section->course_title.' · '.$section->scheduleLine(),
            'department' => $group->department,
            // A class list is not public reading.
            'visibility' => 'private',
            'owner_id' => $teacher?->id ?? $group->owner_id,
        ]);

        // posting_mode is the teacher's decision, so an existing group keeps
        // whatever they chose. Only a brand new class starts locked down.
        if (! $group->exists) {
            $group->posting_mode = CommunityGroup::POSTING_STAFF;
        }

        // A group with no teacher yet still needs an owner_id for the column.
        $group->owner_id = $group->owner_id ?? $teacher?->id ?? $section->enrollments()->value('user_id');

        $group->save();

        static::syncCourseMembers($group, $section);

        return $group;
    }

    /**
     * Membership mirrors enrolment. It is mirrored rather than read live so
     * that every permission check in the app can keep asking the one question
     * "is this person a member of this group".
     */
    protected static function syncCourseMembers(CommunityGroup $group, CourseSection $section): void
    {
        $rows = $section->enrollments()
            ->where('status', CourseEnrollment::ACTIVE)
            ->get(['user_id', 'role', 'enrolled_at']);

        $wanted = [];

        foreach ($rows as $row) {
            $wanted[$row->user_id] = $row->role === CourseEnrollment::STUDENT ? 'member' : $row->role;
        }

        $pivot = DB::table('community_group_members')->where('group_id', $group->id);
        $existing = $pivot->pluck('role', 'user_id')->all();

        foreach ($wanted as $userId => $role) {
            if (! array_key_exists($userId, $existing)) {
                DB::table('community_group_members')->insert([
                    'group_id' => $group->id,
                    'user_id' => $userId,
                    'role' => $role,
                    'joined_at' => now(),
                ]);
            } elseif ($existing[$userId] !== $role) {
                DB::table('community_group_members')
                    ->where('group_id', $group->id)
                    ->where('user_id', $userId)
                    ->update(['role' => $role]);
            }
        }

        // Anybody no longer enrolled loses the room. Dropping a course has to
        // actually take the class group away, or the roster means nothing.
        $stale = array_diff(array_keys($existing), array_keys($wanted));

        if ($stale !== []) {
            DB::table('community_group_members')
                ->where('group_id', $group->id)
                ->whereIn('user_id', $stale)
                ->delete();
        }
    }

    /** Creates or refreshes the group that belongs to a club. */
    public static function club(Club $club): CommunityGroup
    {
        $group = CommunityGroup::firstOrNew([
            'kind' => CommunityGroup::KIND_CLUB,
            'source_id' => $club->id,
        ]);

        $group->fill([
            'name' => $club->name,
            'slug' => $group->slug ?: static::slug($club->slug.'-club'),
            'group_type' => 'club',
            'description' => $club->description,
            'visibility' => 'private',
            'owner_id' => $club->moderator_id ?? $group->owner_id,
        ]);

        if (! $group->exists) {
            // A club is a conversation from the start; a class is not.
            $group->posting_mode = CommunityGroup::POSTING_EVERYONE;
        }

        $group->owner_id = $group->owner_id ?? $club->moderator_id;

        if (! $group->owner_id) {
            // Nobody runs this club yet, so there is nothing to sync into.
            return $group;
        }

        $group->save();

        static::syncClubMembers($group, $club);

        return $group;
    }

    protected static function syncClubMembers(CommunityGroup $group, Club $club): void
    {
        $rows = DB::table('club_members')
            ->where('club_id', $club->id)
            ->get(['user_id', 'club_role', 'can_post_events']);

        $wanted = [];

        foreach ($rows as $row) {
            // A club officer - anybody with a role beyond plain member, or
            // who may post events - runs the group alongside the moderator.
            $isOfficer = $row->can_post_events
                || ($row->club_role && strtolower($row->club_role) !== 'member');

            $wanted[$row->user_id] = $isOfficer ? 'officer' : 'member';
        }

        if ($club->moderator_id) {
            $wanted[$club->moderator_id] = 'owner';
        }

        $existing = DB::table('community_group_members')
            ->where('group_id', $group->id)
            ->pluck('role', 'user_id')
            ->all();

        foreach ($wanted as $userId => $role) {
            if (! array_key_exists($userId, $existing)) {
                DB::table('community_group_members')->insert([
                    'group_id' => $group->id,
                    'user_id' => $userId,
                    'role' => $role,
                    'joined_at' => now(),
                ]);
            } elseif ($existing[$userId] !== $role) {
                DB::table('community_group_members')
                    ->where('group_id', $group->id)
                    ->where('user_id', $userId)
                    ->update(['role' => $role]);
            }
        }

        $stale = array_diff(array_keys($existing), array_keys($wanted));

        if ($stale !== []) {
            DB::table('community_group_members')
                ->where('group_id', $group->id)
                ->whereIn('user_id', $stale)
                ->delete();
        }
    }

    /** A slug nothing else is using. */
    protected static function slug(string $base): string
    {
        $slug = Str::slug($base) ?: 'group';
        $candidate = $slug;
        $i = 1;

        while (CommunityGroup::where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.(++$i);
        }

        return $candidate;
    }
}
