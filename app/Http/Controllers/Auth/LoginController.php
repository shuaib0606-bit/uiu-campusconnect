<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', strtolower($credentials['email']))->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'Those credentials don\'t match an account.'])->onlyInput('email');
        }

        if ($user->status !== 'active') {
            $message = match ($user->status) {
                'pending' => 'Your account is awaiting administrator approval before you can sign in.',
                'suspended' => 'Your account has been suspended. Contact an administrator for help.',
                'rejected' => 'Your account request was not approved.',
                default => 'Your account is not currently active.',
            };

            return back()->withErrors(['email' => $message])->onlyInput('email');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('feed.index'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Sign-out lands on the public landing page, not the sign-in form.
        return redirect()->route('welcome');
    }
}
