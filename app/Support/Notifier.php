<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\User;
use App\Services\SocialRules;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates notifications. send() takes one recipient and collapses or dedupes
 * against an existing unread row; broadcast() writes a whole audience in chunks.
 */
class Notifier
{
    // Type values are stored in notifications.type and are permanent.

    public const POST_REACTION = 'post_reaction';

    public const COMMENT_REACTION = 'comment_reaction';

    public const POST_COMMENT = 'post_comment';

    public const COMMENT_REPLY = 'comment_reply';

    /** "X also commented on a post you commented on." */
    public const ALSO_COMMENTED = 'also_commented';

    public const MENTION = 'mention';

    public const FOLLOW = 'follow';

    public const FRIEND_REQUEST = 'friend_request';

    public const FRIEND_ACCEPT = 'friend_accept';

    public const NOTICE = 'notice';

    public const EVENT = 'event';

    public const SYSTEM = 'system';

    /** Types where repeat activity on the same entity collapses onto one unread row. */
    protected const COLLAPSIBLE = [
        self::POST_REACTION,
        self::COMMENT_REACTION,
        self::POST_COMMENT,
        self::ALSO_COMMENTED,
    ];

    /** Rows per INSERT during a broadcast. */
    public const CHUNK = 500;

    /** Maximum "also commented" notifications sent for one comment. */
    public const ALSO_COMMENTED_LIMIT = 40;

    /**
     * Create one notification, or fold it into an existing unread row.
     *
     * With an actor, $message is a predicate such as "reacted to your post."
     * and the actor's name is prefixed. Without one it is used verbatim.
     * $groupCount is the number of distinct people behind a collapsed row;
     * omitted, the existing count is left alone.
     *
     * @param  User|int|null  $recipient  recipient model or id
     */
    public static function send(
        User|int|null $recipient,
        ?User $actor,
        string $type,
        string $entityType,
        ?int $entityId,
        string $message,
        ?int $groupCount = null,
    ): ?Notification {
        $recipientId = $recipient instanceof User ? (int) $recipient->id : (int) $recipient;

        if ($recipientId <= 0) {
            return null;
        }

        // A user is never notified of their own action.
        if ($actor && (int) $actor->id === $recipientId) {
            return null;
        }

        // Blocks and live mutes suppress the notification.
        if ($actor && self::isSuppressed($recipientId, (int) $actor->id)) {
            return null;
        }

        $base = fn () => Notification::query()
            ->where('user_id', $recipientId)
            ->where('type', $type)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('is_read', false);

        // Collapsible types match on the entity alone; the rest also match on actor.
        $existing = in_array($type, self::COLLAPSIBLE, true)
            ? $base()->orderByDesc('id')->first()
            // "actor_id = NULL" is never true in SQL, so system rows need whereNull.
            : ($actor
                ? $base()->where('actor_id', $actor->id)->first()
                : $base()->whereNull('actor_id')->first());

        $count = $groupCount !== null
            ? max(1, $groupCount)
            : max(1, (int) ($existing->group_count ?? 1));

        $text = self::compose($actor, $message, $count);
        $now = Carbon::now();

        if ($existing) {
            $existing->forceFill([
                // The newest actor owns the avatar on a collapsed row.
                'actor_id' => $actor?->id,
                'message' => $text,
                'group_count' => $count,
                'created_at' => $now,
            ])->save();

            self::invalidate($recipientId);

            return $existing;
        }

        $notification = Notification::create([
            'user_id' => $recipientId,
            'actor_id' => $actor?->id,
            'type' => $type,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'message' => $text,
            'group_count' => $count,
            'is_read' => false,
            // $timestamps is false on the model, so created_at is set here.
            'created_at' => $now,
        ]);

        self::invalidate($recipientId);

        return $notification;
    }

