<?php

namespace App\Http\Controllers;

use App\Models\ConnectionApplication;
use App\Models\User;
use App\Services\SocialRules;
use Illuminate\Http\Request;

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

        $targetId = (int) $request->get('target_id', 0);
        if ($targetId > 0 && $user->role === 'student') {
            $target = User::where('status', 'active')->whereIn('role', ['faculty', 'official'])->find($targetId);

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

        return view('connect-requests.index', compact(
            'applications', 'tab', 'target', 'targetApplication', 'targetAllowed', 'targetMessage'
        ));
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
            \App\Models\Friendship::firstOrCreate([
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
