<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\SocialRules;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->with('actor')->paginate(20);
        $request->user()->notifications()->where('is_read', false)->update(['is_read' => true]);
        SocialRules::forgetCountsFor($request->user()->id);

        return view('notifications.index', compact('notifications'));
    }

    public function read(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['is_read' => true]);
        SocialRules::forgetCountsFor($request->user()->id);

        return back();
    }
}
