<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Friendship;
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
        if ($from->isAdmin() || $to->isAdmin()) {
            return false;
        }
        if ($from->id === $to->id) {
            return false;
        }
        if (static::isBlocked($from, $to)) {
            return false;
        }
        if ($from->role === 'faculty') {
            return true;
        }

        return static::areConnected($from, $to);
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
