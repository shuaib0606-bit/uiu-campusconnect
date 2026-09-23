<?php

namespace App\Services\Ai;

use App\Models\CommunityGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The questions every tool asks the same way.
 *
 * Kept in one place so that "is this person in that group" and "may this
 * person see that person" have exactly one answer across the assistant -
 * the same answer the website gives, and in two places a stricter one,
 * noted where they occur.
 */
class Scopes
{
    /**
     * Groups the viewer is really in. A pending request to join does not
     * count, the same as CommunityGroup::members() everywhere else.
     */
    public static function memberGroups(User $viewer): Builder
    {
        return CommunityGroup::query()
            ->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('community_group_members')
                ->whereColumn('community_group_members.group_id', 'community_groups.id')
                ->where('community_group_members.user_id', $viewer->id)
                ->where('community_group_members.role', '!=', CommunityGroup::PENDING));
    }

    /** The viewer's live class groups for this trimester. */
    public static function courseGroups(User $viewer): Builder
    {
        return static::memberGroups($viewer)
            ->where('kind', CommunityGroup::KIND_COURSE)
            ->whereNull('archived_at')
            ->with(['section.trimester']);
    }

    /**
     * Finds one of the viewer's own groups by course code, course title or
     * group name. Only ever searches the viewer's groups, and says the
     * same thing whether a group does not exist or the viewer is not in
     * it - so the assistant cannot be used to map classes it has no
     * business knowing about.
     */
    public static function findOwnGroup(User $viewer, string $name): ?CommunityGroup
    {
        $needle = static::squash($name);

        if ($needle === '') {
            return null;
        }

        $groups = static::memberGroups($viewer)->whereNull('archived_at')->with('section')->get();

        // An exact code match beats a substring match, so "CSE 111" does
        // not land on "CSE 1111" when both exist.
        $exact = $groups->first(fn ($g) => static::squash($g->courseSection()?->course_code) === $needle);

        return $exact ?? $groups->first(function (CommunityGroup $g) use ($needle) {
            $haystack = static::squash(implode(' ', array_filter([
                $g->courseSection()?->course_code,
                $g->courseSection()?->course_title,
                $g->name,
            ])));

            return $haystack !== '' && str_contains($haystack, $needle);
        });
    }

    /**
     * People the viewer may be told about.
     *
     * Stricter than parts of the website in two ways, deliberately. Only
     * active accounts: a suspended or rejected account is not someone to
     * introduce. And a block in either direction hides each person from
     * the other - the profile page itself does not do this, but a block is
     * a person saying "keep me away from them", and the assistant is the
     * last thing that should help find them.
     */
    public static function visiblePeople(User $viewer): Builder
    {
        return static::withoutBlocks(User::query()->where('users.status', 'active'), $viewer);
    }

    /**
     * Drops anyone the viewer has blocked, or who has blocked the viewer,
     * from any query over users - a plain builder or a relation such as a
     * group's members. The subquery is correlated on users.id, so it costs
     * one index lookup per row rather than a list of every user.
     *
     * @template T of \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation
     *
     * @param  T  $query
     * @return T
     */
    public static function withoutBlocks($query, User $viewer)
    {
        return $query->whereNotExists(fn ($q) => $q
            ->selectRaw('1')
            ->from('user_blocks')
            ->where(fn ($w) => $w
                ->where(fn ($x) => $x
                    ->whereColumn('user_blocks.blocked_id', 'users.id')
                    ->where('user_blocks.blocker_id', $viewer->id))
                ->orWhere(fn ($x) => $x
                    ->whereColumn('user_blocks.blocker_id', 'users.id')
                    ->where('user_blocks.blocked_id', $viewer->id))));
    }

    /**
     * Everyone on the other side of a block with the viewer, either way.
     * For queries whose rows are not users, such as chat lines.
     *
     * @return list<int>
     */
    public static function blockedIds(User $viewer): array
    {
        return DB::table('user_blocks')->where('blocker_id', $viewer->id)->pluck('blocked_id')
            ->merge(DB::table('user_blocks')->where('blocked_id', $viewer->id)->pluck('blocker_id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** Lower case, no spaces - "CSE 3411" and "cse3411" are the same course. */
    public static function squash(?string $value): string
    {
        return Str::lower(preg_replace('/\s+/', '', (string) $value));
    }
}
