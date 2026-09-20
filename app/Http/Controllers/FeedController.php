<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\Post;
use App\Models\PostView;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FeedController extends Controller
{
    /**
     * The feed's tabs, in the order they appear. For You and Trending are
     * ranked; the rest are chronological filters over the same posts.
     */
    public const TABS = [
        'for-you' => 'For You',
        'following' => 'Following',
        'trending' => 'Trending',
        'department' => 'Department',
        'batch' => 'My Batch',
        'clubs' => 'My Clubs',
    ];

    /** What an empty tab should say, so it reads as a state and not a fault. */
    public const EMPTY_STATE = [
        'for-you' => ['Nothing here yet', 'Be the first to share something with campus.'],
        'following' => ['No posts from people you follow', 'Follow more people to see their posts here.'],
        'trending' => ['Nothing is trending', 'Trending looks at the last two days. Check back when campus wakes up.'],
        'department' => ['Nothing from your department yet', 'Posts from people in your department will appear here.'],
        'batch' => ['Nothing from your batch yet', 'Posts from people in your department and year will appear here.'],
        'clubs' => ['Nothing from your clubs yet', 'Posts your clubs share will appear here.'],
    ];

    /** Posts per batch. */
    private const PER_PAGE = 10;

    /** Lifetime of one ranked feed snapshot, in minutes. */
    private const FEED_SESSION_MINUTES = 10;

    /** Maximum post ids accepted in one mark-seen call. */
    private const MAX_SEEN_IDS = 100;

    public function welcome()
    {
        if (Auth::check()) {
            return redirect()->route('feed.index');
        }

        return view('welcome');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $tab = $this->tab($request);

        $batch = $this->batch($user, $tab, $this->cursor($request));

        $posts = $batch['posts'];
        $nextCursor = $batch['nextCursor'];
        $hasMore = $batch['hasMore'];

        $upcomingEvents = Event::where('status', 'published')
            ->where('start_datetime', '>=', now())
            ->orderBy('start_datetime')
            ->limit(3)
            ->get();

        $suggestedClubs = Club::where('status', 'active')
            ->whereDoesntHave('members', fn ($q) => $q->where('users.id', $user->id))
            ->withCount('members')
            ->inRandomOrder()
            ->limit(3)
            ->get();

        return view('feed.index', [
            'posts' => $posts,
            'tab' => $tab,
            'tabs' => $this->tabsFor($user),
            'emptyState' => self::EMPTY_STATE[$tab] ?? self::EMPTY_STATE['for-you'],
            'nextCursor' => $nextCursor,
            'hasMore' => $hasMore,
            'upcomingEvents' => $upcomingEvents,
            'suggestedClubs' => $suggestedClubs,
        ]);
    }

    /** JSON endpoint for infinite scroll. Returns rendered post HTML. */
    public function more(Request $request)
    {
        $user = $request->user();
        $tab = $this->tab($request);

        $batch = $this->batch($user, $tab, $this->cursor($request));

        return response()->json([
            'html' => $batch['posts']->isEmpty()
                ? ''
                : view('feed._posts', ['posts' => $batch['posts']])->render(),
            'nextCursor' => $batch['nextCursor'],
            'hasMore' => $batch['hasMore'],
        ]);
    }

    /** Records posts the viewer has seen. Seen posts are demoted from the next ranked session onwards. */
    public function markSeen(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:'.self::MAX_SEEN_IDS],
            'ids.*' => ['integer'],
        ]);

        $ids = array_slice(array_unique(array_map('intval', $data['ids'])), 0, self::MAX_SEEN_IDS);

        // Only ids with an existing post satisfy the foreign key.
        $existing = Post::whereIn('id', $ids)->pluck('id');

        if ($existing->isEmpty()) {
            return response()->json(['success' => true, 'recorded' => 0]);
        }

        $now = now();
        $userId = $request->user()->id;

        PostView::upsert(
            $existing->map(fn ($id) => [
                'post_id' => $id,
                'user_id' => $userId,
                'seen_at' => $now,
            ])->all(),
            ['post_id', 'user_id'],
            ['seen_at']
        );

        return response()->json(['success' => true, 'recorded' => $existing->count()]);
    }

    private function tab(Request $request): string
    {
        $tab = (string) $request->get('tab', '');

        return array_key_exists($tab, self::TABS) ? $tab : 'for-you';
    }

    /**
     * Which tabs this person can actually use. A student with no department
     * recorded gets no Department tab rather than an empty one, and somebody
     * in no clubs gets no Clubs tab.
     *
     * @return array<string, string>
     */
    private function tabsFor(User $user): array
    {
        $tabs = self::TABS;

        if (! $user->department) {
            unset($tabs['department']);
        }

        if (! $user->department || ! $user->year) {
            unset($tabs['batch']);
        }

        if (! DB::table('club_members')->where('user_id', $user->id)->exists()) {
            unset($tabs['clubs']);
        }

        return $tabs;
    }

    private function cursor(Request $request): ?string
    {
        $cursor = $request->query('cursor');

        return is_string($cursor) ? $cursor : null;
    }

    /**
     * @return array{posts: Collection, nextCursor: ?string, hasMore: bool}
     */
    private function batch(User $user, string $tab, ?string $cursor): array
    {
        return match ($tab) {
            'following' => $this->chronologicalBatch($user, $cursor, fn ($q) => $q
                ->whereIn('posts.user_id', fn ($sub) => $sub
                    ->select('followed_id')
                    ->from('user_follows')
                    ->where('follower_id', $user->id))),

            'department' => $this->chronologicalBatch($user, $cursor, fn ($q) => $q
                ->fromDepartment($user->department)),

            'batch' => $this->chronologicalBatch($user, $cursor, fn ($q) => $q
                ->fromBatchOf($user)),

            'clubs' => $this->chronologicalBatch($user, $cursor, fn ($q) => $q
                ->inClubsOf($user)),

            'trending' => $this->rankedBatch($user, $cursor, 'trending'),

            default => $this->rankedBatch($user, $cursor, 'for-you'),
        };
    }

    /** Following tab: reverse-chronological, keyset paginated on (created_at, id). */
    /**
     * Newest first, narrowed by whatever the tab is about. Every tab except
     * For You and Trending is chronological, because a narrow feed is one you
     * read top to bottom rather than one that needs ranking.
     */
    private function chronologicalBatch(User $user, ?string $cursor, ?\Closure $filter = null): array
    {
        [$createdAt, $lastId] = $this->parseChronologicalCursor($cursor);

        $query = $this->baseQuery($user);

        if ($filter) {
            $filter($query);
        }

        $rows = $query
            ->olderThanCursor($createdAt, $lastId)
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id')
            // One extra row signals whether further posts remain.
            ->limit(self::PER_PAGE + 1)
            ->get();

        $hasMore = $rows->count() > self::PER_PAGE;
        $posts = $rows->take(self::PER_PAGE);
        $last = $posts->last();

        return [
            'posts' => $posts,
            'nextCursor' => $hasMore && $last
                ? 'c.'.$last->created_at->format('YmdHis').'.'.$last->id
                : null,
            'hasMore' => $hasMore,
        ];
    }

    /**
     * For You and Trending: the ordered ids are snapshotted per session and
     * the cursor is (token, offset) into that list, so scrolling does not
     * reshuffle under the reader. The mode is part of the cache key, or the
     * two tabs would hand each other their snapshots.
     */
    private function rankedBatch(User $user, ?string $cursor, string $mode = 'for-you'): array
    {
        [$token, $offset] = $this->parseRankedCursor($cursor);

        $ids = Cache::remember(
            "feed:rank:{$mode}:{$user->id}:{$token}",
            now()->addMinutes(self::FEED_SESSION_MINUTES),
            fn () => $mode === 'trending' ? $this->trendingIds($user) : $this->rankedIds($user)
        );

        $slice = array_slice($ids, $offset, self::PER_PAGE);
        $hasMore = count($ids) > $offset + self::PER_PAGE;

        return [
            'posts' => $this->fetchPosts($user, $slice),
            'nextCursor' => $hasMore ? 'r.'.$token.'.'.($offset + self::PER_PAGE) : null,
            'hasMore' => $hasMore,
        ];
    }

    /** @return list<int> */
    private function trendingIds(User $user): array
    {
        $at = now();

        return Post::query()
            ->visible()
            ->forViewer($user)
            ->trendingFor($at)
            ->limit(Post::RANKING_POOL_LIMIT)
            ->get()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function rankedIds(User $user): array
    {
        $at = now();

        $ids = Post::query()
            ->visible()
            ->forViewer($user)
            ->inRankingWindow($at)
            ->rankedFor($user, $at)
            ->limit(Post::RANKING_POOL_LIMIT)
            ->get()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Rank without the recency window when it matches nothing.
        if ($ids === []) {
            $ids = Post::query()
                ->visible()
                ->forViewer($user)
                ->rankedFor($user, $at)
                ->limit(Post::RANKING_POOL_LIMIT)
                ->get()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $ids;
    }

    /** Hydrates one page of posts. FIELD() restores the ranked id order and is MySQL-specific. */
    private function fetchPosts(User $user, array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return $this->baseQuery($user)
            ->whereIn('posts.id', $ids)
            ->orderByRaw('FIELD(posts.id, '.implode(',', $ids).')')
            ->get();
    }

    /** Shared eager-load set for every relation the post partial renders. */
    private function baseQuery(User $user)
    {
        return Post::visible()
            ->forViewer($user)
            ->with([
                'user.profile', 'reactions', 'saves', 'pollOptions.votes',
                'comments.user.profile', 'comments.likes',
                'comments.replies.user.profile', 'comments.replies.likes',
            ])
            ->withCount(['comments' => fn ($q) => $q->where('is_deleted', false)]);
    }

    /**
     * @return array{0: ?Carbon, 1: ?int}
     */
    private function parseChronologicalCursor(?string $cursor): array
    {
        if ($cursor && preg_match('/^c\.(\d{14})\.(\d+)$/', $cursor, $m)) {
            try {
                return [Carbon::createFromFormat('YmdHis', $m[1]), (int) $m[2]];
            } catch (\Throwable $e) {
                // An unparsable cursor falls back to the first page.
            }
        }

        return [null, null];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function parseRankedCursor(?string $cursor): array
    {
        // The token is pattern-checked before use in a cache key, and the offset is clamped to the pool.
        if ($cursor && preg_match('/^r\.([a-f0-9]{16})\.(\d+)$/', $cursor, $m)) {
            return [$m[1], min((int) $m[2], Post::RANKING_POOL_LIMIT)];
        }

        // No usable cursor starts a new snapshot.
        return [bin2hex(random_bytes(8)), 0];
    }
}
