<?php

namespace App\Services\Ai\Tools;

use App\Models\Assignment;
use App\Models\CommunityGroup;
use App\Models\ConnectionApplication;
use App\Models\Friendship;
use App\Models\LostFoundClaim;
use App\Models\LostFoundItem;
use App\Models\MarketplaceItem;
use App\Models\Message;
use App\Models\Notice;
use App\Models\Post;
use App\Services\Ai\Links;
use App\Services\Ai\PublicProfile;
use App\Services\Ai\Scopes;
use Illuminate\Support\Facades\DB;

/**
 * Everything about the person asking, and only them.
 *
 * Every query in this class is anchored on $this->viewer->id. There is no
 * argument that names another person, so the only way to reach someone
 * else's data from here is through what the viewer already shares with
 * them - a friend's name on their own friend list, the other side of
 * their own conversation.
 */
class MeTool extends Tool
{
    private const ASPECTS = [
        'profile', 'courses', 'assignments', 'groups', 'clubs', 'events',
        'connections', 'messages', 'notifications', 'posts', 'saved',
        'listings', 'applications',
    ];

    public function name(): string
    {
        return 'me';
    }

    public function description(): string
    {
        return "Details about the person asking - their own data only. Use when the summary in the instructions is not enough.";
    }

    public function parameters(): array
    {
        return self::object([
            'aspect' => self::choice(self::ASPECTS, 'Which part of their own data.'),
        ], ['aspect']);
    }

    public function run(array $arguments): array
    {
        return match ($this->arg($arguments, 'aspect')) {
            'profile' => $this->profile(),
            'courses' => $this->courses(),
            'assignments' => $this->assignments(),
            'groups' => $this->groups(),
            'clubs' => $this->clubs(),
            'events' => $this->events(),
            'connections' => $this->connections(),
            'messages' => $this->messages(),
            'notifications' => $this->notifications(),
            'posts' => $this->posts(),
            'saved' => $this->saved(),
            'listings' => $this->listings(),
            'applications' => $this->applications(),
            default => ['error' => 'aspect must be one of: '.implode(', ', self::ASPECTS)],
        };
    }

    private function profile(): array
    {
        return PublicProfile::present($this->viewer, $this->viewer, true) + array_filter([
            'year' => $this->viewer->year,
            'account_type' => $this->viewer->roleLabel(),
        ]);
    }

    /**
     * Classes this trimester, each with the two facts students most often
     * ask about a class that are not on its timetable: whether the teacher
     * has opened the chat, and what is waiting to be read or handed in.
     */
    private function courses(): array
    {
        $groups = Scopes::courseGroups($this->viewer)->get();

        if ($groups->isEmpty()) {
            return ['courses' => [], 'note' => 'No class groups this trimester.'];
        }

        return ['count' => $groups->count(), 'courses' => $groups->map(function (CommunityGroup $group) {
            $section = $group->courseSection();
            $staff = $group->isStaff($this->viewer);

            return array_filter([
                'course' => trim(($section?->course_code ?? '').' '.($section?->course_title ?? '')),
                'link' => Links::group($group),
                'section' => $section?->section_name,
                'teacher' => Links::person($section?->teacher()),
                'schedule' => $section?->scheduleLine(),
                'room' => $section?->room,
                'chat' => $group->posting_mode === CommunityGroup::POSTING_EVERYONE
                    ? 'open to students'
                    : 'closed - only staff can post',
                'your_role' => $staff ? 'staff' : 'student',
                'unread_notices' => $this->unreadNotices($group->id),
                'assignments_to_hand_in' => $staff ? null : $this->pendingAssignments($group->id),
            ], fn ($v) => $v !== null && $v !== '');
        })->values()->all()];
    }

    private function assignments(): array
    {
        $groupIds = Scopes::courseGroups($this->viewer)->pluck('community_groups.id');

        $assignments = Assignment::whereIn('group_id', $groupIds)
            ->with(['group.section', 'teacher:id,full_name', 'attachments'])
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->limit(20)
            ->get();

        $rows = $assignments->map(function (Assignment $a) {
            $mine = $a->submissionFor($this->viewer);

            return array_filter([
                'title' => $a->title,
                'course' => $a->group?->courseSection()?->course_code ?? $a->group?->name,
                'set_by' => $a->teacher?->full_name,
                'link' => Links::group($a->group),
                'due' => $a->dueLabel() ?? 'no deadline',
                // "3 days overdue" beside "handed in" reads like a
                // contradiction, so the countdown is only for work still owed.
                'time_left' => $mine ? null : $a->dueRelative(),
                'status' => $a->status === Assignment::OPEN ? 'open' : 'closed',
                'you' => $mine
                    ? ($mine->is_late ? 'handed in late' : 'handed in').' '.$mine->submitted_at?->diffForHumans()
                    : ($a->isOverdue() ? 'not handed in - overdue' : 'not handed in yet'),
                'handouts' => $a->attachments->pluck('original_name')->all() ?: null,
                'instructions' => $this->excerpt($a->instructions, 200),
            ]);
        });

        return [
            'due_and_not_handed_in' => $rows->where('you', 'not handed in yet')->count(),
            'overdue_and_not_handed_in' => $rows->where('you', 'not handed in - overdue')->count(),
            'assignments' => $rows->values()->all(),
        ];
    }

