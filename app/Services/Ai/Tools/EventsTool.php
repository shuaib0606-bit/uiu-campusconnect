<?php

namespace App\Services\Ai\Tools;

use App\Models\Event;
use App\Services\Ai\Links;
use App\Services\Ai\Scopes;

/**
 * Published events. Drafts and cancelled events never appear - the event
 * page itself does not check that, but the events list does, and the
 * assistant follows the list.
 */
class EventsTool extends Tool
{
    public function name(): string
    {
        return 'events';
    }

    public function description(): string
    {
        return 'Campus events: when and where, host, seats left, who is going, and whether the asker is registered.';
    }

    public function parameters(): array
    {
        return self::object([
            'query' => self::text('Optional words from the title, host club or category.'),
            'when' => self::choice(['upcoming', 'past'], 'Default upcoming.'),
        ]);
    }

    public function run(array $arguments): array
    {
        $query = $this->arg($arguments, 'query');
        $past = $this->arg($arguments, 'when') === 'past';
        $like = '%'.addcslashes($query, '%_\\').'%';

        $events = Event::where('status', 'published')
            ->when($past, fn ($q) => $q->where('start_datetime', '<', now())->orderByDesc('start_datetime'),
                fn ($q) => $q->where('start_datetime', '>=', now())->orderBy('start_datetime'))
            ->when($query !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', $like)
                ->orWhere('category', 'like', $like)
                ->orWhereHas('club', fn ($c) => $c->where('name', 'like', $like))))
            ->with(['club:id,name', 'organizer:id,full_name'])
            ->withCount(['registrants', 'interested'])
            ->limit(self::LIMIT)
            ->get();

        if ($events->isEmpty()) {
            return ['events' => [], 'note' => 'No '.($past ? 'past' : 'upcoming').' events'.($query !== '' ? ' matching "'.$query.'"' : '').'.'];
        }

        $registered = $this->viewer->registeredEvents()->pluck('events.id')->flip();
        $interested = $this->viewer->interestedEvents()->pluck('events.id')->flip();
        $single = $events->count() === 1;

        return ['events' => $events->map(function (Event $e) use ($registered, $interested, $single) {
            $row = array_filter([
                'title' => $e->title,
                'link' => Links::event($e),
                'category' => $e->category,
                'host' => $e->club?->name ?? $e->organizer?->full_name,
                'starts' => $e->start_datetime?->format('D, M j, g:i A'),
                'ends' => $e->end_datetime?->format('D, M j, g:i A'),
                'where' => $e->location,
                'registered' => $e->registrants_count,
                'seats_left' => $e->capacity ? max(0, $e->capacity - $e->registrants_count) : null,
                'interested' => $e->interested_count,
                'you' => isset($registered[$e->id]) ? 'registered' : (isset($interested[$e->id]) ? 'interested' : null),
                'about' => $this->excerpt($e->description, $single ? 400 : 160),
                'registration_link' => $e->registration_url,
            ], fn ($v) => $v !== null && $v !== '');

            // The event page lists who has registered to every signed-in
            // user, so a question about one event may name them - minus
            // anyone blocked either way.
            if ($single) {
                $row['going'] = Scopes::withoutBlocks($e->registrants()->where('users.status', 'active'), $this->viewer)
                    ->latest('event_registrations.created_at')
                    ->limit(15)->pluck('full_name')->all();
            }

            return $row;
        })->values()->all()];
    }
}
