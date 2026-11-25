<?php

namespace App\Modules\Chat\Events;

use App\Modules\Chat\Models\CallSignal;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallSignalBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly CallSignal $signal
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->signal->receiver_id),
            new PrivateChannel('call.' . $this->signal->call_session_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.signal';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => (int) $this->signal->id,
            'call_session_id' => (int) $this->signal->call_session_id,
            'sender_id' => (int) $this->signal->sender_id,
            'receiver_id' => (int) $this->signal->receiver_id,
            'signal_type' => $this->signal->signal_type,
            'payload' => $this->signal->payload,
            'created_at' => $this->signal->created_at?->toIso8601String(),
        ];
    }
}
