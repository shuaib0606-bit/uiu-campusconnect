<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class UserApprovalController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only the master administrator can review account approval requests.');

        $tab = in_array($request->get('tab'), ['pending', 'active', 'rejected', 'suspended', 'all'], true) ? $request->get('tab') : 'pending';
        $q = trim((string) $request->get('q', ''));

        $statusCounts = User::where('role', '!=', 'admin')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $requests = User::where('role', '!=', 'admin')
            ->when($tab !== 'all', fn ($query) => $query->where('status', $tab))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('full_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('student_id', 'like', "%{$q}%")
                        ->orWhere('employee_id', 'like', "%{$q}%")
                        ->orWhere('department', 'like', "%{$q}%");
                });
            })
            ->with('signupReviewer')
            ->orderByRaw("FIELD(status, 'pending', 'active', 'rejected', 'suspended')")
            ->orderByDesc('signup_requested_at')
            ->orderByDesc('id')
            ->limit(400)
            ->get();

        return Inertia::render('Admin/UserApprovals', [
            'requests' => $requests->map(fn (User $req) => $this->card($req))->values(),
            'statusCounts' => [
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'active' => (int) ($statusCounts['active'] ?? 0),
                'rejected' => (int) ($statusCounts['rejected'] ?? 0),
                'suspended' => (int) ($statusCounts['suspended'] ?? 0),
            ],
            'tab' => $tab,
            'filters' => ['q' => $q],
        ]);
    }

    /** One account awaiting a decision, with the buttons it should offer. */
    private function card(User $req): array
    {
        $details = array_filter([
            ucfirst($req->role),
            $req->department,
            $req->student_id ? 'Student ID: '.$req->student_id : null,
            $req->employee_id ? 'Employee ID: '.$req->employee_id : null,
        ]);

        return [
            'id' => $req->id,
            'full_name' => $req->full_name,
            'email' => $req->email,
            'status' => $req->status,
            'status_label' => ucfirst($req->status),
            'details' => implode(' · ', $details),
            'avatar_url' => $req->avatar_url,
            'avatar_initials' => $req->avatar_initials,
            'requested_at' => optional($req->signup_requested_at ?? $req->created_at)->format('M j, Y g:i A'),
            'reviewed_by' => $req->signupReviewer?->full_name ?: 'Not reviewed',
            'review_note' => $req->signup_review_note ?: 'No note',
            'verified' => $req->is_verified ? 'Yes' : 'No',
            'is_student' => $req->role === 'student',
            'id_card_url' => $req->student_id_card_path ? Storage::url($req->student_id_card_path) : null,
            'profile_url' => route('profile.show', $req),
            'review_url' => route('user-approvals.review', $req),
            'can' => [
                'approve' => $req->status !== 'active',
                'reject' => $req->status === 'pending',
                'suspend' => $req->status === 'active',
                'reactivate' => $req->status === 'suspended',
            ],
        ];
    }

    public function review(Request $request, User $target)
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_if($target->role === 'admin', 403);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject,suspend,reactivate'],
            'admin_note' => ['nullable', 'string', 'max:700'],
        ]);

        $note = trim((string) ($data['admin_note'] ?? ''));
        $reviewer = ['signup_reviewed_by' => $request->user()->id, 'signup_reviewed_at' => now()];

        match ($data['decision']) {
            'approve' => $target->forceFill([
                'status' => 'active',
                'is_verified' => true,
                'authority_level' => $target->authority_level ?: match ($target->role) {
                    'faculty' => 'faculty',
                    'official' => 'official',
                    default => 'member',
                },
                'signup_review_note' => $note,
                ...$reviewer,
            ])->save(),
            'reject' => $target->forceFill([
                'status' => 'rejected',
                'signup_review_note' => $note !== '' ? $note : 'Rejected by administrator.',
                ...$reviewer,
            ])->save(),
            'suspend' => $target->forceFill([
                'status' => 'suspended',
                'signup_review_note' => $note !== '' ? $note : 'Suspended by administrator.',
                ...$reviewer,
            ])->save(),
            'reactivate' => $target->forceFill([
                'status' => 'active',
                'is_verified' => true,
                'signup_review_note' => $note !== '' ? $note : 'Reactivated by administrator.',
                ...$reviewer,
            ])->save(),
        };

        $messages = ['approve' => 'Account approved. The user can now sign in.', 'reject' => 'Account request rejected.', 'suspend' => 'Account suspended.', 'reactivate' => 'Account reactivated.'];

        return redirect()->route('user-approvals.index', ['tab' => $request->get('tab', 'pending')])->with('success', $messages[$data['decision']]);
    }
}
