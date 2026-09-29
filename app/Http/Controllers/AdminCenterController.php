<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\CommentReport;
use App\Models\ConnectionApplication;
use App\Models\Event;
use App\Models\Notice;
use App\Models\ProfileReport;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminCenterController extends Controller
{
    /** The tools this dashboard hands off to. Routes are resolved here. */
    private function cards(): array
    {
        return [
            ['pill' => 'Account recovery', 'title' => 'Password Requests', 'desc' => 'Review lock-out requests, verify identity, and issue a one-time temporary password.', 'href' => route('password-requests.index'), 'cta' => 'Open requests', 'icon' => 'key'],
            ['pill' => 'User validation', 'title' => 'User Approvals', 'desc' => 'Approve new signups, view student UIU ID cards, reject fake accounts, suspend users.', 'href' => route('user-approvals.index'), 'cta' => 'Open approvals', 'icon' => 'user-check'],
            ['pill' => 'Safety', 'title' => 'Moderation Center', 'desc' => 'Review post, comment, and profile reports; remove content or suspend users.', 'href' => route('moderation.index'), 'cta' => 'Open moderation', 'icon' => 'shield'],
            ['pill' => 'Faculty access', 'title' => 'Connection Applications', 'desc' => 'Approve student requests before they can connect with faculty or officials.', 'href' => route('connect-requests.index'), 'cta' => 'Open applications', 'icon' => 'handshake'],
            ['pill' => 'Campus', 'title' => 'Clubs', 'desc' => 'Open clubs, assign moderators, review club membership and panel roles.', 'href' => route('clubs.index'), 'cta' => 'Manage clubs', 'icon' => 'award'],
            ['pill' => 'Events', 'title' => 'Events', 'desc' => 'Review campus and club events across the university.', 'href' => route('events.index'), 'cta' => 'Open events', 'icon' => 'ticket'],
            ['pill' => 'Announcements', 'title' => 'Notices', 'desc' => 'Publish or review official notices and administration announcements.', 'href' => route('notices.index'), 'cta' => 'Open notices', 'icon' => 'megaphone'],
        ];
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Please sign in with the master administrator account to access this dashboard.');

        $openReports = Report::whereIn('status', ModerationController::OPEN_STATUSES)->count()
            + CommentReport::whereIn('status', ModerationController::OPEN_STATUSES)->count()
            + ProfileReport::whereIn('status', ModerationController::OPEN_STATUSES)->count();

        $stats = [
            'pending_users' => User::where('role', '!=', 'admin')->where('status', 'pending')->count(),
            'active_users' => User::where('role', '!=', 'admin')->where('status', 'active')->count(),
            'suspended_users' => User::where('role', '!=', 'admin')->where('status', 'suspended')->count(),
            'pending_apps' => ConnectionApplication::whereIn('status', ['pending', 'reviewing'])->count(),
            'reports' => $openReports,
            'clubs' => Club::where('status', 'active')->count(),
            'events' => Event::where('status', 'published')->count(),
            'notices' => Notice::where('status', 'published')->count(),
        ];

        $recentUsers = User::where('role', '!=', 'admin')->latest()->limit(8)->get();

        return Inertia::render('Admin/Center', [
            'stats' => $stats,
            'cards' => $this->cards(),
            'recentUsers' => $recentUsers->map(fn (User $u) => [
                'id' => $u->id,
                'full_name' => $u->full_name,
                'meta' => $u->email.' · '.ucfirst($u->role).' · '.$u->status,
                'url' => route('profile.show', $u),
            ])->values(),
            'reviewUrl' => route('user-approvals.index', ['tab' => 'all']),
            'settingsUrl' => route('settings.password'),
        ]);
    }
}
