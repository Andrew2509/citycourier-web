<?php

use App\Modules\Chat\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', function ($user, int|string $conversationId): bool {
    $conversation = Conversation::query()->find((int) $conversationId);
    if ($conversation === null) {
        return false;
    }

    return $conversation->isParticipant((int) $user->getAuthIdentifier());
});

Broadcast::channel('user.{userId}', function ($user, int|string $userId): bool {
    return (int) $user->getAuthIdentifier() === (int) $userId;
});
