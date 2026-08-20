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

        return view('messages.index', compact('threads'));
    }

    public function show(Request $request, User $user)
    {
        $me = $request->user();

        $messages = Message::query()
            ->where(fn ($q) => $q->where('sender_id', $me->id)->where('receiver_id', $user->id))
            ->orWhere(fn ($q) => $q->where('sender_id', $user->id)->where('receiver_id', $me->id))
            ->oldest()
            ->get();

        Message::where('sender_id', $user->id)->where('receiver_id', $me->id)->update(['is_read' => true]);
        SocialRules::forgetCountsFor($me->id);

        return view('messages.show', [
            'otherUser' => $user,
            'messages' => $messages,
            'canMessage' => SocialRules::canMessage($me, $user),
        ]);
    }

    public function store(Request $request, User $user)
    {
        $me = $request->user();

        if (! SocialRules::canMessage($me, $user)) {
            return back()->with('error', 'You cannot message this person.');
        }

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:4000'],
            'attachment' => ['nullable', 'file', 'max:35720'],
        ]);

        if (empty($data['body']) && ! $request->hasFile('attachment')) {
            return back()->with('error', 'Write a message or attach a file.');
        }

        $path = null;
        $type = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('messages', 'public');
            $type = $file->getClientMimeType();
        }

        Message::create([
            'sender_id' => $me->id,
            'receiver_id' => $user->id,
            'body' => $data['body'] ?? null,
            'attachment_path' => $path,
            'attachment_type' => $type,
        ]);

        SocialRules::forgetCountsFor($user->id);

        return back();
    }
}