    private function groups(): array
    {
        $groups = Scopes::memberGroups($this->viewer)->orderBy('name')->limit(30)->get();

        return ['groups' => $groups->map(fn (CommunityGroup $g) => [
            'name' => $g->name,
            'link' => Links::group($g),
            'kind' => $g->kind,
            'your_role' => $g->roleOf($this->viewer),
            'archived' => $g->archived_at !== null,
        ])->values()->all()];
    }

    private function clubs(): array
    {
        return ['clubs' => $this->viewer->clubs()->get()->map(fn ($club) => array_filter([
            'name' => $club->name,
            'link' => Links::club($club),
            'category' => $club->category,
            'your_role' => $club->pivot->club_role ?: 'member',
            'meets' => trim(($club->meeting_time ?? '').' '.($club->meeting_place ?? '')) ?: null,
            'active' => $club->status === 'active' ? null : 'this club is no longer active',
        ]))->values()->all()];
    }

    private function events(): array
    {
        $now = now();
        $line = fn ($e) => array_filter([
            'title' => $e->title,
            'starts' => $e->start_datetime?->format('D, M j, g:i A'),
            'where' => $e->location,
            'link' => Links::event($e),
        ]);

        return [
            'registered_upcoming' => $this->viewer->registeredEvents()
                ->where('events.start_datetime', '>=', $now)->orderBy('events.start_datetime')
                ->limit(self::LIMIT)->get()->map($line)->values()->all(),
            'interested_upcoming' => $this->viewer->interestedEvents()
                ->where('events.start_datetime', '>=', $now)->orderBy('events.start_datetime')
                ->limit(self::LIMIT)->get()->map($line)->values()->all(),
            'registered_past' => $this->viewer->registeredEvents()
                ->where('events.start_datetime', '<', $now)->orderByDesc('events.start_datetime')
                ->limit(5)->get()->map($line)->values()->all(),
            'events_checked_in_to' => DB::table('event_checkins')->where('user_id', $this->viewer->id)->count(),
        ];
    }

    /**
     * The viewer's own friend list, requests and follow counts. Names are
     * passed through visiblePeople, so an account that has since been
     * suspended, or someone either side has blocked, drops out.
     */
    private function connections(): array
    {
        $me = $this->viewer->id;

        $friendIds = Friendship::where('status', 'accepted')
            ->where(fn ($q) => $q->where('requester_id', $me)->orWhere('addressee_id', $me))
            ->get(['requester_id', 'addressee_id'])
            ->map(fn ($f) => (int) $f->requester_id === $me ? (int) $f->addressee_id : (int) $f->requester_id);

        $incoming = Friendship::where('status', 'pending')->where('addressee_id', $me)->pluck('requester_id');
        $outgoing = Friendship::where('status', 'pending')->where('requester_id', $me)->pluck('addressee_id');

        $names = fn ($ids, $limit = 30) => Scopes::visiblePeople($this->viewer)
            ->whereIn('users.id', $ids)->orderBy('full_name')->limit($limit)->get()
            ->map(fn ($u) => Links::person($u))->all();

        return [
            'friends_count' => $friendIds->count(),
            'friends' => $names($friendIds),
            'requests_waiting_for_you' => $names($incoming),
            'requests_you_sent' => $names($outgoing),
            'followers' => DB::table('user_follows')->where('followed_id', $me)->count(),
            'following' => DB::table('user_follows')->where('follower_id', $me)->count(),
        ];
    }

