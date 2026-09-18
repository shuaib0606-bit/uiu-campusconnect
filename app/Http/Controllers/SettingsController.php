<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function passwordForm(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Settings/Password', [
            'forced' => $request->boolean('force') || $user->must_change_password,
            'security' => [
                'email' => $user->email,
                'role' => $user->roleLabel(),
                'last_login' => optional($user->last_login_at)->format('M j, Y g:i A') ?? 'Not recorded yet',
                'password_changed' => optional($user->password_changed_at)->format('M j, Y g:i A') ?? 'Not recorded yet',
            ],
            'profileUrl' => route('profile.show', $user),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'password_changed_at' => now(),
            'must_change_password' => false,
        ])->save();

        // Stays on this page rather than jumping to the feed. The settings page
        // is React and the feed is not, so a redirect across the two throws an
        // Inertia error instead of showing the confirmation.
        return redirect()->route('settings.password')->with('success', 'Password updated.');
    }
}
