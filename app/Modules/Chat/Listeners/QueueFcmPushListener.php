<?php

namespace App\Modules\Chat\Listeners;

use App\Modules\Chat\Events\InternalChatMessagePersisted;
use App\Modules\Chat\Jobs\SendChatPushNotificationJob;
use App\Modules\Chat\Services\FcmPushService;

class QueueFcmPushListener
{
    public function __construct(
        private readonly FcmPushService $pushService
    ) {
    }

    public function handle(InternalChatMessagePersisted $event): void
    {
        if (! $this->pushService->isConfigured()) {
            return;
        }

        if ((bool) config('chat.use_queue_worker', false) || config('queue.default') === 'sync') {
            SendChatPushNotificationJob::dispatch(
                $event->conversation->id,
                $event->message->id
            );

            return;
        }

        SendChatPushNotificationJob::dispatchAfterResponse(
            $event->conversation->id,
            $event->message->id
        );
    }
}
