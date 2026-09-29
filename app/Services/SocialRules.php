<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubPanelChange;
use App\Models\Friendship;
use App\Models\LostFoundClaim;
use App\Models\LostFoundItem;
use App\Models\MarketplaceItem;
use App\Models\Message;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Facades\Cache;

/** Role-based rules for connections, messaging, club posting and unread counts. */
class SocialRules
{
    public static function friendshipStatus(User $a, User $b): ?Friendship
    {
        return Friendship::query()
            ->where(function ($q) use ($a, $b) {
                $q->where('requester_id', $a->id)->where('addressee_id', $b->id);
            })
            ->orWhere(function ($q) use ($a, $b) {
                $q->where('requester_id', $b->id)->where('addressee_id', $a->id);
            })
            ->first();
    }

    public static function areConnected(User $a, User $b): bool
    {
        $status = static::friendshipStatus($a, $b);

        return $status && $status->status === 'accepted';
    }

    /**
     * @return array{0: bool, 1: string}
     */
    public static function canRequestConnection(User $from, User $to): array
    {
        if ($from->id === $to->id) {
            return [false, 'This is your own profile.'];
        }
        if ($from->isAdmin() || $to->isAdmin()) {
            return [false, 'Developer admin accounts are not used for personal connections.'];
        }
        if ($from->role === 'student' && $to->role !== 'student') {
            return [false, 'Students can send friend requests to students only.'];
        }
        if ($from->role === 'faculty' && $to->role === 'faculty') {
            return [true, 'Faculty-to-faculty connections are accepted directly.'];
        }
        if ($from->role === 'official' && $to->role === 'official') {
            return [true, 'Officials can connect with one another for office work.'];
        }
        if ($from->role === $to->role) {
            return [true, 'Connection request allowed.'];
        }

        return [false, 'This role combination does not allow friend requests.'];
    }

    /**
     * Whether $author may @mention $target. A student may mention any other
     * student, but a faculty member, official or admin only when the two are
     * connected (an approved connection application creates that link).
     * Nobody is mentioned across a block.
     */
    public static function canMention(User $author, User $target): bool
    {
        if ((int) $author->id === (int) $target->id) {
            return false;
        }

        if (static::isBlocked($author, $target)) {
            return false;
        }

        if ($author->role === 'student' && $target->role !== 'student') {
            return static::areConnected($author, $target);
        }

        return true;
    }

    public static function isBlocked(User $a, User $b): bool
    {
        return UserBlock::query()
            ->where(function ($q) use ($a, $b) {
                $q->where('blocker_id', $a->id)->where('blocked_id', $b->id);
            })
            ->orWhere(function ($q) use ($a, $b) {
                $q->where('blocker_id', $b->id)->where('blocked_id', $a->id);
            })
            ->exists();
    }

    public static function canMessage(User $from, User $to): bool
    {
        return static::canMessageWith(
            $from,
            $to,
            fn () => static::isBlocked($from, $to),
            fn () => static::areConnected($from, $to),
            fn () => static::hasBusinessWith($from, $to),
        );
    }

    /**
     * The messaging rule on its own, for callers that already know the two
     * facts it needs. A listing page loads them once instead of per row. The
     * closures are only run when the rule gets that far.
     *
     * $hasBusiness is optional because the people directory deliberately does
     * not use it: a marketplace listing should open one conversation about
     * that listing, not a Message button beside every name on campus.
     */
    public static function canMessageWith(
        User $from,
        User $to,
        callable $isBlocked,
        callable $isConnected,
        ?callable $hasBusiness = null,
    ): bool {
        if ($from->isAdmin() || $to->isAdmin()) {
            return false;
        }
        if ($from->id === $to->id) {
            return false;
        }
        if ($isBlocked()) {
            return false;
        }
        if ($from->role === 'faculty') {
            return true;
        }

        return $isConnected() || ($hasBusiness !== null && $hasBusiness());
    }

