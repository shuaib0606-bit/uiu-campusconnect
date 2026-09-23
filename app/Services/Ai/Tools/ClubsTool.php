<?php

namespace App\Services\Ai\Tools;

use App\Models\Club;
use App\Models\Event;
use App\Services\Ai\Links;
use App\Services\Ai\Scopes;
use Illuminate\Support\Str;

/**
 * Clubs, as their public pages show them.
 *
 * Every signed-in student can see a club's description, meeting details,
 * moderator, executives and member count, so the assistant can too. What
 * it leaves out is what the page keeps to officers: pending membership
 * requests, role nominations and panel changes in progress.
 */
class ClubsTool extends Tool
{
    public function name(): string
    {
        return 'clubs';
    }

    public function description(): string
    {
        return 'UIU clubs: list them, or look one up for its description, meetings, moderator, executive panel, members and events.';
    }

    public function parameters(): array
    {
        return self::object([
            'query' => self::text('Optional club name or category. Leave empty to list all clubs.'),
        ]);
    }

    public function run(array $arguments): array
    {
        $query = $this->arg($arguments, 'query');

        $clubs = Club::where('status', 'active')
            ->when($query !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.addcslashes($query, '%_\\').'%')
                ->orWhere('category', 'like', '%'.addcslashes($query, '%_\\').'%')))
            ->withCount('members')
            ->orderBy('name')
            ->limit(30)
            ->get();

        if ($clubs->isEmpty()) {
            return ['clubs' => [], 'note' => 'No active club matching "'.$query.'".'];
        }

        // A list when browsing; the full page when the question is about
        // one or two clubs.
        if ($query === '' || $clubs->count() > 2) {
            return ['clubs' => $clubs->map(fn (Club $c) => [
                'name' => $c->name,
                'category' => $c->category,
                'members' => $c->members_count,
                'link' => Links::club($c),
            ])->values()->all()];
        }

        return ['clubs' => $clubs->map(fn (Club $c) => $this->detail($c))->values()->all()];
    }

    private function detail(Club $club): array
    {
        // Executives are members with a real designation. Ordinary members
        // are counted, not listed - the question is almost always "who
        // runs it", and a list of forty names helps nobody.
        $executives = Scopes::withoutBlocks($club->members()->where('users.status', 'active'), $this->viewer)
            ->get()
            ->filter(fn ($m) => ! in_array(Str::lower(trim((string) $m->pivot->club_role)), ['', 'member', 'general member'], true))
            ->map(fn ($m) => Links::person($m, ['role' => $m->pivot->club_role]))
            ->values()
            ->all();

        $mine = $this->viewer->clubs()->where('clubs.id', $club->id)->first();

        return array_filter([
            'name' => $club->name,
            'link' => Links::club($club),
            'category' => $club->category,
            'about' => $this->excerpt($club->description, 400),
            'meets' => trim(($club->meeting_time ?? '').($club->meeting_place ? ', '.$club->meeting_place : '')) ?: null,
            'moderator' => Links::person($club->moderator),
            'executives' => $executives ?: null,
            'members' => $club->members_count,
            'you' => $mine ? 'member ('.($mine->pivot->club_role ?: 'member').')' : 'not a member',
            'upcoming_events' => Event::where('club_id', $club->id)
                ->where('status', 'published')
                ->where('start_datetime', '>=', now())
                ->orderBy('start_datetime')->limit(3)->get()
                ->map(fn ($e) => ['title' => $e->title, 'starts' => $e->start_datetime?->format('D, M j, g:i A'), 'link' => Links::event($e)])
                ->values()->all() ?: null,
        ]);
    }
}
