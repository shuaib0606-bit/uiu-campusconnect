<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\Notice;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class FeedController extends Controller
{
    public function welcome()
    {
        if (Auth::check()) {
            return redirect()->route('feed.index');
        }

        return view('welcome');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $tab = $request->get('tab', 'for-you');

        $posts = Post::visible()
            ->with([
                'user.profile', 'reactions', 'saves', 'pollOptions.votes',
                'comments.user.profile', 'comments.likes',
                'comments.replies.user.profile', 'comments.replies.likes',
            ])
            ->withCount(['comments' => fn ($q) => $q->where('is_deleted', false)])
            ->when($tab === 'following', function ($q) use ($user) {
                $q->whereIn('user_id', $user->following()->pluck('users.id'));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $latestNotice = Cache::remember('feed:latest-notice', 60, fn () => Notice::where('status', 'published')->latest()->first());

        $upcomingEvents = Event::where('status', 'published')
            ->where('start_datetime', '>=', now())
            ->orderBy('start_datetime')
            ->limit(3)
            ->get();

        $suggestedClubs = Club::where('status', 'active')
            ->whereDoesntHave('members', fn ($q) => $q->where('users.id', $user->id))
            ->withCount('members')
            ->inRandomOrder()
            ->limit(3)
            ->get();

        return view('feed.index', compact('posts', 'tab', 'latestNotice', 'upcomingEvents', 'suggestedClubs'));
    }
}
