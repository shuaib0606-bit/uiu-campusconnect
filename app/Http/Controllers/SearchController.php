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
            $wants = fn (string $key) => in_array($filter, ['all', $key], true);

            // Short queries match only the start of a word in a title or
            // name; from three characters, any part of the text. "F" used
            // to match every post, club and notice with an f anywhere in it.
            $escaped = addcslashes($q, '%_\\');
            $long = mb_strlen($q) >= 3;
            $match = fn (array $titles, array $more = []) => function ($w) use ($titles, $more, $escaped, $long) {
                foreach ($titles as $column) {
                    $w->orWhere($column, 'like', $escaped.'%')->orWhere($column, 'like', '% '.$escaped.'%');
                }

                if ($long) {
                    foreach (array_merge($titles, $more) as $column) {
                        $w->orWhere($column, 'like', '%'.$escaped.'%');
                    }
                }
            };

            if ($wants('people')) {
                $users = $this->people($request->user(), $q, $department);
            }

            if ($wants('posts')) {
                $posts = Post::visible()
                    // Blocks, mutes and audience rules hold in search as well.
                    ->forViewer($request->user())
                    ->where($match(['body'], ['post_type']))
                    ->with('user')
                    ->latest()
                    ->limit(20)
                    ->get();
            }

            if ($wants('clubs')) {
                $clubs = Club::where('status', 'active')
                    ->where($match(['name'], ['category', 'description']))
                    ->limit(20)
                    ->get();
            }

            if ($wants('events')) {
                $events = Event::where('status', 'published')
                    ->where($match(['title'], ['category', 'location']))
                    ->orderBy('start_datetime')
                    ->limit(20)
                    ->get();
            }

            if ($wants('notices')) {
                // visibleTo is the notice board's own rule: a class or club
                // notice reaches that group's members and nobody else.
                // Without it, search listed the title and type of every
                // class notice in the university to anyone who typed a
                // matching word.
                $notices = Notice::where('status', 'published')
                    ->visibleTo($request->user())
                    ->where($match(['title'], ['body', 'notice_type']))
                    ->latest()
                    ->limit(20)
                    ->get();
            }

            if ($wants('groups')) {
                $groups = CommunityGroup::where($match(['name'], ['description', 'group_type']))
                    ->limit(20)
                    ->get();
            }

            if ($wants('marketplace')) {
                $marketplace = MarketplaceItem::where('status', 'available')
                    ->where($match(['title'], ['description', 'category']))
                    ->limit(20)
                    ->get();
            }
        }

        return view('search.index', compact('q', 'filter', 'department', 'users', 'posts', 'clubs', 'events', 'notices', 'groups', 'marketplace'));
    }

    /**
     * People whose name - or, for longer queries, department or title -
     * starts with what was typed.
     *
     * The old query matched the text anywhere in the name, email,
     * department or designation, so "F" returned everyone whose title
     * contains "Professor" or whose email has an f in it. Now:
     *
     *  - a name matches at the start of any word in it ("F" finds Farzana
     *    and Dr. Fahim, not Rafiul);
     *  - department and title count only from three letters, also at the
     *    start of a word, so "Eng" finds English and Engineering;
     *  - an email or ID counts only from three characters, only as a
     *    prefix, and only for people who show it - searching someone's
     *    hidden address must not reveal whose it is;
     *  - inactive accounts, and anyone blocked either way, never appear.
     *
     * Results are ranked: name starts with the query, then a later word
     * in the name does, then everything else.
     */
    private function people(User $viewer, string $q, string $department)
    {
        $escaped = addcslashes($q, '%_\\');
        $starts = $escaped.'%';
        $wordStarts = '% '.$escaped.'%';
        $long = mb_strlen($q) >= 3;

        return User::query()
            ->where('users.status', 'active')
            ->leftJoin('user_profiles as up', 'up.user_id', '=', 'users.id')
            ->select('users.*')
            ->whereNotExists(fn ($b) => $b->selectRaw('1')->from('user_blocks')
                ->where(fn ($w) => $w
                    ->where(fn ($x) => $x->whereColumn('user_blocks.blocked_id', 'users.id')->where('user_blocks.blocker_id', $viewer->id))
                    ->orWhere(fn ($x) => $x->whereColumn('user_blocks.blocker_id', 'users.id')->where('user_blocks.blocked_id', $viewer->id))))
            ->where(function ($w) use ($starts, $wordStarts, $long, $escaped) {
                $w->where('users.full_name', 'like', $starts)
                    ->orWhere('users.full_name', 'like', $wordStarts)
                    // "Dr.Farzana" and hyphenated names: a word can also
                    // begin after a full stop or a hyphen.
                    ->orWhere('users.full_name', 'like', '%.'.$escaped.'%')
                    ->orWhere('users.full_name', 'like', '%-'.$escaped.'%');

                if ($long) {
                    $w->orWhere('users.department', 'like', $starts)
                        ->orWhere('users.department', 'like', $wordStarts)
                        ->orWhere('users.designation', 'like', $starts)
                        ->orWhere('users.designation', 'like', $wordStarts)
                        ->orWhere(fn ($e) => $e->where('users.email', 'like', $starts)
                            ->where(fn ($s) => $s->whereNull('up.user_id')->orWhere('up.show_email', true)))
                        ->orWhere(fn ($i) => $i
                            ->where(fn ($x) => $x->where('users.student_id', 'like', $starts)->orWhere('users.employee_id', 'like', $starts))
                            ->where(fn ($s) => $s->whereNull('up.user_id')->orWhere('up.show_student_id', true)));
                }
            })
            ->when($department !== '', fn ($query) => $query->where('users.department', $department))
            ->orderByRaw('CASE WHEN users.full_name LIKE ? THEN 0 WHEN users.full_name LIKE ? THEN 1 ELSE 2 END', [$starts, $wordStarts])
            ->orderBy('users.full_name')
            ->limit(20)
            ->get();
    }
}