    /**
     * Notify a list of recipients of the same thing. Use broadcast() for a
     * campus-wide audience.
     *
     * @param  iterable<User|int>  $recipients
     */
    public static function sendMany(
        iterable $recipients,
        ?User $actor,
        string $type,
        string $entityType,
        ?int $entityId,
        string $message,
    ): int {
        $sent = 0;

        foreach ($recipients as $recipient) {
            if (self::send($recipient, $actor, $type, $entityType, $entityId, $message)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Fan a single announcement out to an audience, in CHUNK-sized inserts.
     *
     * Unlike send(), $message is used verbatim. The NOT EXISTS guard makes a
     * repeated call idempotent.
     *
     * @param  EloquentBuilder<User>  $audience  a users query
     */
    public static function broadcast(
        EloquentBuilder $audience,
        ?User $actor,
        string $type,
        string $entityType,
        ?int $entityId,
        string $message,
        bool $respectSocialState = true,
    ): int {
        $excluded = [];

        if ($actor) {
            // The issuer is not notified of their own notice.
            $excluded[] = (int) $actor->id;

            if ($respectSocialState) {
                $excluded = array_merge($excluded, self::suppressedRecipientIds((int) $actor->id));
            }
        }

        $excluded = array_values(array_unique($excluded));

        $text = Str::limit($message, 255, '');
        $now = Carbon::now();
        $actorId = $actor?->id;
        $total = 0;

        $audience
            ->select('users.id')
            ->when($excluded !== [], fn ($q) => $q->whereNotIn('users.id', $excluded))
            ->whereNotExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('notifications')
                ->whereColumn('notifications.user_id', 'users.id')
                ->where('notifications.type', $type)
                ->where('notifications.entity_type', $entityType)
                ->where('notifications.entity_id', $entityId))
            ->chunkById(self::CHUNK, function ($users) use (&$total, $actorId, $type, $entityType, $entityId, $text, $now) {
                $rows = [];

                foreach ($users as $user) {
                    $rows[] = [
                        'user_id' => $user->id,
                        'actor_id' => $actorId,
                        'type' => $type,
                        'entity_type' => $entityType,
                        'entity_id' => $entityId,
                        'message' => $text,
                        'group_count' => 1,
                        'is_read' => false,
                        'created_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    Notification::insert($rows);
                    $total += count($rows);
                }
            }, 'users.id', 'id');

        // Unread-count caches are not invalidated here; they expire on their own.

        return $total;
    }

    /**
     * The audience for a university notice, as a users query.
     *
     * $audience is one of all, students, faculties or officials, optionally
     * narrowed by department. Department is matched with LIKE.
     *
     * @return EloquentBuilder<User>
     */
    public static function audienceQuery(?string $audience, ?string $department = null): EloquentBuilder
    {
        $query = User::query()->where('status', 'active');

        $role = match (Str::lower(trim((string) $audience))) {
            'students', 'student' => 'student',
            'faculties', 'faculty' => 'faculty',
            'officials', 'official' => 'official',
            default => null,
        };

        if ($role) {
            $query->where('role', $role);
        }

        if (filled($department)) {
            $query->where('department', 'like', '%'.trim($department).'%');
        }

        return $query;
    }

    /**
     * The actor's name, or "name and 4 others", followed by the predicate.
     * Truncated to the column's 255 characters.
     */
    protected static function compose(?User $actor, string $message, int $count): string
    {
        if (! $actor) {
            return Str::limit($message, 255, '');
        }

        $others = $count - 1;

        $label = $others > 0
            ? $actor->full_name.' and '.$others.' other'.($others > 1 ? 's' : '')
            : $actor->full_name;

        return Str::limit(trim($label.' '.$message), 255, '');
    }

    /** Blocks apply in both directions; a mute is one-directional and only while live. */
    protected static function isSuppressed(int $recipientId, int $actorId): bool
    {
        $blocked = DB::table('user_blocks')
            ->where(fn ($q) => $q->where('blocker_id', $recipientId)->where('blocked_id', $actorId))
            ->orWhere(fn ($q) => $q->where('blocker_id', $actorId)->where('blocked_id', $recipientId))
            ->exists();

        if ($blocked) {
            return true;
        }

        return DB::table('user_mutes')
            ->where('muter_id', $recipientId)
            ->where('muted_id', $actorId)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    /**
     * Every user who should not hear about this actor.
     *
     * @return int[]
     */
    protected static function suppressedRecipientIds(int $actorId): array
    {
        return DB::table('user_blocks')->where('blocked_id', $actorId)->pluck('blocker_id')
            ->merge(DB::table('user_blocks')->where('blocker_id', $actorId)->pluck('blocked_id'))
            ->merge(
                DB::table('user_mutes')
                    ->where('muted_id', $actorId)
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->pluck('muter_id')
            )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** Drops the recipient's cached unread counts. */
    protected static function invalidate(int $recipientId): void
    {
        SocialRules::forgetCountsFor($recipientId);
    }
}
