<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $remember = $request->boolean('remember');

        if (! Auth::attempt([
            'email' => strtolower($credentials['email']),
            'password' => $credentials['password'],
            'status' => 'active',
        ], $remember)) {
            return back()->withErrors([
                'email' => 'Those credentials don\'t match an active account.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('feed.index'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
