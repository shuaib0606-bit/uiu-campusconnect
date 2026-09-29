<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\User;
use App\Services\SocialRules;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    /**
     * The filter tabs, and the rows each one keeps.
     *
     * @var array<string, array{label: string, types: string[]|null, entities: string[]|null}>
     */
    public const FILTERS = [
        'all' => ['label' => 'All', 'types' => null, 'entities' => null],
        'unread' => ['label' => 'Unread', 'types' => null, 'entities' => null],
        'mentions' => ['label' => 'Mentions', 'types' => [Notifier::MENTION], 'entities' => null],
        'posts' => [
            'label' => 'Posts',
            'types' => [
                Notifier::POST_REACTION, Notifier::COMMENT_REACTION, Notifier::POST_COMMENT,
                Notifier::COMMENT_REPLY, Notifier::ALSO_COMMENTED, Notifier::MENTION,
            ],
            'entities' => ['post', 'comment'],
        ],
        'people' => [
            'label' => 'People',
            'types' => [Notifier::FOLLOW, Notifier::FRIEND_REQUEST, Notifier::FRIEND_ACCEPT, 'connection', 'connection_request', 'message', Notifier::GROUP_INVITE, Notifier::GROUP_INVITE_ACCEPTED],
            'entities' => null,
        ],
        'campus' => [
            'label' => 'Campus',
            'types' => [Notifier::NOTICE, Notifier::EVENT, Notifier::SYSTEM],
            'entities' => ['notice', 'event', 'club'],
        ],
    ];

    public function index(Request $request)
    {
        $user = $request->user();

        $filter = trim((string) $request->get('filter', 'all'));
        if (! array_key_exists($filter, self::FILTERS)) {
            $filter = 'all';
        }

        $counts = [
            'total' => $user->notifications()->count(),
            'unread' => $user->notifications()->unread()->count(),
            'mentions' => $user->notifications()->where('type', Notifier::MENTION)->unread()->count(),
        ];

        $notifications = $user->notifications()
            ->with('actor')
            ->when($filter === 'unread', fn ($q) => $q->unread())
            // Type is the primary filter; entity_type is an OR fallback for rows with no matching type.
            ->when(self::FILTERS[$filter]['types'], fn ($q, $types) => $q->where(function ($w) use ($types, $filter) {
                $w->whereIn('type', $types);

                if ($entities = self::FILTERS[$filter]['entities']) {
                    $w->orWhereIn('entity_type', $entities);
                }
            }))
            ->latest('created_at')
            ->limit(100)
            ->get();

        // Notifications are grouped into time buckets for display.
        $groups = [
            'Today' => $notifications->filter(fn ($n) => $n->created_at?->isToday()),
            'Yesterday' => $notifications->filter(fn ($n) => $n->created_at?->isYesterday()),
            'This week' => $notifications->filter(fn ($n) => $n->created_at
                && ! $n->created_at->isToday()
                && ! $n->created_at->isYesterday()
                && $n->created_at->greaterThanOrEqualTo(now()->subWeek())),
            'Earlier' => $notifications->filter(fn ($n) => ! $n->created_at || $n->created_at->lessThan(now()->subWeek())),
        ];

        $buckets = [];

        foreach ($groups as $label => $rows) {
            if ($rows->isNotEmpty()) {
                $buckets[] = ['label' => $label, 'items' => $rows->map(fn (Notification $n) => $this->card($n))->values()];
            }
        }

        return Inertia::render('Notifications/Index', [
            'buckets' => $buckets,
            'isEmpty' => $notifications->isEmpty(),
            'counts' => $counts,
            'filter' => $filter,
            // Only the labels reach the page; the type lists behind them stay here.
            'tabs' => collect(self::FILTERS)->map(fn ($meta) => $meta['label']),
        ]);
    }

    /** One row, with the actor and icon already resolved. */
    private function card(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'message' => $notification->message,
            'ago' => $notification->created_at?->diffForHumans(),
            'is_read' => (bool) $notification->is_read,
            'is_aggregated' => $notification->isAggregated(),
            'group_count' => $notification->group_count,
            'icon' => $notification->iconName(),
            'actor' => [
                'avatar_url' => $notification->actor?->avatar_url,
                'avatar_initials' => $notification->actor?->avatar_initials ?? 'UIU',
            ],
            'url' => route('notifications.go', $notification),
        ];
    }

    public function markAllRead(Request $request)
    {
        $request->user()->notifications()->unread()->update(['is_read' => true]);
        SocialRules::forgetCountsFor($request->user()->id);

        return back()->with('success', 'Notifications updated.');
    }

    public function read(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['is_read' => true]);
        SocialRules::forgetCountsFor($request->user()->id);

        return back();
    }

    /** Marks a notification read and redirects to the entity it refers to. */
    public function go(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['is_read' => true]);
        SocialRules::forgetCountsFor($request->user()->id);

        return match ($notification->entity_type) {
            'post' => $notification->entity_id
                ? redirect()->route('posts.show', $notification->entity_id)
                : redirect()->route('feed.index'),
            'comment' => $this->redirectToComment($notification),
            'event' => $notification->entity_id
                ? redirect()->route('events.show', $notification->entity_id)
                : redirect()->route('events.index'),
            'notice' => $notification->entity_id
                ? redirect()->route('notices.show', $notification->entity_id)
                : redirect()->route('notices.index'),
            'club' => ($club = $notification->entity_id ? Club::find($notification->entity_id) : null)
                ? redirect()->route('clubs.show', $club)
                : redirect()->route('clubs.index'),
            'message' => ($actor = $notification->actor_id ? User::find($notification->actor_id) : null)
                ? redirect()->route('messages.index', ['with' => $actor->public_id])
                : redirect()->route('messages.index'),
            // An invitation is answered on the Applications page, which
            // opens scrolled to it.
            'group_invite' => redirect()->to(route('connect-requests.index').'#invite-'.$notification->entity_id),
            'group' => ($group = $notification->entity_id ? \App\Models\CommunityGroup::find($notification->entity_id) : null)
                ? redirect()->route('groups.show', $group)
                : redirect()->route('groups.index'),
            'user', 'friendship', 'connection' => ($actor = $notification->actor_id ? User::find($notification->actor_id) : null)
                ? redirect()->route('profile.show', $actor)
                : redirect()->route('network.index'),
            default => redirect()->route('notifications.index'),
        };
    }

    /** Resolves a comment notification to its parent post and the comment's anchor there. */
    protected function redirectToComment(Notification $notification)
    {
        $comment = $notification->entity_id
            ? Comment::select('id', 'post_id')->find($notification->entity_id)
            : null;

        if (! $comment) {
            return redirect()->route('feed.index');
        }

        // to() is used because route() cannot append a fragment.
        return redirect()->to(route('posts.show', $comment->post_id).'#comment-'.$comment->id);
    }
}
