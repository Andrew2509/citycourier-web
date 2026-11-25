<?php

namespace App\Modules\Chat\Events;

use App\Modules\Chat\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageStatusUpdatedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<int, int>  $messageIds
     */
    public function __construct(
        public readonly Conversation $conversation,
        public readonly array $messageIds,
        public readonly string $deliveryStatus,
        public readonly string $updatedAtIso,
        public readonly ?int $actorUserId = null,
        public readonly ?string $actorRole = null
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
        return 'message.status';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => (int) $this->conversation->id,
            'message_ids' => array_values($this->messageIds),
            'delivery_status' => $this->deliveryStatus,
            'updated_at' => $this->updatedAtIso,
            'actor_user_id' => $this->actorUserId,
            'actor_role' => $this->actorRole,
        ];
    }
}
