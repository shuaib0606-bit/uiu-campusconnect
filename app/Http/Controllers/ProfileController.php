<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\ProfileReport;
use App\Models\User;
use App\Services\SocialRules;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function me(Request $request)
    {
        return redirect()->route('profile.show', $request->user());
    }

    public function show(Request $request, User $user)
    {
        $viewer = $request->user();
        $user->load('profile');

        $posts = Post::visible()
            ->where('user_id', $user->id)
            ->with(['user.profile', 'reactions', 'saves', 'comments'])
            ->latest()
            ->paginate(10);

        return view('profile.show', [
            'profileUser' => $user,
            'posts' => $posts,
            'friendship' => SocialRules::friendshipStatus($viewer, $user),
            'canRequest' => SocialRules::canRequestConnection($viewer, $user),
            'canMessage' => SocialRules::canMessage($viewer, $user),
            'isFollowing' => $viewer->following()->where('followed_id', $user->id)->exists(),
        ]);
    }

    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()->load('profile')]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'designation' => ['nullable', 'string', 'max:150'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'room' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:50'],
            'office_hours' => ['nullable', 'string', 'max:255'],
            'skills' => ['nullable', 'string', 'max:500'],
            'privacy_level' => ['nullable', 'string', 'max:30'],
            'show_email' => ['nullable', 'boolean'],
            'show_phone' => ['nullable', 'boolean'],
            'show_friend_list' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'image', 'max:10240'],
            'cover' => ['nullable', 'image', 'max:10240'],
        ]);

        $user->update([
            'full_name' => $data['full_name'],
            'department' => $data['department'] ?? null,
            'designation' => $data['designation'] ?? null,
        ]);

        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);

        if ($request->hasFile('avatar')) {
            $data['profile_photo_path'] = $request->file('avatar')->store('profiles', 'public');
        }
        if ($request->hasFile('cover')) {
            $data['cover_photo_path'] = $request->file('cover')->store('covers', 'public');
        }

        $profile->update(array_merge($data, [
            'show_email' => $request->boolean('show_email'),
            'show_phone' => $request->boolean('show_phone'),
            'show_friend_list' => $request->boolean('show_friend_list'),
        ]));

        return redirect()->route('profile.show', $user)->with('success', 'Profile updated.');
    }

    public function report(Request $request, User $user)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:700'],
        ]);

        ProfileReport::updateOrCreate([
            'reported_user_id' => $user->id,
            'reporter_id' => $request->user()->id,
            'status' => 'pending',
        ], $data);

        return back()->with('success', 'Report submitted to moderators.');
    }
}
