<?php

namespace App\Events;

use App\Models\GroupMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A line of chat, pushed to everyone watching that group.
 *
 * Private rather than public: the channel name alone must not be enough
 * to listen in. Who may subscribe is decided in routes/channels.php, by
 * the same membership question the rest of the group uses.
 *
 * ShouldBroadcast rather than ShouldBroadcastNow, so with a queue worker
 * running the web request does not wait on the socket server - and with
 * QUEUE_CONNECTION=sync, which is what this project runs, it simply
 * happens inline.
 */
class GroupMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public GroupMessage $message) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('group.'.$this->message->group_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * The same array the polling endpoint returns, so the browser has one
     * renderer for both paths and they cannot drift apart.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['message' => $this->message->forBrowser()];
    }
}
