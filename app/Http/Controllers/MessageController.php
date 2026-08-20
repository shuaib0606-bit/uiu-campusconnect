<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use App\Services\SocialRules;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin() && ! $request->expectsJson()) {
            return view('messages.disabled');
        }

        $threads = Message::query()
            ->where('sender_id', $user->id)
            ->orWhere('receiver_id', $user->id)
            ->with(['sender', 'receiver'])
            ->latest()
            ->get()
            ->groupBy(fn (Message $m) => $m->sender_id === $user->id ? $m->receiver_id : $m->sender_id)
            ->map(fn ($group) => [
                'user' => $group->first()->sender_id === $user->id ? $group->first()->receiver : $group->first()->sender,
                'last' => $group->first(),
                'unread' => $group->where('receiver_id', $user->id)->where('is_read', false)->count(),
            ])
            ->sortByDesc(fn ($t) => $t['last']->created_at);

        if ($request->expectsJson()) {
            return response()->json([
                'threads' => $threads->values()->map(fn ($t) => [
                    'user_id' => $t['user']->id,
                    'name' => $t['user']->full_name,
                    'avatar_initials' => $t['user']->avatar_initials,
                    'role' => $t['user']->role,
                    'designation' => $t['user']->designation,
                    'last_body' => $t['last']->body ?: ($t['last']->attachment_path ? 'Attachment' : ''),
                    'time' => $t['last']->created_at->diffForHumans(null, true),
                    'unread' => $t['unread'],
                ]),
            ]);
        }

        return view('messages.index', compact('threads'));
    }

    public function show(Request $request, User $user)
    {
        $me = $request->user();

        if (! $request->expectsJson()) {
            return redirect()->route('messages.index', ['with' => $user->id]);
        }

        $messages = Message::query()
            ->where(fn ($q) => $q->where('sender_id', $me->id)->where('receiver_id', $user->id))
            ->orWhere(fn ($q) => $q->where('sender_id', $user->id)->where('receiver_id', $me->id))
            ->oldest()
            ->get();

        Message::where('sender_id', $user->id)->where('receiver_id', $me->id)->update(['is_read' => true]);
        SocialRules::forgetCountsFor($me->id);

        return response()->json([
            'other_user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'avatar_initials' => $user->avatar_initials,
                'role' => $user->role,
                'designation' => $user->designation,
            ],
            'messages' => $messages->map(fn (Message $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'attachment_url' => $m->attachment_path ? \Illuminate\Support\Facades\Storage::url($m->attachment_path) : null,
                'mine' => $m->sender_id === $me->id,
                'time' => $m->created_at->format('g:i A'),
            ]),
        ]);
    }

    public function store(Request $request, User $user)
    {
        $me = $request->user();

        if (! SocialRules::canMessage($me, $user)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => true, 'reason' => 'You cannot message this person.'], 422);
            }

            return back()->with('error', 'You cannot message this person.');
        }

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:4000'],
            'attachment' => ['nullable', 'file', 'max:35720'],
        ]);

        if (empty($data['body']) && ! $request->hasFile('attachment')) {
            if ($request->expectsJson()) {
                return response()->json(['error' => true, 'reason' => 'Write a message or attach a file.'], 422);
            }

            return back()->with('error', 'Write a message or attach a file.');
        }

        $path = null;
        $type = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('messages', 'public');
            $type = $file->getClientMimeType();
        }

        $message = Message::create([
            'sender_id' => $me->id,
            'receiver_id' => $user->id,
            'body' => $data['body'] ?? null,
            'attachment_path' => $path,
            'attachment_type' => $type,
        ]);

        SocialRules::forgetCountsFor($user->id);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'attachment_url' => $path ? \Illuminate\Support\Facades\Storage::url($path) : null,
                    'mine' => true,
                    'time' => $message->created_at->format('g:i A'),
                ],
            ]);
        }

        return back();
    }
}
