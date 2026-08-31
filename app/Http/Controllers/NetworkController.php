<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserMute;
use App\Services\SocialRules;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NetworkController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $search = trim((string) $request->get('q', ''));
        $role = $request->get('role', '');

        $people = User::query()
            ->where('id', '!=', $user->id)
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

        $roleCounts = User::where('status', 'active')->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        $specialRoles = \Illuminate\Support\Facades\DB::table('club_members')
            ->join('clubs', 'clubs.id', '=', 'club_members.club_id')
            ->whereNotIn('club_members.club_role', ['member', 'general member', 'participant'])
            ->orderByRaw("CASE LOWER(club_members.club_role) WHEN 'president' THEN 1 WHEN 'vice president' THEN 2 WHEN 'secretary' THEN 3 WHEN 'advisor' THEN 4 WHEN 'moderator' THEN 5 WHEN 'coordinator' THEN 6 ELSE 9 END")
            ->orderBy('clubs.name')
            ->pluck('club_members.club_role', 'club_members.user_id')
            ->map(fn ($role) => ucwords($role));

        $pendingIncoming = $user->receivedFriendRequests()->where('status', 'pending')->with('requester')->get();
        $connections = Friendship::where('status', 'accepted')
            ->where(fn ($q) => $q->where('requester_id', $user->id)->orWhere('addressee_id', $user->id))
            ->with(['requester', 'addressee'])
            ->get();

        return view('network.index', compact('people', 'pendingIncoming', 'connections', 'search', 'role', 'roleCounts', 'specialRoles'));
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
