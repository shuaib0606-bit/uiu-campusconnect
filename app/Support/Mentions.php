<?php

namespace App\Support;

use App\Models\Mention;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Parsing, resolution, storage and rendering of @-mentions. */
class Mentions
{
    /** Maximum mentions honoured per post or comment. */
    public const MAX_PER_ITEM = 20;

    /**
     * Handle tokens are [alnum][alnum . _ -]*[alnum], 1-50 characters.
     * The lookbehind keeps email addresses from matching.
     */
    public const PATTERN = '/(?<![A-Za-z0-9_@.\-])@([A-Za-z0-9](?:[A-Za-z0-9._\-]{0,48}[A-Za-z0-9])?)/';

    /** The bare token for a user: handle() without its leading '@'. */
    public static function tokenFor(User $user): string
    {
        return Str::lower(ltrim($user->handle(), '@'));
    }

    /**
     * Pull distinct, lower-cased handle tokens out of a body.
     *
     * @return string[]
     */
    public static function parse(?string $body): array
    {
        $body = (string) $body;

        if ($body === '' || ! str_contains($body, '@')) {
            return [];
        }

        preg_match_all(self::PATTERN, $body, $matches);

        $tokens = array_map(fn ($t) => Str::lower($t), $matches[1] ?? []);
        $tokens = array_values(array_unique(array_filter($tokens)));

        return array_slice($tokens, 0, self::MAX_PER_ITEM);
    }

    /**
     * Turn tokens into users. Only candidates whose handle equals the token are kept.
     *
     * @param  string[]  $tokens
     */
    public static function resolve(array $tokens): EloquentCollection
    {
        if ($tokens === []) {
            return new EloquentCollection;
        }

        $candidates = User::query()
            ->where('status', 'active')
            ->where(function ($q) use ($tokens) {
                $q->whereIn('student_id', $tokens)
                    ->orWhereIn('employee_id', $tokens)
                    ->orWhereIn(DB::raw("SUBSTRING_INDEX(email, '@', 1)"), $tokens);
            })
            ->limit(self::MAX_PER_ITEM * 4)
            ->get();

        return $candidates
            ->filter(fn (User $u) => in_array(self::tokenFor($u), $tokens, true))
            ->values();
    }

    /**
     * Persist the mentions found in a body against its post or comment.
     *
     * @return EloquentCollection<int, User> newly mentioned users
     */
    public static function sync(Model $mentionable, ?string $body, ?int $excludeUserId = null): EloquentCollection
    {
        $users = self::resolve(self::parse($body));

        if ($excludeUserId) {
            // Self-mentions are not stored or notified.
            $users = $users->reject(fn (User $u) => (int) $u->id === (int) $excludeUserId)->values();
        }

        $type = $mentionable->getMorphClass();
        $id = $mentionable->getKey();

        $rows = Mention::where('mentionable_type', $type)->where('mentionable_id', $id);
        $existingIds = (clone $rows)->pluck('user_id')->map(fn ($v) => (int) $v)->all();
        $keepIds = $users->pluck('id')->map(fn ($v) => (int) $v)->all();

        // An edit that drops a handle drops the mention with it.
        $stale = array_diff($existingIds, $keepIds);
        if ($stale !== []) {
            (clone $rows)->whereIn('user_id', $stale)->delete();
        }

        $newIds = array_values(array_diff($keepIds, $existingIds));
        if ($newIds !== []) {
            $now = now();
            Mention::insert(array_map(fn ($uid) => [
                'mentionable_type' => $type,
                'mentionable_id' => $id,
                'user_id' => $uid,
                'created_at' => $now,
            ], $newIds));
        }

        $mentionable->unsetRelation('mentions');
        $mentionable->unsetRelation('mentionedUsers');

        return $users->whereIn('id', $newIds)->values();
    }

    /**
     * Render a post or comment body with its mentions turned into profile links.
     * The caller prints the result unescaped, so the whole body is escaped here
     * before any anchor markup is substituted in.
     */
    public static function render(?Model $mentionable, ?string $body = null): string
    {
        $body = $body ?? (string) ($mentionable?->body ?? '');

        $safe = e((string) $body);

        if ($mentionable === null || ! str_contains($safe, '@')) {
            return $safe;
        }

        $users = $mentionable->mentionedUsers;

        if ($users === null || $users->isEmpty()) {
            return $safe;
        }

        $map = [];
        foreach ($users as $user) {
            $map[self::tokenFor($user)] = $user;
        }

        return preg_replace_callback(self::PATTERN, function (array $m) use ($map) {
            $user = $map[Str::lower($m[1])] ?? null;

            if (! $user) {
                return $m[0];
            }

            return '<a href="'.e(route('profile.show', $user->id)).'"'
                .' class="font-medium text-primary-600 hover:underline dark:text-primary-300"'
                .' title="'.e($user->full_name).'">'
                .e('@'.$m[1])
                .'</a>';
        }, $safe);
    }
}
