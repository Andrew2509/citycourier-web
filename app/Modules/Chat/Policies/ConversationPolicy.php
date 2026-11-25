<?php

namespace App\Modules\Chat\Policies;

use App\Modules\Chat\Models\Conversation;
use Illuminate\Contracts\Auth\Authenticatable;

class ConversationPolicy
{
    public function view(Authenticatable $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant((int) $user->getAuthIdentifier());
    }

    public function sendMessage(Authenticatable $user, Conversation $conversation): bool
    {
        if (! $this->view($user, $conversation)) {
            return false;
        }

        return $conversation->status === Conversation::STATUS_ACTIVE;
    }

    public function markRead(Authenticatable $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function sendTyping(Authenticatable $user, Conversation $conversation): bool
    {
        return $this->sendMessage($user, $conversation);
    }
}
