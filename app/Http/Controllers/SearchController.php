<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $people = $users = $posts = $clubs = $events = collect();

        if ($q !== '') {
            $users = User::where('status', 'active')->where('full_name', 'like', "%{$q}%")->limit(8)->get();
            $posts = Post::visible()->where('body', 'like', "%{$q}%")->with('user')->latest()->limit(8)->get();
            $clubs = Club::where('status', 'active')->where('name', 'like', "%{$q}%")->limit(8)->get();
            $events = Event::where('status', 'published')->where('title', 'like', "%{$q}%")->limit(8)->get();
        }

        return view('search.index', compact('q', 'users', 'posts', 'clubs', 'events'));
    }
}
