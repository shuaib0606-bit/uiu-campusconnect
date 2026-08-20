<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use App\Rules\UiuEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['student', 'faculty', 'official'])],
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email', new UiuEmail],
            'password' => ['required', 'confirmed', Password::min(6)],
            'department' => ['nullable', 'string', 'max:150'],
            'designation' => ['nullable', 'string', 'max:150'],
            'student_id' => ['nullable', 'string', 'max:50'],
            'employee_id' => ['nullable', 'string', 'max:50'],
            'year' => ['nullable', 'string', 'max:50'],
        ]);

        $role = $data['role'];
        $designation = $data['designation'] ?: match ($role) {
            'faculty' => 'Faculty',
            'official' => 'University Official',
            default => 'Student',
        };
        $authorityLevel = match ($role) {
            'official' => 'official_pending',
            'faculty' => 'faculty_pending',
            default => 'member',
        };
        $status = $role === 'student' ? 'active' : 'pending';
        $initials = Str::of($data['full_name'])->replaceMatches('/[^A-Za-z]/', '')->substr(0, 2)->upper()->value() ?: 'UI';

        $user = User::create([
            'full_name' => $data['full_name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'role' => $role,
            'department' => $data['department'] ?? null,
            'designation' => $designation,
            'student_id' => $role === 'student' ? ($data['student_id'] ?? null) : null,
            'employee_id' => $role !== 'student' ? ($data['employee_id'] ?? null) : null,
            'year' => $role === 'student' ? ($data['year'] ?? null) : null,
            'authority_level' => $authorityLevel,
            'status' => $status,
            'avatar_initials' => $initials,
        ]);

        UserProfile::create([
            'user_id' => $user->id,
            'bio' => 'New UIU CampusConnect member.',
        ]);

        if ($status === 'active') {
            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->route('feed.index');
        }

        return redirect()->route('login')
            ->with('success', 'Account request submitted. A university administrator must approve this role before sign in.');
    }
}
