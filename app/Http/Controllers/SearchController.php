<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\CommunityGroup;
use App\Models\Event;
use App\Models\MarketplaceItem;
use App\Models\Notice;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public const FILTERS = ['all', 'people', 'posts', 'clubs', 'events', 'notices', 'groups', 'marketplace'];

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $filter = in_array($request->get('filter'), self::FILTERS, true) ? $request->get('filter') : 'all';
        $department = trim((string) $request->get('department', ''));

        $users = $posts = $clubs = $events = $notices = $groups = $marketplace = collect();

        if ($q !== '') {
            $like = "%{$q}%";
            $wants = fn (string $key) => in_array($filter, ['all', $key], true);

            if ($wants('people')) {
                $users = User::where('status', 'active')
                    ->where(function ($query) use ($like) {
                        $query->where('full_name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('department', 'like', $like)
                            ->orWhere('designation', 'like', $like);
                    })
                    ->when($department !== '', fn ($query) => $query->where('department', $department))
                    ->orderBy('full_name')
                    ->limit(20)
                    ->get();
            }

            if ($wants('posts')) {
                $posts = Post::visible()
                    ->where(fn ($query) => $query->where('body', 'like', $like)->orWhere('post_type', 'like', $like))
                    ->with('user')
                    ->latest()
                    ->limit(20)
                    ->get();
            }

            if ($wants('clubs')) {
                $clubs = Club::where('status', 'active')
                    ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('category', 'like', $like)->orWhere('description', 'like', $like))
                    ->limit(20)
                    ->get();
            }

            if ($wants('events')) {
                $events = Event::where('status', 'published')
                    ->where(fn ($query) => $query->where('title', 'like', $like)->orWhere('category', 'like', $like)->orWhere('location', 'like', $like))
                    ->orderBy('start_datetime')
                    ->limit(20)
                    ->get();
            }

            if ($wants('notices')) {
                $notices = Notice::where('status', 'published')
                    ->where(fn ($query) => $query->where('title', 'like', $like)->orWhere('body', 'like', $like)->orWhere('notice_type', 'like', $like))
                    ->latest()
                    ->limit(20)
                    ->get();
            }

            if ($wants('groups')) {
                $groups = CommunityGroup::where(fn ($query) => $query->where('name', 'like', $like)->orWhere('description', 'like', $like)->orWhere('group_type', 'like', $like))
                    ->limit(20)
                    ->get();
            }

            if ($wants('marketplace')) {
                $marketplace = MarketplaceItem::where('status', 'available')
                    ->where(fn ($query) => $query->where('title', 'like', $like)->orWhere('description', 'like', $like)->orWhere('category', 'like', $like))
                    ->limit(20)
                    ->get();
            }
        }

        return view('search.index', compact('q', 'filter', 'department', 'users', 'posts', 'clubs', 'events', 'notices', 'groups', 'marketplace'));
    }
}
