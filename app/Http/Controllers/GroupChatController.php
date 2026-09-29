<?php

namespace App\Http\Controllers;

use App\Events\GroupMessageSent;
use App\Models\CommunityGroup;
use App\Models\GroupMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Group chat.
 *
 * Two ways in and the same answers from both: a socket pushes new lines
 * when Reverb is running, and this controller serves them when it is
 * not. The fallback is not a nicety - a websocket server is one more
 * process that can be down, and a class conversation that silently stops
 * updating is worse than one that updates a few seconds late.
 */
class GroupChatController extends Controller
{
    /** Lines returned on first load. Enough to scroll back through, not enough to be slow. */
    private const PAGE = 60;

    /** Messages one person may send in a minute. Generous for talking, tight enough to stop a flood. */
    private const PER_MINUTE = 25;

    /**
     * Recent messages, or everything after a given id.
     *
     * The `after` form is what the polling fallback asks on a timer, and
     * what a reconnecting socket asks to fill the gap it missed.
     */
    public function index(Request $request, CommunityGroup $group)
    {
        // Membership alone is not enough. While the teacher has
        // communication switched off the chat does not exist for students,
        // and hiding only the button would leave this endpoint open to
        // anyone who guessed the URL.
        abort_unless($this->visibleTo($request, $group), 403);

        $after = (int) $request->query('after', 0);

        $query = GroupMessage::where('group_id', $group->id)
            ->with(['author:id,full_name,avatar_initials,role', 'author.profile:user_id,profile_photo_path']);

        $messages = $after > 0
            ? $query->where('id', '>', $after)->orderBy('id')->limit(self::PAGE)->get()
            // Newest first to take the last page, then flipped, because a
            // conversation reads downwards.
            : $query->latest('id')->limit(self::PAGE)->get()->reverse()->values();

        return response()->json([
            'messages' => $messages->map(fn (GroupMessage $message) => $message->forBrowser())->values(),
            'can_send' => $group->canChat($request->user()),
        ]);
    }

    public function store(Request $request, CommunityGroup $group)
    {
        $user = $request->user();

        // canPost is the same rule the wall uses: staff always, everyone
        // else only when the group is set to let members post.
        abort_unless($group->canChat($user), 403, 'You cannot write in this group\'s chat right now.');
        abort_if($group->isArchived(), 403, 'This group is archived.');

        $key = 'chat:'.$group->id.':'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            return response()->json([
                'error' => 'You are sending messages very quickly. Wait a moment.',
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $message = GroupMessage::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'body' => trim($data['body']),
        ]);

        $message->setRelation('author', $user);

        // Everyone else hears about it over the socket. The sender gets it
        // back in this response, so their own line appears immediately
        // whether or not the socket is up.
        $this->push($message, toOthers: true);

        return response()->json(['message' => $message->forBrowser()], 201);
    }

    public function destroy(Request $request, CommunityGroup $group, GroupMessage $message)
    {
        abort_unless($message->group_id === $group->id, 404);
        abort_unless($message->deletableBy($request->user()), 403);

        // A tombstone rather than a hole: the replies around it still read
        // as a conversation.
        $message->update(['is_deleted' => true, 'body' => '']);

        $this->push($message);

        return response()->json(['message' => $message->forBrowser()]);
    }

    /**
     * Whether this person may see the chat at all.
     *
     * Staff always, because they own the switch. Everyone else only while
     * the group is set to let members post - the same flag the button is
     * hidden behind, asked again on the server so hiding the button is
     * decoration rather than the security.
     */
    private function visibleTo(Request $request, CommunityGroup $group): bool
    {
        $user = $request->user();

        // The same rule decides who may read and who may write: a chat
        // you can open but never speak in is worse than no chat tab.
        return $group->canChat($user);
    }

    /**
     * Announces a message over the socket, and shrugs if that fails.
     *
     * BROADCAST_CONNECTION=reverb with no reverb:start running means the
     * push throws a connection error. That must not take the request with
     * it: the message is already saved, and every open browser is polling
     * as well, so the only cost of a failed push is a few seconds' delay.
     * Losing the message - or handing the sender a 500 - would be worse.
     */
    private function push(GroupMessage $message, bool $toOthers = false): void
    {
        try {
            $broadcast = broadcast(new GroupMessageSent($message));

            if ($toOthers) {
                $broadcast->toOthers();
            }
        } catch (\Throwable $e) {
            Log::warning('Group chat broadcast failed; clients will poll instead.', [
                'group_id' => $message->group_id,
                'reason' => $e->getMessage(),
            ]);
        }
    }
}
