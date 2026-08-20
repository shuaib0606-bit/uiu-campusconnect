<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Services\SocialRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $events = Event::where('status', 'published')
            ->with(['organizer', 'club'])
            ->withCount('registrants')
            ->orderBy('start_datetime')
            ->paginate(12);

        return view('events.index', compact('events'));
    }

    public function show(Event $event)
    {
        $event->load(['organizer', 'club', 'registrants', 'interested']);

        return view('events.show', compact('event'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'description' => ['required', 'string'],
            'category' => ['required', 'string', 'max:80'],
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['required', 'date', 'after:start_datetime'],
            'location' => ['required', 'string', 'max:190'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'club_id' => ['nullable', 'exists:clubs,id'],
            'poster' => ['nullable', 'image', 'max:10240'],
        ]);

        $club = isset($data['club_id']) ? Club::find($data['club_id']) : null;
        $allowed = $club ? SocialRules::canPostClubEvent($user, $club) : $user->canPostUniversityEvent();

        if (! $allowed) {
            return back()->with('error', 'You are not authorised to publish this event.');
        }

        if ($request->hasFile('poster')) {
            $data['poster_path'] = $request->file('poster')->store('events', 'public');
        }

        Event::create([
            ...$data,
            'organizer_user_id' => $user->id,
            'capacity' => $data['capacity'] ?? 100,
        ]);

        return back()->with('success', 'Event published.');
    }

    public function register(Request $request, Event $event)
    {
        $event->registrants()->syncWithoutDetaching([$request->user()->id]);

        return $this->respond($request, ['active' => true, 'count' => $event->registrants()->count()], 'You are registered.');
    }

    public function interest(Request $request, Event $event)
    {
        $user = $request->user();
        $interested = $event->interested()->where('users.id', $user->id)->exists();

        if ($interested) {
            $event->interested()->detach($user->id);
        } else {
            $event->interested()->attach($user->id);
        }

        return $this->respond($request, ['active' => ! $interested]);
    }

    public function checkin(Request $request, Event $event)
    {
        EventCheckin::firstOrCreate(['event_id' => $event->id, 'user_id' => $request->user()->id]);

        return $this->respond($request, ['active' => true], 'Checked in.');
    }
}
