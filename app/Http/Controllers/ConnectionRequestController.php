<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubPanelChange;
use App\Models\ClubRoleRequest;
use App\Models\ConnectionApplication;
use App\Models\Friendship;
use App\Models\User;
use App\Services\SocialRules;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Students apply to connect with faculty or officials, and an admin reviews the application. */
class ConnectionRequestController extends Controller
{
    private const TABS = ['pending', 'reviewing', 'approved', 'rejected', 'cancelled'];

    public function index(Request $request)
    {
        $user = $request->user();
        $tab = $request->get('tab', 'pending');
        $tab = in_array($tab, self::TABS, true) ? $tab : 'pending';

        $target = null;
        $targetApplication = null;
        $targetAllowed = false;
        $targetMessage = '';

        // ?target= carries the public id, so the address does not reveal
        // or accept a sequential account number.
        $targetKey = (string) $request->get('target', '');
        if ($targetKey !== '' && $user->role === 'student') {
            $target = User::where('status', 'active')->whereIn('role', ['faculty', 'official'])->where('public_id', $targetKey)->first();

            if ($target) {
                $targetApplication = ConnectionApplication::where('student_id', $user->id)
                    ->where('target_id', $target->id)
                    ->latest('id')
                    ->first();

                if (SocialRules::areConnected($user, $target)) {
                    $targetMessage = 'You are already approved to connect with this user.';
                } elseif ($targetApplication && in_array($targetApplication->status, ['pending', 'reviewing'], true)) {
                    $targetMessage = 'Your application is already waiting for admin review.';
                } elseif ($targetApplication && $targetApplication->status === 'approved') {
                    $targetMessage = 'This application was already approved.';
                } else {
                    $targetAllowed = true;
                }
            }
        }

        if ($user->isAdmin()) {
            $applications = ConnectionApplication::with(['student', 'target', 'reviewer'])
                ->where('status', $tab)
                ->latest('updated_at')
                ->limit(200)
                ->get();
        } elseif ($user->role === 'student') {
            $applications = ConnectionApplication::with(['student', 'target', 'reviewer'])
                ->where('student_id', $user->id)
                ->latest('updated_at')
                ->limit(100)
                ->get();
        } else {
            $applications = ConnectionApplication::with(['student', 'target', 'reviewer'])
                ->where('target_id', $user->id)
                ->where('status', 'approved')
                ->latest('updated_at')
                ->limit(100)
                ->get();
        }

        $connected = $target !== null && SocialRules::areConnected($user, $target);

        return Inertia::render('ConnectRequests/Index', [
            'applications' => $applications->map(fn (ConnectionApplication $app) => $this->card($app, $user))->values(),
            'clubRoleRequests' => $this->clubRoleRequests($user),
            'clubPanelChanges' => $this->clubPanelChanges($user),
            'tab' => $tab,
            'tabs' => self::TABS,
            'role' => $user->isAdmin() ? 'admin' : $user->role,
            'missingTarget' => $targetKey !== '' && $user->role === 'student' && $target === null,
            'target' => $target === null ? null : [
                'id' => $target->public_id,
                'full_name' => $target->full_name,
                'avatar_url' => $target->avatar_url,
                'avatar_initials' => $target->avatar_initials,
                'subtitle' => implode(' · ', array_filter([ucfirst($target->role), $target->designation, $target->department])),
                'connected' => $connected,
                'pending' => $targetApplication !== null && in_array($targetApplication->status, ['pending', 'reviewing'], true),
                'approved' => $targetApplication !== null && $targetApplication->status === 'approved',
                'status_label' => $targetApplication?->statusLabel(),
                'blocked_reason' => $targetAllowed ? null : ($targetMessage ?: null),
                'apply_url' => route('connect-requests.apply', $target),
                'message_url' => route('messages.show', $target),
            ],
        ]);
    }

