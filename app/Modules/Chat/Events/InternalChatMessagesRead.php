<?php

namespace App\Modules\Chat\Events;

use App\Modules\Chat\Models\Conversation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InternalChatMessagesRead
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<int, int>  $messageIds
     */
    public function __construct(
        public readonly Conversation $conversation,
        public readonly int $readerUserId,
        public readonly string $readerRole,
        public readonly array $messageIds,
        public readonly string $readAtIso
    ) {
    }
}
