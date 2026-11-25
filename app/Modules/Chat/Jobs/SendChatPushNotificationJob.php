<?php

namespace App\Modules\Chat\Jobs;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Services\FcmPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendChatPushNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $messageId
    ) {
    }

    public function handle(FcmPushService $pushService): void
    {
        $conversation = Conversation::query()->find($this->conversationId);
        $message = Message::query()->find($this->messageId);

        if ($conversation === null || $message === null) {
            return;
        }

        $pushService->notifyNewMessage($conversation, $message);
    }
}
