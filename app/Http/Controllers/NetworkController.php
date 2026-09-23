<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserMute;
use App\Services\SocialRules;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class NetworkController extends Controller
{
    private const ROLE_LABELS = [
        'student' => 'Student',
        'faculty' => 'Faculty',
        'official' => 'Official',
        'admin' => 'Admin',
    ];

    public function index(Request $request)
    {
        $me = $request->user();
        $search = trim((string) $request->get('q', ''));
        $role = (string) $request->get('role', '');

        $people = User::query()
            ->where('id', '!=', $me->id)
            ->where('status', 'active')
            ->with('profile')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%")
                        ->orWhere('designation', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('profile', fn ($p) => $p->where('skills', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($role, ['student', 'faculty', 'official', 'admin'], true), fn ($q) => $q->where('role', $role))
            ->orderByRaw("CASE role WHEN 'faculty' THEN 1 WHEN 'official' THEN 2 WHEN 'student' THEN 3 ELSE 4 END")
            ->orderBy('full_name')
            ->paginate(24)
            ->withQueryString();

        $specialRoles = DB::table('club_members')
            ->join('clubs', 'clubs.id', '=', 'club_members.club_id')
            ->whereNotIn('club_members.club_role', ['member', 'general member', 'participant'])
            ->orderByRaw("CASE LOWER(club_members.club_role) WHEN 'president' THEN 1 WHEN 'vice president' THEN 2 WHEN 'secretary' THEN 3 WHEN 'advisor' THEN 4 WHEN 'moderator' THEN 5 WHEN 'coordinator' THEN 6 ELSE 9 END")
            ->orderBy('clubs.name')
            ->pluck('club_members.club_role', 'club_members.user_id')
            ->map(fn ($role) => ucwords($role));

        // Loaded once for the whole page. Asking per person cost two queries a card.
        $friendships = Friendship::query()
            ->where(fn ($q) => $q->where('requester_id', $me->id)->orWhere('addressee_id', $me->id))
            ->get()
            ->keyBy(fn (Friendship $f) => $f->requester_id === $me->id ? $f->addressee_id : $f->requester_id);

        $blocked = UserBlock::query()
            ->where('blocker_id', $me->id)
            ->orWhere('blocked_id', $me->id)
            ->get()
            ->flatMap(fn (UserBlock $b) => [$b->blocker_id, $b->blocked_id])
            ->unique()
            ->flip();

        return Inertia::render('Network/Index', [
            'people' => $people->through(
                fn (User $person) => $this->card($me, $person, $friendships, $blocked, $specialRoles)
            ),
            'filters' => ['q' => $search, 'role' => $role],
        ]);
    }

    /** One directory card, with everything the page needs already decided. */
    private function card(User $me, User $person, Collection $friendships, Collection $blocked, Collection $specialRoles): array
    {
        $friendship = $friendships[$person->id] ?? null;
        $pending = $friendship !== null && $friendship->status === 'pending';

        $connected = $friendship !== null && $friendship->status === 'accepted';
        $sent = $pending && $friendship->requester_id === $me->id;
        $incoming = $pending && $friendship->addressee_id === $me->id;

        [$canRequest] = SocialRules::canRequestConnection($me, $person);

        // A student reaching a member of staff goes through the application desk.
        $needsPermission = $me->role === 'student' && in_array($person->role, ['faculty', 'official'], true);

        $canMessage = SocialRules::canMessageWith(
            $me,
            $person,
            fn () => isset($blocked[$person->id]),
            fn () => $connected,
        );

        $roleLabel = self::ROLE_LABELS[$person->role] ?? ucfirst($person->role ?: 'Member');

        return [
            'id' => $person->id,
            'full_name' => $person->full_name,
            'role_label' => $roleLabel,
            'title' => $person->designation ?: $roleLabel,
            'department' => $person->department ?: 'United International University',
            'avatar_url' => $person->avatar_url,
            'avatar_initials' => $person->avatar_initials,
            'special_role' => $specialRoles[$person->id] ?? null,
            'bio' => Str::limit((string) ($person->profile->bio ?? ''), 110),
            'skills' => array_slice(
                array_filter(array_map('trim', explode(',', (string) ($person->profile->skills ?? '')))),
                0,
                3
            ),
            'profile_url' => route('profile.show', $person),
            'message_url' => $canMessage ? route('messages.show', $person) : null,
            'relationship' => match (true) {
                $connected => 'connected',
                $sent => 'sent',
                $incoming => 'incoming',
                $needsPermission => 'permission',
                $canRequest => 'connect',
                default => 'none',
            },
            'actions' => [
                'connect' => route('network.request', $person),
                'accept' => route('network.accept', $person),
                'decline' => route('network.decline', $person),
                'remove' => route('network.remove', $person),
                'permission' => route('connect-requests.index', ['target' => $person->public_id]),
            ],
        ];
    }

    public function request(Request $request, User $user)
    {
        $me = $request->user();
        [$allowed, $message] = SocialRules::canRequestConnection($me, $user);

        if (! $allowed) {
            return back()->with('error', $message);
        }

        $status = in_array($message, [
            'Faculty-to-faculty connections are accepted directly.',
            'Officials can connect with one another for office work.',
        ], true) ? 'accepted' : 'pending';

        $friendship = Friendship::firstOrCreate([
            'requester_id' => $me->id,
            'addressee_id' => $user->id,
        ], ['status' => $status]);

        // Notify only on the first send.
        if ($friendship->wasRecentlyCreated) {
            $status === 'accepted'
                ? Notifier::send($user->id, $me, Notifier::FRIEND_ACCEPT, 'user', $me->id, 'is now connected with you.')
                : Notifier::send($user->id, $me, Notifier::FRIEND_REQUEST, 'user', $me->id, 'sent you a connection request.');
        }

        return $this->respond($request, ['status' => $status], $status === 'accepted' ? 'Connected.' : 'Request sent.');
    }

    public function accept(Request $request, User $user)
    {
        $accepted = Friendship::where('requester_id', $user->id)
            ->where('addressee_id', $request->user()->id)
            ->update(['status' => 'accepted']);

        // Notify only when the update matched a row.
        if ($accepted) {
            Notifier::send($user->id, $request->user(), Notifier::FRIEND_ACCEPT, 'user', $request->user()->id, 'accepted your connection request.');
        }

        return $this->respond($request, ['status' => 'accepted'], 'Connection accepted.');
    }

    public function decline(Request $request, User $user)
    {
        Friendship::where('requester_id', $user->id)
            ->where('addressee_id', $request->user()->id)
            ->delete();

        return $this->respond($request, ['status' => 'none'], 'Request declined.');
    }

    public function remove(Request $request, User $user)
    {
        $me = $request->user()->id;
        Friendship::where(fn ($q) => $q->where('requester_id', $me)->where('addressee_id', $user->id))
            ->orWhere(fn ($q) => $q->where('requester_id', $user->id)->where('addressee_id', $me))
            ->delete();

        return $this->respond($request, ['status' => 'none'], 'Connection removed.');
    }

    public function follow(Request $request, User $user)
    {
        $me = $request->user();
        $following = $me->following()->where('followed_id', $user->id)->exists();

        if ($following) {
            $me->following()->detach($user->id);
        } else {
            $me->following()->attach($user->id);

            // Notify on follow only, never on unfollow.
            Notifier::send($user->id, $me, Notifier::FOLLOW, 'user', $me->id, 'started following you.');
        }

        return $this->respond(
            $request,
            ['active' => ! $following],
            $following ? "Unfollowed {$user->full_name}." : "Following {$user->full_name}."
        );
    }

    public function block(Request $request, User $user)
    {
        UserBlock::firstOrCreate([
            'blocker_id' => $request->user()->id,
            'blocked_id' => $user->id,
        ]);

        return $this->respond($request, ['active' => true], $user->full_name.' has been blocked.');
    }

    /** Lifts a block. Only the person who set it can undo it. */
    public function unblock(Request $request, User $user)
    {
        UserBlock::where('blocker_id', $request->user()->id)
            ->where('blocked_id', $user->id)
            ->delete();

        return $this->respond($request, ['active' => false], $user->full_name.' has been unblocked.');
    }

    /** Time-boxed mute. Muting again while a mute is live lifts it. */
    public function mute(Request $request, User $user)
    {
        $days = (int) $request->input('days', 30);
        $days = max(1, min($days, 365));

        $me = $request->user();
        $query = UserMute::where('muter_id', $me->id)->where('muted_id', $user->id);
        $existing = $query->first();

        if ($existing && ($existing->expires_at === null || $existing->expires_at->isFuture())) {
            $query->delete();

            return $this->respond($request, ['active' => false], $user->full_name.' has been unmuted.');
        }

        if ($existing) {
            $query->update(['expires_at' => now()->addDays($days)]);
        } else {
            UserMute::create([
                'muter_id' => $me->id,
                'muted_id' => $user->id,
                'expires_at' => now()->addDays($days),
            ]);
        }

        return $this->respond($request, ['active' => true], "Muted {$user->full_name} for {$days} days.");
    }
}
