<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\User;
use App\Services\SocialRules;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $type = trim((string) $request->get('type', ''));

        $events = Event::where('status', 'published')
            ->when($type !== '', fn ($q) => $q->where('category', $type))
            ->with(['organizer', 'club'])
            ->withCount(['registrants', 'interested'])
            ->orderBy('start_datetime')
            ->get();

        $categories = Event::where('status', 'published')->whereNotNull('category')->where('category', '!=', '')
            ->distinct()->orderBy('category')->pluck('category');

        $clubs = Club::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        $canCreateEvent = $user && (
            $user->canPostUniversityEvent()
            || $user->clubs()->wherePivot('can_post_events', true)->exists()
            || $user->moderatedClubs()->exists()
        );

        return view('events.index', compact('events', 'type', 'categories', 'clubs', 'canCreateEvent'));
    }

    public function show(Event $event)
    {
        $event->load(['organizer', 'club']);
        $event->loadCount(['registrants', 'interested']);
        $attendees = $event->registrants()->latest('event_registrations.created_at')->limit(30)->get();
        $checkinsCount = $event->checkins()->count();

        return view('events.show', compact('event', 'attendees', 'checkinsCount'));
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
            'registration_url' => ['nullable', 'url', 'max:500'],
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

        $event = Event::create([
            ...$data,
            'organizer_user_id' => $user->id,
            'capacity' => $data['capacity'] ?? 100,
        ]);

        $this->notifyAudience($event, $club);

        return back()->with('success', 'Event published.');
    }

    /** Announces a published event: club events reach that club's members, university events every active account. */
    protected function notifyAudience(Event $event, ?Club $club): void
    {
        // The status column default is applied by the database, so a freshly created model has it unset.
        if (in_array($event->status, ['draft', 'cancelled', 'archived'], true)) {
            return;
        }

        $when = $event->start_datetime ? $event->start_datetime->format('M j, g:i A') : null;
        $message = ($club ? $club->name.' event: ' : 'New campus event: ').$event->title.($when ? ' - '.$when : '');

        if ($club) {
            // Club membership is a bounded list, so ids come straight from the pivot.
            $memberIds = DB::table('club_members')->where('club_id', $club->id)->pluck('user_id');

            if ($memberIds->isEmpty()) {
                return;
            }

            $audience = User::query()->where('status', 'active')->whereIn('users.id', $memberIds);
        } else {
            $audience = User::query()->where('status', 'active');
        }

        Notifier::broadcast(
            $audience,
            $event->organizer ?: request()->user(),
            Notifier::EVENT,
            'event',
            $event->id,
            $message,
        );
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
