<?php

namespace App\Modules\Chat\Events;

use App\Modules\Chat\Http\Resources\MessageResource;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSentBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly Message $message
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->conversation->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->message->loadMissing('sender');

        return [
            'conversation_id' => (int) $this->conversation->id,
            'order_id' => (int) $this->conversation->order_id,
            'message' => MessageResource::make($this->message)->resolve(request()),
            'customer_unread_count' => (int) $this->conversation->customer_unread_count,
            'courier_unread_count' => (int) $this->conversation->courier_unread_count,
        ];
    }
}
