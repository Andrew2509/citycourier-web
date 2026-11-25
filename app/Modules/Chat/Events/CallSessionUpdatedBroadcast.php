<?php

namespace App\Modules\Chat\Events;

use App\Modules\Chat\Http\Resources\CallSessionResource;
use App\Modules\Chat\Models\CallSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallSessionUpdatedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly CallSession $callSession
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->callSession->caller_id),
            new PrivateChannel('user.' . $this->callSession->receiver_id),
            new PrivateChannel('call.' . $this->callSession->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->callSession->loadMissing(['conversation', 'caller.courier', 'receiver.courier']);

        return [
            'call' => CallSessionResource::make($this->callSession)->resolve(request()),
        ];
    }
}
