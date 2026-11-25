<?php

namespace App\Modules\Chat\Events;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InternalChatMessagePersisted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly Message $message
    ) {
    }
}
