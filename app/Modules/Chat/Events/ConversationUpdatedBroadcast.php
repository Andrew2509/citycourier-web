<?php

namespace App\Modules\Chat\Events;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdatedBroadcast implements ShouldBroadcastNow
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
        $channels = [
            new PrivateChannel('user.' . $this->conversation->customer_user_id),
        ];

        if ($this->conversation->courier_user_id !== null) {
            $channels[] = new PrivateChannel('user.' . $this->conversation->courier_user_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => (int) $this->conversation->id,
            'order_id' => (int) $this->conversation->order_id,
            'order_number' => $this->conversation->order_number,
            'last_message_id' => (int) $this->message->id,
            'last_message_preview' => $this->conversation->last_message_preview,
            'last_message_at' => $this->conversation->last_message_at?->toIso8601String(),
            'sender_user_id' => $this->message->sender_user_id !== null ? (int) $this->message->sender_user_id : null,
            'sender_role' => $this->message->sender_role,
            'customer_unread_count' => (int) $this->conversation->customer_unread_count,
            'courier_unread_count' => (int) $this->conversation->courier_unread_count,
        ];
    }
}