    /**
     * Club designation requests belong on this page too: they are the other
     * thing a student is waiting on an answer for. Somebody sees a request
     * when they put the name forward, when they moderate the club, or when
     * they are the person being put forward.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function clubRoleRequests(User $user)
    {
        return ClubRoleRequest::with(['club', 'nominee', 'nominator', 'decider'])
            ->when(! $user->isAdmin(), fn ($q) => $q
                ->where(fn ($inner) => $inner
                    ->where('nominated_by', $user->id)
                    ->orWhere('nominee_id', $user->id)
                    ->orWhereIn('club_id', Club::where('moderator_id', $user->id)->pluck('id'))))
            ->latest('updated_at')
            ->limit(50)
            ->get()
            ->map(function (ClubRoleRequest $req) use ($user) {
                // A super admin can do anything the club's moderator can.
                $isModerator = $user->isAdmin()
                    || (int) ($req->club->moderator_id ?? 0) === (int) $user->id;

                return [
                    'id' => $req->id,
                    'status' => $req->status,
                    'status_label' => $req->statusLabel(),
                    'club' => $req->club->name ?? 'Club',
                    'club_url' => $req->club ? route('clubs.show', $req->club) : null,
                    'nominee' => $req->nominee->full_name ?? 'Unknown',
                    'nominator' => $req->nominator->full_name ?? 'Unknown',
                    'club_role' => $req->club_role,
                    'reason' => $req->reason,
                    'decision_note' => $req->decision_note,
                    'decided_by' => $req->decider?->full_name,
                    'submitted_at' => $req->created_at->format('M j, Y g:i A'),
                    // Which line to show: the moderator is being asked, the
                    // nominator is waiting, the nominee is being discussed.
                    'viewer' => $isModerator
                        ? 'moderator'
                        : ((int) $req->nominated_by === (int) $user->id ? 'nominator' : 'nominee'),
                    'can_review' => $isModerator && $req->isPending(),
                    'can_cancel' => (int) $req->nominated_by === (int) $user->id && $req->isPending(),
                    'review_url' => route('clubs.officers.review', [$req->club_id, $req->id]),
                    'cancel_url' => route('clubs.officers.cancel', [$req->club_id, $req->id]),
                ];
            });
    }

    /**
     * Panel handovers waiting on, or decided by, this person. A handover is
     * the club's whole student panel changing at once, so it is shown as a
     * slate rather than a list of promotions.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function clubPanelChanges(User $user)
    {
        return ClubPanelChange::with(['club', 'proposer', 'decider', 'panelMembers.user'])
            ->when(! $user->isAdmin(), fn ($q) => $q
                ->where(fn ($inner) => $inner
                    ->where('proposed_by', $user->id)
                    ->orWhereIn('club_id', Club::where('moderator_id', $user->id)->pluck('id'))
                    ->orWhereExists(fn ($sub) => $sub->selectRaw('1')
                        ->from('club_panel_members')
                        ->whereColumn('club_panel_members.panel_change_id', 'club_panel_changes.id')
                        ->where('club_panel_members.user_id', $user->id))))
            ->latest('updated_at')
            ->limit(30)
            ->get()
            ->map(function (ClubPanelChange $change) use ($user) {
                $canDecide = $user->isAdmin()
                    || (int) ($change->club->moderator_id ?? 0) === (int) $user->id;

                return [
                    'id' => $change->id,
                    'status' => $change->status,
                    'status_label' => $change->statusLabel(),
                    'club' => $change->club->name ?? 'Club',
                    'club_url' => $change->club ? route('clubs.show', $change->club) : null,
                    'proposer' => $change->proposer->full_name ?? 'Unknown',
                    'note' => $change->note,
                    'decision_note' => $change->decision_note,
                    'decided_by' => $change->decider?->full_name,
                    'submitted_at' => $change->created_at->format('M j, Y g:i A'),
                    'seats' => $change->panelMembers->map(fn ($seat) => [
                        'name' => $seat->user->full_name ?? 'Unknown',
                        'role' => $seat->club_role,
                    ])->values(),
                    'replaced_count' => is_array($change->previous_panel) ? count($change->previous_panel) : 0,
                    'can_review' => $canDecide && $change->isPending(),
                    'can_cancel' => (int) $change->proposed_by === (int) $user->id && $change->isPending(),
                    'review_url' => route('clubs.panel.review', [$change->club_id, $change->id]),
                    'cancel_url' => route('clubs.panel.cancel', [$change->club_id, $change->id]),
                ];
            });
    }

    /** One application, with the viewer's own permissions already decided. */
    private function card(ConnectionApplication $app, User $viewer): array
    {
        $settled = in_array($app->status, ['approved', 'rejected', 'cancelled'], true);

        return [
            'id' => $app->id,
            'status' => $app->status,
            'status_label' => $app->statusLabel(),
            'purpose' => $app->purpose,
            'course_department' => $app->course_department ?: 'Not specified',
            'message' => $app->message,
            'admin_note' => $app->admin_note,
            'submitted_at' => $app->created_at->format('M j, Y g:i A'),
            'updated_at' => $app->updated_at->format('M j, Y g:i A'),
            'student' => [
                'full_name' => $app->student->full_name,
                'email' => $app->student->email,
                'department' => $app->student->department,
                'url' => route('profile.show', $app->student),
            ],
            'target' => [
                'full_name' => $app->target->full_name,
                'role' => ucfirst($app->target->role),
                'url' => route('profile.show', $app->target),
            ],
            'can_review' => $viewer->isAdmin() && ! $settled,
            'can_cancel' => $app->student_id === $viewer->id && in_array($app->status, ['pending', 'reviewing'], true),
            'can_message' => $app->status === 'approved' && $app->student_id === $viewer->id,
            'message_url' => route('messages.show', $app->target),
            'actions' => [
                'review' => route('connect-requests.review', $app),
                'cancel' => route('connect-requests.cancel', $app),
                'suspend' => route('connect-requests.suspend-student', $app),
            ],
        ];
    }

