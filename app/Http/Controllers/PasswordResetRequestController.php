<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PasswordResetRequestController extends Controller
{
    /** Characters used for temporary passwords. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

    private const TEMP_LENGTH = 12;

    public function create()
    {
        return view('auth.forgot-password');
    }

    /**
     * Files a recovery request. The response is identical whether or not the identifier matched an
     * account, and an unmatched request is still stored.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:190'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $identifier = trim($data['identifier']);

        $user = User::where('email', $identifier)
            ->orWhere('student_id', $identifier)
            ->orWhere('employee_id', $identifier)
            ->first();

        // One open request per account at a time.
        $alreadyOpen = $user
            ? PasswordResetRequest::where('user_id', $user->id)->where('status', 'pending')->exists()
            : false;

        if (! $alreadyOpen) {
            PasswordResetRequest::create([
                'user_id' => $user?->id,
                'submitted_identifier' => $identifier,
                'note' => $data['note'] ?? null,
                'status' => 'pending',
                'ip_address' => $request->ip(),
            ]);
        }

        return back()->with('success', 'Your request has been sent to the CampusConnect admin office. If the details match an account, an administrator will contact you with a temporary password.');
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only the master administrator can review password recovery requests.');

        $tab = in_array($request->get('tab'), ['pending', 'approved', 'rejected', 'all'], true)
            ? $request->get('tab')
            : 'pending';

        $requests = PasswordResetRequest::with(['user', 'reviewer'])
            ->when($tab !== 'all', fn ($q) => $q->where('status', $tab))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = PasswordResetRequest::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.password-requests', [
            'requests' => $requests,
            'tab' => $tab,
            'counts' => $counts,
            // Flashed by review() and readable for one redirect only.
            'issued' => session('issued_password'),
            'issuedFor' => session('issued_for'),
        ]);
    }

    /**
     * Approves or rejects a request. Approval sets a random password with must_change_password, and the
     * generated password is shown to the administrator once and never stored in readable form.
     */
    public function review(Request $request, PasswordResetRequest $passwordRequest)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $passwordRequest->isPending()) {
            return back()->with('error', 'That request has already been reviewed.');
        }

        if ($data['decision'] === 'reject') {
            $passwordRequest->update([
                'status' => 'rejected',
                'review_note' => $data['review_note'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            return back()->with('success', 'Request rejected.');
        }

        $user = $passwordRequest->user;

        if (! $user) {
            return back()->with('error', 'This request is not attached to a real account, so there is nothing to reset. Reject it instead.');
        }

        $temporary = $this->temporaryPassword();

        $user->forceFill([
            'password' => Hash::make($temporary),
            'must_change_password' => true,
            'password_changed_at' => now(),
        ])->save();

        $passwordRequest->update([
            'status' => 'approved',
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()
            ->with('issued_password', $temporary)
            ->with('issued_for', $user->full_name);
    }

    /** Random temporary password. The alphabet excludes look-alike characters. */
    private function temporaryPassword(): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::TEMP_LENGTH; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
