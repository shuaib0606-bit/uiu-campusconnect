<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

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

        return view('admin.user-approvals', [
            'requests' => $requests,
            'statusCounts' => $statusCounts,
            'tab' => $tab,
            'q' => $q,
        ]);
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
