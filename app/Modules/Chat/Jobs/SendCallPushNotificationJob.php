<?php

namespace App\Modules\Chat\Jobs;

use App\Modules\Chat\Models\CallSession;
use App\Modules\Chat\Services\FcmPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCallPushNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly int $callSessionId,
        public readonly string $eventType = 'incoming_call'
    ) {
    }

    public function handle(FcmPushService $fcmPushService): void
    {
        $call = CallSession::query()
            ->with(['conversation', 'caller.courier', 'receiver.courier'])
            ->find($this->callSessionId);

        if ($call === null) {
            return;
        }

        if ($this->eventType === 'incoming_call' && $call->status === CallSession::STATUS_RINGING) {
            $fcmPushService->notifyIncomingCall($call);

            return;
        }

        if ($this->eventType === 'call_cancelled') {
            $fcmPushService->notifyCallCancelled($call);
        }
    }
}
