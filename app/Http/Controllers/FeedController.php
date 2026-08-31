<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\Notice;
use App\Models\Post;
use App\Models\PostView;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class FeedController extends Controller
{
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

        $latestNotice = Cache::remember('feed:latest-notice', 60, fn () => Notice::where('status', 'published')->latest()->first());

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

        return view('feed.index', compact(
            'posts', 'tab', 'nextCursor', 'hasMore',
            'latestNotice', 'upcomingEvents', 'suggestedClubs'
        ));
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
        return $request->get('tab') === 'following' ? 'following' : 'for-you';
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
        return $tab === 'following'
            ? $this->chronologicalBatch($user, $cursor)
            : $this->rankedBatch($user, $cursor);
    }

    /** Following tab: reverse-chronological, keyset paginated on (created_at, id). */
    private function chronologicalBatch(User $user, ?string $cursor): array
    {
        [$createdAt, $lastId] = $this->parseChronologicalCursor($cursor);

        $rows = $this->baseQuery($user)
            ->whereIn('posts.user_id', fn ($q) => $q
                ->select('followed_id')
                ->from('user_follows')
                ->where('follower_id', $user->id))
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

    /** For You tab: ranked ids are snapshotted per session and the cursor is (token, offset) into that list. */
    private function rankedBatch(User $user, ?string $cursor): array
    {
        [$token, $offset] = $this->parseRankedCursor($cursor);

        $ids = Cache::remember(
            "feed:rank:{$user->id}:{$token}",
            now()->addMinutes(self::FEED_SESSION_MINUTES),
            fn () => $this->rankedIds($user)
        );

        $slice = array_slice($ids, $offset, self::PER_PAGE);
        $hasMore = count($ids) > $offset + self::PER_PAGE;

        return [
            'posts' => $this->fetchPosts($user, $slice),
            'nextCursor' => $hasMore ? 'r.'.$token.'.'.($offset + self::PER_PAGE) : null,
            'hasMore' => $hasMore,
        ];
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
