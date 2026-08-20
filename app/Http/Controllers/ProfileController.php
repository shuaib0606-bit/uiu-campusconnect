<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\ProfileReport;
use App\Models\User;
use App\Services\SocialRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $isOwn = $viewer && $viewer->id === $user->id;

        $tab = strtolower((string) $request->get('tab', 'all'));
        if (! in_array($tab, ['all', 'about', 'friends', 'photos', 'posts', 'more'], true)) {
            $tab = 'all';
        }

        $posts = in_array($tab, ['all', 'posts'], true)
            ? Post::visible()->where('user_id', $user->id)->with(['user.profile', 'reactions', 'saves', 'comments'])->latest()->paginate(10)
            : collect();

        $photos = $tab === 'photos'
            ? Post::visible()->where('user_id', $user->id)->where('media_type', 'image')->whereNotNull('media_path')->latest()->limit(30)->get()
            : collect();

        $friendCount = DB::table('friendships')->where('status', 'accepted')
            ->where(fn ($q) => $q->where('requester_id', $user->id)->orWhere('addressee_id', $user->id))
            ->count();

        $friends = in_array($tab, ['all', 'friends'], true)
            ? DB::table('friendships')
                ->join('users', 'users.id', '=', DB::raw('CASE WHEN friendships.requester_id = '.(int) $user->id.' THEN friendships.addressee_id ELSE friendships.requester_id END'))
                ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')
                ->where('friendships.status', 'accepted')
                ->where(fn ($q) => $q->where('friendships.requester_id', $user->id)->orWhere('friendships.addressee_id', $user->id))
                ->where('users.status', 'active')
                ->orderByDesc('friendships.updated_at')
                ->limit(12)
                ->get(['users.id', 'users.full_name', 'users.avatar_initials', 'user_profiles.profile_photo_path'])
            : collect();

        $clubs = DB::table('club_members')
            ->join('clubs', 'clubs.id', '=', 'club_members.club_id')
            ->where('club_members.user_id', $user->id)
            ->where('clubs.status', 'active')
            ->orderBy('clubs.name')
            ->get(['clubs.id', 'clubs.name', 'clubs.slug', 'club_members.club_role']);

        $specialRoles = $clubs
            ->pluck('club_role')
            ->filter(fn ($role) => $role && ! in_array(strtolower($role), ['member', 'general member'], true))
            ->unique()
            ->take(2)
            ->map(fn ($role) => ucwords($role));

        return view('profile.show', [
            'profileUser' => $user,
            'isOwn' => $isOwn,
            'tab' => $tab,
            'posts' => $posts,
            'photos' => $photos,
            'friendCount' => $friendCount,
            'friends' => $friends,
            'followerCount' => $user->followers()->count(),
            'followingCount' => $user->following()->count(),
            'postCount' => Post::visible()->where('user_id', $user->id)->count(),
            'photoCount' => Post::visible()->where('user_id', $user->id)->where('media_type', 'image')->whereNotNull('media_path')->count(),
            'clubs' => $clubs,
            'specialRoles' => $specialRoles,
            'friendship' => $viewer ? SocialRules::friendshipStatus($viewer, $user) : null,
            'canRequest' => $viewer ? SocialRules::canRequestConnection($viewer, $user) : [false, ''],
            'canMessage' => $viewer ? SocialRules::canMessage($viewer, $user) : false,
            'isFollowing' => $viewer && $viewer->following()->where('followed_id', $user->id)->exists(),
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
            'interests' => ['nullable', 'string', 'max:700'],
            'courses_taught' => ['nullable', 'string', 'max:700'],
            'research_interests' => ['nullable', 'string', 'max:700'],
            'academic_resources' => ['nullable', 'string', 'max:3000'],
            'privacy_level' => ['nullable', 'string', 'max:30'],
            'show_email' => ['nullable', 'boolean'],
            'show_student_id' => ['nullable', 'boolean'],
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
            'show_student_id' => $request->boolean('show_student_id'),
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
