<?php

namespace App\Modules\Chat\Listeners;

use App\Modules\Chat\Events\InternalChatMessagePersisted;
use App\Modules\Chat\Models\Message;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Services\WhatsAppCloudClient;

class QueueWhatsAppBridgeListener
{
    public function __construct(
        private readonly WhatsAppCloudClient $client
    ) {
    }

    public function handle(InternalChatMessagePersisted $event): void
    {
        if (
            ! (bool) config('chat.whatsapp_bridge_enabled', false)
            || ! $this->client->isConfigured()
            || ! $event->conversation->wa_bridged_enabled
            || $event->message->channel !== Message::CHANNEL_INTERNAL
        ) {
            return;
        }

        if ((bool) config('chat.use_queue_worker', false) || config('queue.default') === 'sync') {
            SendWhatsAppMessageJob::dispatch(
                $event->conversation->id,
                $event->message->id
            );

            return;
        }

        SendWhatsAppMessageJob::dispatchAfterResponse(
            $event->conversation->id,
            $event->message->id
        );
    }
}
