<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubMembershipRequest;
use App\Models\ClubPanelChange;
use App\Models\ClubRoleRequest;
use App\Models\User;
use App\Services\SocialRules;
use App\Support\ClubPanel;
use App\Support\Gallery;
use App\Support\GroupSync;
use App\Support\ImageResizer;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

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
            ->withCount(['members', 'events' => fn ($q2) => $q2->where('status', 'published')])
            ->with('moderator', 'media')
            ->orderBy('name')
            ->get();

        return Inertia::render('Clubs/Index', [
            'clubs' => $clubs->map(fn (Club $club) => [
                'id' => $club->id,
                'name' => $club->name,
                'category' => $club->category,
                'description' => Str::limit($club->description, 140),
                'cover_url' => $club->coverUrl(),
                'logo_url' => $club->logo_path ? Storage::url($club->logo_path) : null,
                'members_count' => $club->members_count,
                'events_count' => $club->events_count,
                'moderator' => $club->moderator->full_name ?? 'Not assigned',
                'meeting' => trim(implode(' · ', array_filter([$club->meeting_time, $club->meeting_place]))),
                'url' => route('clubs.show', $club),
            ])->values(),
            'filters' => ['q' => $q],
        ]);
    }

    public function show(Request $request, Club $club)
    {
        $user = $request->user();

        $club->load(['moderator', 'media']);
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

        // The banner and the logo follow one rule, and it lives on the
        // model: the moderator, the student panel, or an administrator.
        $canEditMedia = Gallery::canEdit($user, $club);

        // An officer may put a name forward; only the moderator may agree.
        $canNominate = $user && SocialRules::canNominateClubOfficer($user, $club);

        // The panel handover: proposed by the outgoing president, agreed by
        // the moderator or a super admin.
        $canProposePanel = $user && SocialRules::canProposeClubPanel($user, $club);
        $canApprovePanel = $user && SocialRules::canApproveClubPanel($user, $club);
        $canChangeModerator = $user && SocialRules::canChangeClubModerator($user);

        $pendingPanel = ($canProposePanel || $canApprovePanel)
            ? $club->panelChanges()
                ->where('status', ClubPanelChange::PENDING)
                ->with(['proposer', 'panelMembers.user'])
                ->latest()
                ->first()
            : null;

        $lastPanelChange = $club->panelChanges()
            ->where('status', ClubPanelChange::APPROVED)
            ->with(['decider', 'proposer'])
            ->latest('decided_at')
            ->first();

        // Faculty and office accounts a super admin can hand the club to.
        $moderatorOptions = $canChangeModerator
            ? User::whereIn('role', ['faculty', 'official'])
                ->where('status', 'active')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'department'])
            : collect();

        $roleRequests = ($canNominate || $canManage)
            ? $club->roleRequests()
                ->where('status', ClubRoleRequest::PENDING)
                ->with(['nominee', 'nominator'])
                ->latest()
                ->get()
            : collect();

        return view('clubs.show', compact(
            'club', 'members', 'events', 'posts', 'pendingRequests',
            'isMember', 'hasPending', 'canManage', 'canPostClubContent', 'canEditMedia',
            'canNominate', 'roleRequests',
            'canProposePanel', 'canApprovePanel', 'canChangeModerator',
            'pendingPanel', 'lastPanelChange', 'moderatorOptions'
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

    /**
     * An officer puts a member forward for a designation. It does not take
     * effect here: the club's moderator has to approve it, and until they do
     * the request sits in both people's Applications list.
     */
    public function nominateOfficer(Request $request, Club $club)
    {
        $user = $request->user();

        abort_unless(SocialRules::canNominateClubOfficer($user, $club), 403);

        $data = $request->validate([
            'nominee_id' => ['required', 'integer', 'exists:users,id'],
            'club_role' => ['required', 'string', 'max:80'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $nominee = User::findOrFail($data['nominee_id']);

        if (! $club->members()->where('users.id', $nominee->id)->exists()) {
            return back()->with('error', 'That person has to be a club member first.');
        }

        if ($club->moderator_id === $nominee->id) {
            return back()->with('error', 'They already run this club.');
        }

        $open = $club->roleRequests()
            ->where('nominee_id', $nominee->id)
            ->where('status', ClubRoleRequest::PENDING)
            ->exists();

        if ($open) {
            return back()->with('warning', 'There is already a request waiting for '.$nominee->full_name.'.');
        }

        $roleRequest = ClubRoleRequest::create([
            'club_id' => $club->id,
            'nominee_id' => $nominee->id,
            'nominated_by' => $user->id,
            'club_role' => trim($data['club_role']),
            'reason' => $data['reason'] ?? null,
            'status' => ClubRoleRequest::PENDING,
        ]);

        // The moderator is the only person who can answer it, so tell them.
        if ($club->moderator_id) {
            Notifier::send(
                $club->moderator_id,
                $user,
                Notifier::SYSTEM,
                'club_role_request',
                $roleRequest->id,
                $user->full_name.' asked to make '.$nominee->full_name.' '.$roleRequest->club_role.' of '.$club->name.'.'
            );
        }

        return back()->with('success', 'Sent to the club moderator for approval.');
    }

    /** The moderator agrees or declines. Only they can. */
    public function reviewOfficer(Request $request, Club $club, ClubRoleRequest $roleRequest)
    {
        $user = $request->user();

        abort_unless(SocialRules::canManageClub($user, $club), 403);
        abort_unless((int) $roleRequest->club_id === (int) $club->id, 404);

        $data = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject'],
            'decision_note' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $roleRequest->isPending()) {
            return back()->with('error', 'That request has already been answered.');
        }

        $approved = $data['decision'] === 'approve';

        if ($approved) {
            $club->members()->updateExistingPivot($roleRequest->nominee_id, [
                'club_role' => $roleRequest->club_role,
                'can_post_events' => true,
            ]);

            // The club group mirrors club_members, so it needs telling.
            GroupSync::club($club->fresh());
        }

        $roleRequest->update([
            'status' => $approved ? ClubRoleRequest::APPROVED : ClubRoleRequest::REJECTED,
            'decision_note' => $data['decision_note'] ?? null,
            'decided_by' => $user->id,
            'decided_at' => now(),
        ]);

        foreach (array_unique([$roleRequest->nominated_by, $roleRequest->nominee_id]) as $recipient) {
            Notifier::send(
                $recipient,
                $user,
                Notifier::SYSTEM,
                'club_role_request',
                $roleRequest->id,
                $approved
                    ? $roleRequest->nominee->full_name.' is now '.$roleRequest->club_role.' of '.$club->name.'.'
                    : 'The request to make '.$roleRequest->nominee->full_name.' '.$roleRequest->club_role.' of '.$club->name.' was declined.'
            );
        }

        return back()->with('success', $approved ? 'Designation granted.' : 'Request declined.');
    }

    /** Whoever put the name forward can withdraw it. */
    public function cancelOfficer(Request $request, Club $club, ClubRoleRequest $roleRequest)
    {
        abort_unless((int) $roleRequest->nominated_by === (int) $request->user()->id, 403);
        abort_unless((int) $roleRequest->club_id === (int) $club->id, 404);

        if (! $roleRequest->isPending()) {
            return back()->with('error', 'That request has already been answered.');
        }

        $roleRequest->update(['status' => ClubRoleRequest::CANCELLED, 'decided_at' => now()]);

        return back()->with('success', 'Request withdrawn.');
    }

    /**
     * The outgoing president puts a whole new panel forward. Nothing changes
     * until the moderator, or a super admin, agrees to it.
     */
    public function proposePanel(Request $request, Club $club)
    {
        $user = $request->user();

        abort_unless(SocialRules::canProposeClubPanel($user, $club), 403);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
            'panel' => ['required', 'array', 'min:1', 'max:30'],
            'panel.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'panel.*.club_role' => ['required', 'string', 'max:80'],
        ]);

        // One row per person, last entry wins, so a duplicated name in the
        // form does not become two seats.
        $seats = [];

        foreach ($data['panel'] as $row) {
            $role = trim((string) $row['club_role']);

            if ($role === '') {
                continue;
            }

            $seats[(int) $row['user_id']] = ['user_id' => (int) $row['user_id'], 'club_role' => $role];
        }

        $seats = array_values($seats);

        // The moderator is staff, appointed by a super admin. ClubPanel::apply
        // already refuses to strip their role; this is the other half of that,
        // so a handover cannot hand them a student designation either.
        if ($club->moderator_id && array_key_exists((int) $club->moderator_id, array_column($seats, null, 'user_id'))) {
            return back()->with('error', 'The club moderator cannot hold a student designation.');
        }

        // Everyone on the panel has to actually be in the club.
        $memberIds = $club->members()->pluck('users.id')->all();
        $outsiders = array_diff(array_column($seats, 'user_id'), $memberIds);

        if ($outsiders !== []) {
            return back()->with('error', 'Everyone on the panel has to be a club member first.');
        }

        if ($problem = ClubPanel::problemWith($seats)) {
            return back()->with('error', $problem);
        }

        if ($club->panelChanges()->where('status', ClubPanelChange::PENDING)->exists()) {
            return back()->with('warning', 'There is already a panel change waiting to be reviewed.');
        }

        $change = null;

        DB::transaction(function () use ($club, $user, $data, $seats, &$change) {
            $change = ClubPanelChange::create([
                'club_id' => $club->id,
                'proposed_by' => $user->id,
                'note' => $data['note'] ?? null,
                'status' => ClubPanelChange::PENDING,
            ]);

            foreach ($seats as $i => $seat) {
                $change->panelMembers()->create([
                    'user_id' => $seat['user_id'],
                    'club_role' => $seat['club_role'],
                    'position' => $i,
                ]);
            }
        });

        if ($club->moderator_id && $club->moderator_id !== $user->id) {
            Notifier::send(
                $club->moderator_id,
                $user,
                Notifier::SYSTEM,
                'club_panel_change',
                $change->id,
                $user->full_name.' proposed a new panel for '.$club->name.'.'
            );
        }

        return back()->with('success', 'Panel sent to the club moderator for approval.');
    }

    /**
     * The moderator or a super admin answers. Approving swaps the entire panel
     * over in one go: every old designation is dropped and the new slate takes
     * them. The people replaced stay in the club without a title.
     */
    public function reviewPanel(Request $request, Club $club, ClubPanelChange $panelChange)
    {
        $user = $request->user();

        abort_unless(SocialRules::canApproveClubPanel($user, $club), 403);
        abort_unless((int) $panelChange->club_id === (int) $club->id, 404);

        $data = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject'],
            'decision_note' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $panelChange->isPending()) {
            return back()->with('error', 'That panel change has already been answered.');
        }

        $approved = $data['decision'] === 'approve';
        $previous = [];

        if ($approved) {
            $panelChange->load(['panelMembers', 'club']);
            $previous = ClubPanel::apply($panelChange);
        }

        $panelChange->update([
            'status' => $approved ? ClubPanelChange::APPROVED : ClubPanelChange::REJECTED,
            'decision_note' => $data['decision_note'] ?? null,
            'decided_by' => $user->id,
            'decided_at' => now(),
            'previous_panel' => $approved ? $previous : null,
        ]);

        // Everybody affected hears about it: the new panel, the old one, and
        // whoever proposed it.
        $told = array_unique(array_merge(
            [$panelChange->proposed_by],
            $panelChange->panelMembers->pluck('user_id')->all(),
            array_column($previous, 'user_id'),
        ));

        foreach ($told as $recipient) {
            Notifier::send(
                $recipient,
                $user,
                Notifier::SYSTEM,
                'club_panel_change',
                $panelChange->id,
                $approved
                    ? 'The new panel for '.$club->name.' is in place.'
                    : 'The proposed panel for '.$club->name.' was declined.'
            );
        }

        return back()->with('success', $approved
            ? 'New panel is in place. The previous panel keep their membership but no longer hold designations.'
            : 'Panel change declined.');
    }

    /** Whoever proposed it can withdraw it while it is still pending. */
    public function cancelPanel(Request $request, Club $club, ClubPanelChange $panelChange)
    {
        abort_unless((int) $panelChange->proposed_by === (int) $request->user()->id, 403);
        abort_unless((int) $panelChange->club_id === (int) $club->id, 404);

        if (! $panelChange->isPending()) {
            return back()->with('error', 'That panel change has already been answered.');
        }

        $panelChange->update(['status' => ClubPanelChange::CANCELLED, 'decided_at' => now()]);

        return back()->with('success', 'Panel proposal withdrawn.');
    }

    /**
     * Appoints or replaces the faculty moderator. Super admin only - not the
     * outgoing moderator, and not the students.
     */
    public function changeModerator(Request $request, Club $club)
    {
        abort_unless(SocialRules::canChangeClubModerator($request->user()), 403);

        $data = $request->validate([
            'moderator_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $moderator = $data['moderator_id'] ? User::find($data['moderator_id']) : null;

        if ($moderator && ! in_array($moderator->role, ['faculty', 'official', 'admin'], true)) {
            return back()->with('error', 'A club moderator has to be a faculty or office account.');
        }

        $previous = $club->moderator;

        $club->forceFill(['moderator_id' => $moderator?->id])->save();

        if ($moderator) {
            // The moderator is a member of their own club, and runs its group.
            DB::table('club_members')->updateOrInsert(
                ['club_id' => $club->id, 'user_id' => $moderator->id],
                ['club_role' => 'Moderator', 'can_post_events' => true, 'joined_at' => now()]
            );

            Notifier::send(
                $moderator->id,
                $request->user(),
                Notifier::SYSTEM,
                'club',
                $club->id,
                'You are now the moderator of '.$club->name.'.'
            );
        }

        if ($previous && $previous->id !== $moderator?->id) {
            // The outgoing moderator keeps their membership; only the title
            // moves.
            DB::table('club_members')
                ->where('club_id', $club->id)
                ->where('user_id', $previous->id)
                ->update(['club_role' => 'member', 'can_post_events' => false]);
        }

        GroupSync::club($club->fresh());

        return back()->with('success', $moderator
            ? $moderator->full_name.' now moderates '.$club->name.'.'
            : 'Moderator removed.');
    }

    /**
     * Replaces the club's logo.
     *
     * The banner goes through the gallery like every other picture on the
     * site, but the logo is a single square that belongs to the club rather
     * than a gallery of one, so it keeps its own column and its own upload.
     * Whoever may edit the banner may change the logo - one answer, asked of
     * Gallery::canEdit so the two cannot drift apart.
     */
    public function updateLogo(Request $request, Club $club)
    {
        abort_unless(Gallery::canEdit($request->user(), $club), 403);

        $request->validate([
            'logo' => ['required', 'image', 'max:5120'],
        ]);

        $previous = $club->logo_path;

        $path = $request->file('logo')->store('clubs/logos', 'public');

        ImageResizer::shrink($path);

        $club->forceFill(['logo_path' => $path])->save();

        // Only once the new one is safely recorded, and never a file another
        // row might still be pointing at.
        if ($previous && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return back()->with('success', 'Club logo updated.');
    }

    /** Puts the club back to its generated initials badge. */
    public function removeLogo(Request $request, Club $club)
    {
        abort_unless(Gallery::canEdit($request->user(), $club), 403);

        $previous = $club->logo_path;

        $club->forceFill(['logo_path' => null])->save();

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        return back()->with('success', 'Club logo removed.');
    }
}
