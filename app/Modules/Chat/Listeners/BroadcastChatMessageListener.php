<?php

namespace App\Modules\Chat\Listeners;

use App\Modules\Chat\Events\ConversationUpdatedBroadcast;
use App\Modules\Chat\Events\InternalChatMessagePersisted;
use App\Modules\Chat\Events\MessageSentBroadcast;
use Throwable;

class BroadcastChatMessageListener
{
    public function handle(InternalChatMessagePersisted $event): void
    {
        // Realtime WebSocket errors must never roll back or fail an already persisted chat message.
        try {
            MessageSentBroadcast::dispatch($event->conversation, $event->message);
            ConversationUpdatedBroadcast::dispatch($event->conversation, $event->message);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
