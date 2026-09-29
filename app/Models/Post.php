<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    /** How far back Trending looks. Older than this is not trending. */
    public const TRENDING_WINDOW_HOURS = 48;

    /** Hours in which a trending post's engagement score halves. */
    public const TRENDING_HALF_LIFE_HOURS = 6.0;

    /** Number of posts held in one ranked feed session. */
    public const RANKING_POOL_LIMIT = 400;

    /** Maximum friend/followed ids inlined into the social proof IN () lists. */
    public const CIRCLE_ID_LIMIT = 1000;

    protected $fillable = [
        'user_id', 'club_id', 'group_id', 'body', 'post_type', 'code_language',
        'media_type', 'media_path', 'media_thumb_path',
        'visibility', 'is_deleted', 'deleted_reason', 'approval_status',
    ];

    /** A group post waiting for the group owner's approval. */
    public const APPROVAL_PENDING = 'pending';

    /** A group post the owner turned down. Only its author can still see it. */
    public const APPROVAL_REJECTED = 'rejected';

    protected $with = ['media', 'link'];

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

    public function group(): BelongsTo
    {
        return $this->belongsTo(CommunityGroup::class, 'group_id');
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

    public function link(): HasOne
    {
        return $this->hasOne(PostLink::class);
    }

    /** Code is a post whose body is the code, with a language beside it. */
    public function isCode(): bool
    {
        return $this->post_type === 'code';
    }

    /**
     * Documents attached to this post. Separate from the picture grid because
     * they are read as a list of files, not looked at.
     *
     * @return Collection<int, PostMedia>
     */
    public function getDocumentsAttribute(): Collection
    {
        return $this->media->where('type', PostMedia::FILE)->values();
    }

    /**
     * Attachments from post_media rows, falling back to the media_path column.
     *
     * @return Collection<int, object{path: string, type: string, url: string}>
     */
    public function getMediaItemsAttribute(): Collection
    {
        // Documents are listed separately; this is the picture grid.
        $rows = $this->media->whereIn('type', ['image', 'video']);

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

    /**
     * Everything a post listing should normally show. Group posts are excluded
     * here rather than at each call site, because this one scope is what the
     * feed, search, profiles, clubs and the permalink all go through - so a
     * group wall cannot leak into any of them by somebody forgetting a filter.
     * The group page queries posts without this scope.
     */
    public function scopeVisible($query)
    {
        return $query->where('is_deleted', false)->whereNull('posts.group_id');
    }

    /**
     * Group-wall posts everyone in the group may see: not deleted, and not
     * waiting for approval or turned down. The wall adds the viewer's own
     * waiting posts, and the owner's approval queue, separately.
     */
    public function scopePublishedInGroup($query, int $groupId)
    {
        return $query->where('posts.group_id', $groupId)
            ->where('posts.is_deleted', false)
            ->whereNull('posts.approval_status');
    }

    public function isAwaitingApproval(): bool
    {
        return $this->approval_status === self::APPROVAL_PENDING;
    }

    /**
     * Whether this person may see this post at all - and so react to it,
     * comment on it, vote in its poll or save it. Feed posts keep their
     * existing rules; this is about group posts, which only members may
     * touch, and which nobody but the author and the people running the
     * group may touch while they wait for approval.
     */
    public function isOpenTo(?User $user): bool
    {
        if (! $user || $this->is_deleted) {
            return false;
        }

        if (! $this->group_id) {
            return true;
        }

        $group = $this->group;

        if (! $group || ! $group->hasMember($user)) {
            return false;
        }

        if ($this->approval_status === null) {
            return true;
        }

        return (int) $this->user_id === (int) $user->id || $group->isStaff($user);
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
                        ->where('user_blocks.blocked_id', $viewerId))))
            ->where(fn ($w) => $this->visibilityClause($w, $viewer));
    }

    /**
     * The audience rule for the visibility column. Anything that is not the
     * viewer's own post has to earn its place here.
     */
    protected function visibilityClause($query, $viewer)
    {
        $viewerId = (int) $viewer->id;

        $query
            ->where('posts.user_id', $viewerId)
            // Only the two restricted values narrow the audience. Anything
            // else, including the column default, is university-wide, so a
            // stray value can never make somebody's post disappear.
            ->orWhereNotIn('posts.visibility', ['department', 'connections']);

        // A department post needs both sides to name the same department.
        if (! empty($viewer->department)) {
            $query->orWhere(fn ($d) => $d
                ->where('posts.visibility', 'department')
                ->whereExists(fn ($q) => $q
                    ->selectRaw('1')
                    ->from('users as author')
                    ->whereColumn('author.id', 'posts.user_id')
                    ->where('author.department', $viewer->department)));
        }

        return $query->orWhere(fn ($c) => $c
            ->where('posts.visibility', 'connections')
            ->whereExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('friendships')
                ->where('friendships.status', 'accepted')
                ->where(fn ($p) => $p
                    ->where(fn ($x) => $x
                        ->whereColumn('friendships.requester_id', 'posts.user_id')
                        ->where('friendships.addressee_id', $viewerId))
                    ->orWhere(fn ($x) => $x
                        ->whereColumn('friendships.addressee_id', 'posts.user_id')
                        ->where('friendships.requester_id', $viewerId)))));
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

    /**
     * What the campus is actually talking about right now.
     *
     * Deliberately impersonal: no friend or follow weighting, so this is the
     * one tab that shows a student something from outside their own circle.
     * Engagement is damped by LN so a single post with 400 reactions cannot
     * own the tab, and decayed steeply so yesterday's argument drops away.
     */
    public function scopeTrendingFor($query, ?Carbon $at = null)
    {
        $at = $at ?: Carbon::now();
        $halfLifeMinutes = self::TRENDING_HALF_LIFE_HOURS * 60;

        $sql = '('
            .'('.self::COMMENT_WEIGHT.' * LN(1 + (SELECT COUNT(*) FROM comments AS tc'
            .' WHERE tc.post_id = posts.id AND tc.is_deleted = 0))'
            .' + '.self::REACTION_WEIGHT.' * LN(1 + (SELECT COUNT(*) FROM post_reactions AS tr'
            .' WHERE tr.post_id = posts.id))'
            .' + '.self::CIRCLE_REACTION_WEIGHT.' * LN(1 + (SELECT COUNT(*) FROM post_shares AS ts'
            .' WHERE ts.post_id = posts.id))'
            .') * POW(0.5, GREATEST(TIMESTAMPDIFF(MINUTE, posts.created_at, ?), 0) / '.$halfLifeMinutes.')'
            .')';

        return $query
            ->where('posts.created_at', '>=', $at->copy()->subHours(self::TRENDING_WINDOW_HOURS))
            ->selectRaw("posts.id, {$sql} AS trend_score", [$at])
            ->orderByDesc('trend_score')
            ->orderByDesc('posts.id');
    }

    /** Posts by people in a given department. */
    public function scopeFromDepartment($query, ?string $department)
    {
        if (! $department) {
            // Nothing rather than everything: an empty tab is honest, a tab
            // showing the whole campus under the heading "Department" is not.
            return $query->whereRaw('1 = 0');
        }

        return $query->whereExists(fn ($q) => $q->selectRaw('1')
            ->from('users as dept_author')
            ->whereColumn('dept_author.id', 'posts.user_id')
            ->where('dept_author.department', $department));
    }

    /**
     * Posts by the viewer's own batch. There is no admission-year column, so
     * a batch is taken as the people in the same department and the same year
     * of study - which is who a student actually means by "my batch".
     */
    public function scopeFromBatchOf($query, ?User $viewer)
    {
        if (! $viewer || ! $viewer->department || ! $viewer->year) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereExists(fn ($q) => $q->selectRaw('1')
            ->from('users as batch_author')
            ->whereColumn('batch_author.id', 'posts.user_id')
            ->where('batch_author.department', $viewer->department)
            ->where('batch_author.year', $viewer->year));
    }

    /** Posts attached to a club the viewer belongs to. */
    public function scopeInClubsOf($query, ?User $viewer)
    {
        if (! $viewer) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereNotNull('posts.club_id')
            ->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('club_members')
                ->whereColumn('club_members.club_id', 'posts.club_id')
                ->where('club_members.user_id', $viewer->id));
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