    public function apply(Request $request, User $user)
    {
        $me = $request->user();

        if ($me->role !== 'student' || ! in_array($user->role, ['faculty', 'official'], true)) {
            return back()->with('error', 'This action is not available.');
        }

        if (SocialRules::areConnected($me, $user)) {
            return back()->with('error', 'You are already approved to connect with this user.');
        }

        $existing = ConnectionApplication::where('student_id', $me->id)->where('target_id', $user->id)->latest('id')->first();
        if ($existing && in_array($existing->status, ['pending', 'reviewing', 'approved'], true)) {
            return back()->with('error', 'You already have an active application for this person.');
        }

        $data = $request->validate([
            'purpose' => ['nullable', 'string', 'max:120'],
            'course_department' => ['nullable', 'string', 'max:190'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        ConnectionApplication::create([
            'student_id' => $me->id,
            'target_id' => $user->id,
            'purpose' => $data['purpose'] ?: 'Academic support',
            'course_department' => $data['course_department'] ?? null,
            'message' => $data['message'],
            'status' => 'pending',
        ]);

        return redirect()->route('connect-requests.index')->with('success', 'Application sent to admin for review.');
    }

    public function review(Request $request, ConnectionApplication $application)
    {
        $admin = $request->user();
        if (! $admin->isAdmin()) {
            abort(403);
        }

        $data = $request->validate([
            'application_action' => ['required', 'in:reviewing,approve,reject'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = match ($data['application_action']) {
            'reviewing' => 'reviewing',
            'approve' => 'approved',
            'reject' => 'rejected',
        };

        $application->update([
            'status' => $status,
            'admin_note' => $data['admin_note'] ?? $application->admin_note,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        if ($status === 'approved') {
            Friendship::firstOrCreate([
                'requester_id' => $application->student_id,
                'addressee_id' => $application->target_id,
            ], ['status' => 'accepted']);
        }

        return redirect()->route('connect-requests.index', ['tab' => $request->get('tab', 'pending')])
            ->with('success', 'Application updated.');
    }

    public function cancel(Request $request, ConnectionApplication $application)
    {
        $me = $request->user();
        if ($application->student_id !== $me->id) {
            abort(403);
        }

        if (in_array($application->status, ['pending', 'reviewing'], true)) {
            $application->update(['status' => 'cancelled']);
        }

        return redirect()->route('connect-requests.index')->with('success', 'Application cancelled.');
    }

    public function suspendStudent(Request $request, ConnectionApplication $application)
    {
        $admin = $request->user();
        if (! $admin->isAdmin()) {
            abort(403);
        }

        $application->student()->update(['status' => 'suspended']);

        return redirect()->route('connect-requests.index', ['tab' => $request->get('tab', 'pending')])
            ->with('success', 'Student account suspended.');
    }
}
