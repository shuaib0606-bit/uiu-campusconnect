<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Http\Request;

class ClubController extends Controller
{
    public function index()
    {
        $clubs = Club::where('status', 'active')->withCount('members')->orderBy('name')->paginate(12);

        return view('clubs.index', compact('clubs'));
    }

    public function show(Club $club)
    {
        $club->load(['members', 'events' => fn ($q) => $q->latest('start_datetime')->limit(10)]);

        return view('clubs.show', compact('club'));
    }

    public function join(Request $request, Club $club)
    {
        if (! $club->members()->where('users.id', $request->user()->id)->exists()) {
            $club->members()->attach($request->user()->id, ['club_role' => 'member', 'can_post_events' => false]);
        }

        return $this->respond($request, ['active' => true, 'count' => $club->members()->count()], 'Joined '.$club->name.'.');
    }

    public function leave(Request $request, Club $club)
    {
        $club->members()->detach($request->user()->id);

        return $this->respond($request, ['active' => false, 'count' => $club->members()->count()]);
    }
}
