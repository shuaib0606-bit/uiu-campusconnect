<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\User;
use App\Services\SocialRules;
use App\Support\Gallery;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $type = trim((string) $request->get('type', ''));

        $now = now();

        $events = Event::where('status', 'published')
            ->when($type !== '', fn ($q) => $q->where('category', $type))
            ->with(['organizer', 'club', 'media'])
            ->withCount(['registrants', 'interested'])
            // What has not happened yet comes first, soonest at the top, and
            // the archive follows most recent first. A plain date sort was
            // fine while every event was in the future; with real events
            // imported from the university's own archive it opened the page
            // on something that finished six weeks ago.
            ->orderByRaw('CASE WHEN start_datetime >= ? THEN 0 ELSE 1 END', [$now])
            ->orderByRaw('CASE WHEN start_datetime >= ? THEN start_datetime END ASC', [$now])
            ->orderByDesc('start_datetime')
            ->get();

        $categories = Event::where('status', 'published')->whereNotNull('category')->where('category', '!=', '')
            ->distinct()->orderBy('category')->pluck('category');

        $clubs = Club::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        $canCreateEvent = $user && (
            $user->canPostUniversityEvent()
            || $user->clubs()->wherePivot('can_post_events', true)->exists()
            || $user->moderatedClubs()->exists()
        );

        // Two lookups for the page instead of two per card.
        $registered = $user ? $user->registeredEvents()->pluck('events.id')->flip() : collect();
        $interested = $user ? $user->interestedEvents()->pluck('events.id')->flip() : collect();

        return Inertia::render('Events/Index', [
            'events' => $events->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'category' => $event->category,
                'club_name' => $event->club?->name,
                'host' => $event->club->name ?? $event->organizer->full_name,
                'description' => Str::limit(strip_tags($event->description), 170),
                'cover_url' => $event->coverUrl(),
                'month' => $event->start_datetime->format('M'),
                'day' => $event->start_datetime->format('d'),
                'date_label' => $event->start_datetime->format('D, M j').' · '.$event->start_datetime->format('g:i A'),
                'location' => $event->location,
                'registrants_count' => $event->registrants_count,
                'interested_count' => $event->interested_count,
                'capacity' => $event->capacity,
                'fill_percent' => min(100, (int) round(($event->registrants_count / max(1, $event->capacity)) * 100)),
                'is_full' => $event->registrants_count >= $event->capacity,
                'is_registered' => isset($registered[$event->id]),
                'is_interested' => isset($interested[$event->id]),
                'url' => route('events.show', $event),
                'actions' => [
                    'register' => route('events.register', $event),
                    'unregister' => route('events.unregister', $event),
                    'interest' => route('events.interest', $event),
                ],
            ])->values(),
            'filters' => ['type' => $type],
            'categories' => $categories->values(),
            'clubs' => $clubs->map(fn (Club $club) => ['id' => $club->id, 'name' => $club->name])->values(),
            'canCreateEvent' => (bool) $canCreateEvent,
        ]);
    }

    public function show(Request $request, Event $event)
    {
        $viewer = $request->user();

        // A draft or cancelled event is not public. Its organiser and
        // administrators can still open it; everyone else gets a 404,
        // so an event cannot be found early by counting through ids.
        abort_unless(
            $event->status === 'published'
                || ($viewer && ((int) $event->organizer_user_id === (int) $viewer->id || $viewer->isAdmin())),
            404
        );

        $event->load(['organizer', 'club', 'media']);
        $event->loadCount(['registrants', 'interested']);
        $attendees = $event->registrants()->latest('event_registrations.created_at')->limit(30)->get();
        $checkinsCount = $event->checkins()->count();

        // Whoever posted the event, or an administrator. Asked of Gallery
        // so every page in the site answers this the same way.
        $canEditMedia = Gallery::canEdit($viewer, $event);

        return view('events.show', compact('event', 'attendees', 'checkinsCount', 'canEditMedia'));
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
            'posters' => ['nullable', 'array', 'max:'.Gallery::LIMIT],
            'posters.*' => ['image', 'max:10240'],
        ]);

        $club = isset($data['club_id']) ? Club::find($data['club_id']) : null;
        $allowed = $club ? SocialRules::canPostClubEvent($user, $club) : $user->canPostUniversityEvent();

        if (! $allowed) {
            return back()->with('error', 'You are not authorised to publish this event.');
        }

        unset($data['poster'], $data['posters']);

        $event = Event::create([
            ...$data,
            'organizer_user_id' => $user->id,
            'capacity' => $data['capacity'] ?? 100,
        ]);

        foreach (Gallery::filesFrom($request, 'poster', 'posters') as $file) {
            Gallery::add($event, $file, $user->id);
        }

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
        $user = $request->user();
        $already = $event->registrants()->where('users.id', $user->id)->exists();

        // The seat count is the point of having a capacity.
        if (! $already && $event->registrants()->count() >= $event->capacity) {
            return back()->with('error', 'This event is full.');
        }

        $event->registrants()->syncWithoutDetaching([$user->id]);

        return $this->respond($request, ['active' => true, 'count' => $event->registrants()->count()], 'You are registered.');
    }

    public function unregister(Request $request, Event $event)
    {
        $event->registrants()->detach($request->user()->id);

        return $this->respond($request, ['active' => false, 'count' => $event->registrants()->count()], 'Registration cancelled.');
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

        return $this->respond($request, ['active' => ! $interested], $interested ? 'Removed from your interested list.' : 'Added to your interested list.');
    }

    public function checkin(Request $request, Event $event)
    {
        EventCheckin::firstOrCreate(['event_id' => $event->id, 'user_id' => $request->user()->id]);

        return $this->respond($request, ['active' => true], 'Checked in.');
    }
}
