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

class AdminCenterController extends Controller
{
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

        return view('admin.center', compact('stats', 'recentUsers'));
    }
}
