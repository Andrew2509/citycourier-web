<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\WhatsApp\Services\WhatsAppBridgeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $messageId
    ) {
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function handle(WhatsAppBridgeService $bridgeService): void
    {
        $conversation = Conversation::query()->find($this->conversationId);
        $message = Message::query()->find($this->messageId);

        if ($conversation === null || $message === null) {
            return;
        }

        $attempt = method_exists($this, 'attempts') ? max(1, (int) $this->attempts()) : 1;
        $bridgeService->dispatchOutbound($conversation, $message, $attempt);
    }
}
