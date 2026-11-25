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
            ! $this->client->isConfigured()
            || ! $event->conversation->wa_bridged_enabled
            || $event->message->channel !== Message::CHANNEL_INTERNAL
        ) {
            return;
        }

        SendWhatsAppMessageJob::dispatch(
            $event->conversation->id,
            $event->message->id
        );
    }
}
