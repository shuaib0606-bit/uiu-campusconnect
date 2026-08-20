<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubMembershipRequest;
use App\Services\SocialRules;
use Illuminate\Http\Request;

class ClubController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $clubs = Club::where('status', 'active')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('category', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->withCount(['members', 'events' => fn ($q2) => $q2->where('status', 'published'), 'membershipRequests as pending_requests_count' => fn ($q2) => $q2->where('status', 'pending')])
            ->with('moderator')
            ->orderBy('name')
            ->get();

        return view('clubs.index', compact('clubs', 'q'));
    }

    public function show(Request $request, Club $club)
    {
        $user = $request->user();

        $club->load(['moderator']);
        $members = $club->members()->with('profile')->orderByDesc('club_members.can_post_events')->orderBy('full_name')->get();
        $events = $club->events()->where('status', 'published')->withCount('registrants')->orderBy('start_datetime')->get();
        $posts = $club->posts()->visible()->with('user')->latest()->limit(8)->get();

        $canManage = $user && SocialRules::canManageClub($user, $club);
        $pendingRequests = $canManage
            ? $club->membershipRequests()->where('status', 'pending')->with('user')->latest()->get()
            : collect();

        $isMember = $user && $club->members()->where('users.id', $user->id)->exists();
        $hasPending = $user && ! $isMember && $club->membershipRequests()->where('user_id', $user->id)->where('status', 'pending')->exists();
        $canPostClubContent = $user && SocialRules::canPostClubEvent($user, $club);

        return view('clubs.show', compact(
            'club', 'members', 'events', 'posts', 'pendingRequests',
            'isMember', 'hasPending', 'canManage', 'canPostClubContent'
        ));
    }

    public function requestMembership(Request $request, Club $club)
    {
        $user = $request->user();

        if ($club->members()->where('users.id', $user->id)->exists()) {
            return back()->with('error', 'You are already a member of this club.');
        }

        if ($club->membershipRequests()->where('user_id', $user->id)->where('status', 'pending')->exists()) {
            return back()->with('error', 'Your membership request is already pending.');
        }

        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        ClubMembershipRequest::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'note' => $data['note'] ?? null,
            'status' => 'pending',
        ]);

        return $this->respond($request, ['status' => 'pending'], 'Membership request sent.');
    }

    public function reviewRequest(Request $request, Club $club, ClubMembershipRequest $membershipRequest)
    {
        $user = $request->user();
        if (! SocialRules::canManageClub($user, $club) || $membershipRequest->club_id !== $club->id) {
            abort(403);
        }

        $data = $request->validate(['decision' => ['required', 'in:approved,rejected']]);

        $membershipRequest->update(['status' => $data['decision']]);

        if ($data['decision'] === 'approved') {
            $club->members()->syncWithoutDetaching([$membershipRequest->user_id => ['club_role' => 'member', 'can_post_events' => false]]);
        }

        return $this->respond($request, ['status' => $data['decision']], 'Membership request updated.');
    }

    public function leave(Request $request, Club $club)
    {
        $club->members()->detach($request->user()->id);

        return $this->respond($request, ['active' => false, 'count' => $club->members()->count()]);
    }
}
