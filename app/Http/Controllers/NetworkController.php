<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\SocialRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NetworkController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $search = trim((string) $request->get('q', ''));

        $people = User::query()
            ->where('id', '!=', $user->id)
            ->where('status', 'active')
            ->when($search !== '', fn ($q) => $q->where('full_name', 'like', "%{$search}%"))
            ->orderBy('full_name')
            ->paginate(24)
            ->withQueryString();

        $pendingIncoming = $user->receivedFriendRequests()->where('status', 'pending')->with('requester')->get();
        $connections = Friendship::where('status', 'accepted')
            ->where(fn ($q) => $q->where('requester_id', $user->id)->orWhere('addressee_id', $user->id))
            ->with(['requester', 'addressee'])
            ->get();

        return view('network.index', compact('people', 'pendingIncoming', 'connections', 'search'));
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

        Friendship::firstOrCreate([
            'requester_id' => $me->id,
            'addressee_id' => $user->id,
        ], ['status' => $status]);

        return $this->respond($request, ['status' => $status], $status === 'accepted' ? 'Connected.' : 'Request sent.');
    }

    public function accept(Request $request, User $user)
    {
        Friendship::where('requester_id', $user->id)
            ->where('addressee_id', $request->user()->id)
            ->update(['status' => 'accepted']);

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
        }

        return $this->respond($request, ['active' => ! $following]);
    }

    public function block(Request $request, User $user)
    {
        UserBlock::firstOrCreate([
            'blocker_id' => $request->user()->id,
            'blocked_id' => $user->id,
        ]);

        return $this->respond($request, ['active' => true], $user->full_name.' has been blocked.');
    }
}
