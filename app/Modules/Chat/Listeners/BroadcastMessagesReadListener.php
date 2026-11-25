<?php

namespace App\Modules\Chat\Listeners;

use App\Modules\Chat\Events\InternalChatMessagesRead;
use App\Modules\Chat\Events\MessageStatusUpdatedBroadcast;
use App\Modules\Chat\Models\Message;
use Throwable;

class BroadcastMessagesReadListener
{
    public function handle(InternalChatMessagesRead $event): void
    {
        try {
            MessageStatusUpdatedBroadcast::dispatch(
                $event->conversation,
                $event->messageIds,
                Message::STATUS_READ,
                $event->readAtIso,
                $event->readerUserId,
                $event->readerRole
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