    /**
     * The viewer's own inbox. Message text leaves the site only when the
     * student asks about their messages, and only as a short excerpt of
     * the latest line in each conversation.
     */
    private function messages(): array
    {
        if ($this->viewer->isAdmin()) {
            return ['note' => 'Messaging is switched off for administrator accounts.'];
        }

        $me = $this->viewer->id;

        $recent = Message::query()
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('sender_id', $me)->where('is_deleted_by_sender', false))
                ->orWhere(fn ($w) => $w->where('receiver_id', $me)->where('is_deleted_by_receiver', false)))
            ->with(['sender:id,full_name', 'receiver:id,full_name'])
            ->latest('id')
            ->limit(300)
            ->get();

        $threads = $recent->groupBy(fn ($m) => (int) $m->sender_id === $me ? $m->receiver_id : $m->sender_id)
            ->take(10)
            ->map(function ($messages) use ($me) {
                $last = $messages->first();
                $other = (int) $last->sender_id === $me ? $last->receiver : $last->sender;

                return [
                    'with' => $other?->full_name,
                    'link' => Links::messages($other),
                    'unread' => $messages->where('receiver_id', $me)->where('is_read', false)->count(),
                    'last_from' => (int) $last->sender_id === $me ? 'you' : $other?->full_name,
                    'last' => $this->excerpt($last->body, 80) ?? '(an attachment)',
                    'when' => $last->created_at?->diffForHumans(),
                ];
            });

        return [
            'unread_total' => $recent->where('receiver_id', $me)->where('is_read', false)->count(),
            'conversations' => $threads->values()->all(),
        ];
    }

    private function notifications(): array
    {
        $all = $this->viewer->notifications();

        return [
            'unread' => (clone $all)->where('is_read', false)->count(),
            'latest' => $all->limit(10)->get()->map(fn ($n) => [
                'text' => $n->message,
                'when' => $n->created_at?->diffForHumans(),
                'read' => (bool) $n->is_read,
            ])->values()->all(),
        ];
    }

    private function posts(): array
    {
        $mine = Post::visible()->where('posts.user_id', $this->viewer->id);

        return [
            'total' => (clone $mine)->count(),
            'latest' => $mine->withCount(['comments', 'reactions'])->latest()->limit(5)->get()
                ->map(fn (Post $p) => [
                    'text' => $this->excerpt($p->body, 140),
                    'posted' => $p->created_at?->diffForHumans(),
                    'audience' => $p->visibility ?: 'everyone',
                    'reactions' => $p->reactions_count,
                    'comments' => $p->comments_count,
                ])->values()->all(),
        ];
    }

    /** Saved posts, still filtered through the feed's own visibility rules. */
    private function saved(): array
    {
        $posts = Post::visible()->forViewer($this->viewer)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('post_saves')
                ->whereColumn('post_saves.post_id', 'posts.id')
                ->where('post_saves.user_id', $this->viewer->id))
            ->with('user:id,full_name')
            ->latest()
            ->limit(8)
            ->get();

        return ['saved' => $posts->map(fn (Post $p) => [
            'by' => $p->user?->full_name,
            'link' => Links::post($p),
            'text' => $this->excerpt($p->body, 140),
            'posted' => $p->created_at?->diffForHumans(),
        ])->values()->all()];
    }

    private function listings(): array
    {
        $me = $this->viewer->id;

        return [
            'marketplace' => MarketplaceItem::where('user_id', $me)->latest()->limit(10)->get()
                ->map(fn ($i) => ['title' => $i->title, 'price' => $i->price, 'status' => $i->status, 'link' => Links::marketplace($i)])
                ->values()->all(),

            // Claims on the viewer's own reports are shown to them on the
            // site, claimant and all, so they are here too.
            'lost_and_found' => LostFoundItem::where('user_id', $me)->with('claims.user:id,full_name')
                ->latest()->limit(10)->get()
                ->map(fn ($i) => array_filter([
                    'type' => $i->type,
                    'title' => $i->title,
                    'link' => Links::lostFound($i),
                    'status' => $i->status === 'resolved' ? 'reunited' : $i->status,
                    'claims' => $i->claims->map(fn ($c) => ($c->user?->full_name ?? 'someone').' ('.$c->status.')')->values()->all() ?: null,
                ]))->values()->all(),

            'your_claims_on_others_reports' => LostFoundClaim::where('user_id', $me)->with('item:id,title,status')
                ->latest()->limit(10)->get()
                ->map(fn ($c) => ['item' => $c->item?->title, 'your_claim' => $c->status])
                ->values()->all(),
        ];
    }

    /**
     * Connection applications, from whichever side the viewer is on. A
     * student sees what they applied for. A teacher sees applications that
     * have been approved to reach them, with the student's contact details
     * - which is what the connect-requests page shows them. Administrators'
     * internal notes are never included.
     */
    private function applications(): array
    {
        $me = $this->viewer->id;

        if ($this->viewer->role === 'student') {
            return ['your_applications' => ConnectionApplication::where('student_id', $me)
                ->with('target:id,full_name')->latest()->limit(10)->get()
                ->map(fn ($a) => [
                    'to' => $a->target?->full_name,
                    'purpose' => $a->purpose,
                    'status' => $a->statusLabel(),
                    'sent' => $a->created_at?->diffForHumans(),
                ])->values()->all()];
        }

        return ['students_approved_to_contact_you' => ConnectionApplication::where('target_id', $me)
            ->where('status', 'approved')
            ->with('student:id,full_name,email,department')->latest()->limit(10)->get()
            ->map(fn ($a) => array_filter([
                'student' => $a->student?->full_name,
                'email' => $a->student?->email,
                'department' => $a->student?->department,
                'purpose' => $a->purpose,
                'course_or_department' => $a->course_department,
                'message' => $this->excerpt($a->message, 160),
            ]))->values()->all()];
    }

    // ------------------------------------------------------------------

    /** Class notices in one group the viewer has not opened yet. */
    private function unreadNotices(int $groupId): int
    {
        return Notice::where('status', 'published')
            ->where('group_id', $groupId)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('notice_reads')
                ->whereColumn('notice_reads.notice_id', 'notices.id')
                ->where('notice_reads.user_id', $this->viewer->id))
            ->count();
    }

    /** Open assignments in one group the viewer still has to hand in. */
    private function pendingAssignments(int $groupId): int
    {
        return Assignment::where('group_id', $groupId)
            ->where('status', Assignment::OPEN)
            ->whereDoesntHave('submissions', fn ($q) => $q->where('user_id', $this->viewer->id))
            ->count();
    }
}
