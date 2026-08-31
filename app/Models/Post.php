<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    /** Points a post scores at age zero, before time decay. */
    public const RECENCY_WEIGHT = 12.0;

    /** Hours in which the recency score halves. */
    public const RECENCY_HALF_LIFE_HOURS = 12.0;

    /** Points added for a post by an accepted friend. */
    public const FRIEND_WEIGHT = 5.0;

    /** Points added for a post by a followed user. */
    public const FOLLOWED_WEIGHT = 3.0;

    /** Points added for a faculty, official, admin or verified author. */
    public const AUTHORITY_WEIGHT = 2.5;

    /** Multiplier on the damped comment count. */
    public const COMMENT_WEIGHT = 2.0;

    /** Multiplier on the damped reaction count. */
    public const REACTION_WEIGHT = 0.4;

    /** Multiplier on the damped comment count from the viewer's circle. */
    public const CIRCLE_COMMENT_WEIGHT = 3.0;

    /** Multiplier on the damped reaction count from the viewer's circle. */
    public const CIRCLE_REACTION_WEIGHT = 1.6;

    /** Points subtracted for a post the viewer has already seen. */
    public const SEEN_PENALTY = 6.0;

    /** Maximum age in days of a post eligible for ranking. */
    public const RANKING_WINDOW_DAYS = 60;

    /** Number of posts held in one ranked feed session. */
    public const RANKING_POOL_LIMIT = 400;

    /** Maximum friend/followed ids inlined into the social proof IN () lists. */
    public const CIRCLE_ID_LIMIT = 1000;

    protected $fillable = [
        'user_id', 'club_id', 'body', 'post_type', 'media_type', 'media_path', 'media_thumb_path',
        'visibility', 'is_deleted', 'deleted_reason',
    ];

    protected $with = ['media'];

    protected function casts(): array
    {
        return ['is_deleted' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id');
    }

    public function allComments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(PostReaction::class);
    }

    public function saves(): HasMany
    {
        return $this->hasMany(PostSave::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function pollOptions(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('position');
    }

    /** Attachments in the order the author arranged them, id as tiebreak. */
    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Attachments from post_media rows, falling back to the media_path column.
     *
     * @return Collection<int, object{path: string, type: string, url: string}>
     */
    public function getMediaItemsAttribute(): Collection
    {
        $rows = $this->media;

        if ($rows->isNotEmpty()) {
            return $rows
                ->map(fn (PostMedia $m) => (object) [
                    'path' => $m->path,
                    'type' => $m->type === 'video' ? 'video' : 'image',
                    'url' => Storage::url($m->path),
                ])
                ->values();
        }

        if ($this->media_path && in_array($this->media_type, ['image', 'video'], true)) {
            return collect([(object) [
                'path' => $this->media_path,
                'type' => $this->media_type,
                'url' => Storage::url($this->media_path),
            ]]);
        }

        return collect();
    }

    /** Mention rows written by App\Support\Mentions::sync() when the body is saved. */
    public function mentions(): MorphMany
    {
        return $this->morphMany(Mention::class, 'mentionable');
    }

    /** The users named in this post's body. */
    public function mentionedUsers(): MorphToMany
    {
        return $this->morphToMany(User::class, 'mentionable', 'mentions');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_deleted', false);
    }

    /**
     * Exclude posts the viewer hid, authors they have muted, and either side
     * of a block.
     */
    public function scopeForViewer($query, $viewer)
    {
        if (! $viewer) {
            return $query;
        }

        $viewerId = $viewer->id;

        return $query
            ->whereNotExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('hidden_posts')
                ->whereColumn('hidden_posts.post_id', 'posts.id')
                ->where('hidden_posts.user_id', $viewerId))
            ->whereNotExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('user_mutes')
                ->whereColumn('user_mutes.muted_id', 'posts.user_id')
                ->where('user_mutes.muter_id', $viewerId)
                ->where(fn ($w) => $w
                    ->whereNull('user_mutes.expires_at')
                    ->orWhere('user_mutes.expires_at', '>', now())))
            // Blocks cut both ways: neither party should see the other.
            ->whereNotExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('user_blocks')
                ->where(fn ($w) => $w
                    ->where(fn ($x) => $x
                        ->whereColumn('user_blocks.blocked_id', 'posts.user_id')
                        ->where('user_blocks.blocker_id', $viewerId))
                    ->orWhere(fn ($x) => $x
                        ->whereColumn('user_blocks.blocker_id', 'posts.user_id')
                        ->where('user_blocks.blocked_id', $viewerId))));
    }

    /**
     * The viewer's followed users and accepted friends.
     *
     * @return array{friends: int[], followed: int[], circle: int[]}
     */
    public static function viewerCircle($viewer): array
    {
        if (! $viewer) {
            return ['friends' => [], 'followed' => [], 'circle' => []];
        }

        $viewerId = (int) $viewer->id;

        $followed = DB::table('user_follows')
            ->where('follower_id', $viewerId)
            ->limit(self::CIRCLE_ID_LIMIT)
            ->pluck('followed_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $friends = DB::table('friendships')
            ->where('status', 'accepted')
            ->where(fn ($q) => $q
                ->where('requester_id', $viewerId)
                ->orWhere('addressee_id', $viewerId))
            ->limit(self::CIRCLE_ID_LIMIT)
            ->get(['requester_id', 'addressee_id'])
            // Friendship rows are symmetric; keep whichever side is not the viewer.
            ->map(fn ($row) => (int) $row->requester_id === $viewerId
                ? (int) $row->addressee_id
                : (int) $row->requester_id)
            ->all();

        return [
            'friends' => array_values(array_unique($friends)),
            'followed' => array_values(array_unique($followed)),
            'circle' => array_values(array_unique(array_merge($friends, $followed))),
        ];
    }

    /**
     * The feed ranking expression as [sql, bindings] for selectRaw().
     *
     * $at pins the clock so the score is deterministic across paged requests.
     */
    public static function rankingScoreSql($viewer, ?Carbon $at = null): array
    {
        $at = $at ?: Carbon::now();
        $viewerId = $viewer ? (int) $viewer->id : 0;
        $circle = static::viewerCircle($viewer);

        // IN () is not valid SQL, so an empty set becomes IN (0).
        $list = fn (array $ids) => $ids === [] ? '0' : implode(',', $ids);

        $friends = $list($circle['friends']);
        $followed = $list($circle['followed']);
        $inCircle = $list($circle['circle']);

        $halfLifeMinutes = self::RECENCY_HALF_LIFE_HOURS * 60;

        $sql = '('
            // Time decay: RECENCY_WEIGHT * 0.5 ^ (ageMinutes / halfLifeMinutes).
            // GREATEST clamps posts dated in the future to age zero.
            .self::RECENCY_WEIGHT.' * POW(0.5, GREATEST(TIMESTAMPDIFF(MINUTE, posts.created_at, ?), 0) / '.$halfLifeMinutes.')'

            // Affinity: friendship and follow never stack.
            .' + CASE'
            ." WHEN posts.user_id IN ({$friends}) THEN ".self::FRIEND_WEIGHT
            ." WHEN posts.user_id IN ({$followed}) THEN ".self::FOLLOWED_WEIGHT
            .' ELSE 0 END'

            // Authority: faculty/official/admin or an explicitly verified account.
            .' + CASE WHEN EXISTS ('
            .'SELECT 1 FROM users AS author WHERE author.id = posts.user_id'
            ." AND (author.role IN ('faculty', 'official', 'admin') OR author.is_verified = 1)"
            .') THEN '.self::AUTHORITY_WEIGHT.' ELSE 0 END'

            // Engagement, damped by LN.
            .' + '.self::COMMENT_WEIGHT.' * LN(1 + (SELECT COUNT(*) FROM comments AS ec'
            .' WHERE ec.post_id = posts.id AND ec.is_deleted = 0))'
            .' + '.self::REACTION_WEIGHT.' * LN(1 + (SELECT COUNT(*) FROM post_reactions AS er'
            .' WHERE er.post_id = posts.id))'

            // Social proof: the same two counts, restricted to the viewer's circle.
            .' + '.self::CIRCLE_COMMENT_WEIGHT.' * LN(1 + (SELECT COUNT(*) FROM comments AS sc'
            ." WHERE sc.post_id = posts.id AND sc.is_deleted = 0 AND sc.user_id IN ({$inCircle})))"
            .' + '.self::CIRCLE_REACTION_WEIGHT.' * LN(1 + (SELECT COUNT(*) FROM post_reactions AS sr'
            ." WHERE sr.post_id = posts.id AND sr.user_id IN ({$inCircle})))"

            // Penalty on posts the viewer has already seen.
            .' - CASE WHEN EXISTS ('
            .'SELECT 1 FROM post_views AS pv WHERE pv.post_id = posts.id AND pv.user_id = ?'
            .') THEN '.self::SEEN_PENALTY.' ELSE 0 END'
            .')';

        return [$sql, [$at->toDateTimeString(), $viewerId]];
    }

    /** Order by ranking score, highest first, with posts.id DESC as tiebreak. */
    public function scopeRankedFor($query, $viewer, ?Carbon $at = null)
    {
        [$sql, $bindings] = static::rankingScoreSql($viewer, $at);

        return $query
            ->selectRaw("posts.id, {$sql} AS rank_score", $bindings)
            ->orderByDesc('rank_score')
            ->orderByDesc('posts.id');
    }

    /** Restrict to posts within RANKING_WINDOW_DAYS of $at. */
    public function scopeInRankingWindow($query, ?Carbon $at = null)
    {
        $at = $at ?: Carbon::now();

        return $query->where('posts.created_at', '>=', $at->copy()->subDays(self::RANKING_WINDOW_DAYS));
    }

    /** Keyset pagination: posts strictly older than the (created_at, id) pair. */
    public function scopeOlderThanCursor($query, ?Carbon $createdAt, ?int $id)
    {
        if (! $createdAt || ! $id) {
            return $query;
        }

        return $query->where(fn ($q) => $q
            ->where('posts.created_at', '<', $createdAt)
            ->orWhere(fn ($w) => $w
                ->where('posts.created_at', '=', $createdAt)
                ->where('posts.id', '<', $id)));
    }
}