    /**
     * Whether these two have something to talk about that is not friendship.
     *
     * Connections are the right rule for social messaging, but they are the
     * wrong rule for the two boards: the whole point of a marketplace listing
     * or a lost property notice is that a stranger gets in touch. Before this
     * existed, "Message seller" led to a page that refused to send.
     *
     * Three things count, and each is narrow on purpose:
     *
     *  - The recipient has something posted and unsettled. Putting an item up
     *    for sale, or reporting a bag missing, is an invitation to be
     *    contacted about it. It expires when the listing is marked sold or
     *    the item is reunited.
     *  - One of them has claimed the other's lost property. The owner has to
     *    be able to answer a claim.
     *  - They have already exchanged a message. Whatever opened the
     *    conversation, both sides can carry it on - otherwise a seller could
     *    be written to and be unable to reply.
     *
     * None of this survives a block, which is checked before this runs.
     */
    public static function hasBusinessWith(User $from, User $to): bool
    {
        $selling = MarketplaceItem::where('user_id', $to->id)
            ->where('status', 'available')
            ->exists();

        if ($selling) {
            return true;
        }

        $looking = LostFoundItem::where('user_id', $to->id)
            ->where('status', 'open')
            ->exists();

        if ($looking) {
            return true;
        }

        $claim = LostFoundClaim::query()
            ->where(fn ($q) => $q->where('user_id', $from->id)
                ->whereIn('item_id', LostFoundItem::where('user_id', $to->id)->select('id')))
            ->orWhere(fn ($q) => $q->where('user_id', $to->id)
                ->whereIn('item_id', LostFoundItem::where('user_id', $from->id)->select('id')))
            ->exists();

        if ($claim) {
            return true;
        }

        return Message::query()
            ->where(fn ($q) => $q->where('sender_id', $from->id)->where('receiver_id', $to->id))
            ->orWhere(fn ($q) => $q->where('sender_id', $to->id)->where('receiver_id', $from->id))
            ->exists();
    }

    public static function canDeletePost(User $user, int $postOwnerId): bool
    {
        return $user->isAdmin() || $user->id === $postOwnerId;
    }

    public static function canEditPost(User $user, int $postOwnerId): bool
    {
        return $user->isAdmin() || $user->id === $postOwnerId;
    }

    public static function canManageClub(User $user, Club $club): bool
    {
        return $user->isAdmin() || $club->moderator_id === $user->id;
    }

    /**
     * The student who speaks for the club. Held by designation rather than a
     * column, because the panel handover is what moves it, and that already
     * writes designations.
     */
    public static function clubPresident(Club $club): ?User
    {
        return $club->members()
            ->whereRaw('LOWER(club_members.club_role) = ?', [ClubPanelChange::PRESIDENT])
            ->first();
    }

    public static function isClubPresident(User $user, Club $club): bool
    {
        return $club->members()
            ->where('users.id', $user->id)
            ->whereRaw('LOWER(club_members.club_role) = ?', [ClubPanelChange::PRESIDENT])
            ->exists();
    }

    /**
     * Who may put a new student panel forward. The outgoing president does it
     * in the normal course of things; the moderator or a super admin can do it
     * too, which is how a club that has no president yet gets its first one.
     */
    public static function canProposeClubPanel(User $user, Club $club): bool
    {
        return self::canManageClub($user, $club) || self::isClubPresident($user, $club);
    }

    /**
     * Who may agree to it. Deliberately not the president: a panel cannot
     * appoint itself.
     */
    public static function canApproveClubPanel(User $user, Club $club): bool
    {
        return self::canManageClub($user, $club);
    }

    /**
     * Only a super admin appoints or replaces the faculty moderator of a club.
     * Not the moderator themselves, and not the students.
     */
    public static function canChangeClubModerator(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * A student officer: somebody the moderator has given a designation or
     * event-posting rights to. They run the club day to day, but they are not
     * the moderator, so the things they decide still need signing off.
     */
    public static function isClubOfficer(User $user, Club $club): bool
    {
        return $club->members()
            ->where('users.id', $user->id)
            ->where(fn ($q) => $q
                ->where('club_members.can_post_events', true)
                ->orWhere(fn ($r) => $r->whereNotNull('club_members.club_role')
                    ->whereRaw('LOWER(club_members.club_role) <> ?', ['member'])))
            ->exists();
    }

    /** Who may put a name forward for a club designation. */
    public static function canNominateClubOfficer(User $user, Club $club): bool
    {
        return self::canManageClub($user, $club) || self::isClubOfficer($user, $club);
    }

    public static function canPostClubEvent(User $user, Club $club): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if ($user->role === 'faculty' && $user->authority_level === 'club_moderator' && $club->moderator_id === $user->id) {
            return true;
        }

        return $club->members()
            ->where('users.id', $user->id)
            ->wherePivot('can_post_events', true)
            ->exists();
    }

    public static function unreadNotificationCount(User $user): int
    {
        return Cache::remember(
            "user:{$user->id}:unread-notifications",
            now()->addSeconds(config('cache.ttl.counts', 30)),
            fn () => $user->notifications()->where('is_read', false)->count()
        );
    }

    public static function unreadMessageCount(User $user): int
    {
        return Cache::remember(
            "user:{$user->id}:unread-messages",
            now()->addSeconds(config('cache.ttl.counts', 30)),
            fn () => \App\Models\Message::where('receiver_id', $user->id)
                ->where('is_read', false)
                ->where('is_deleted_by_receiver', false)
                ->count()
        );
    }

    public static function forgetCountsFor(int $userId): void
    {
        Cache::forget("user:{$userId}:unread-notifications");
        Cache::forget("user:{$userId}:unread-messages");
    }
}
