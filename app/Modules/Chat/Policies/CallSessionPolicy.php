<?php

namespace App\Modules\Chat\Policies;

use App\Modules\Chat\Models\CallSession;
use Illuminate\Contracts\Auth\Authenticatable;

class CallSessionPolicy
{
    public function view(Authenticatable $user, CallSession $callSession): bool
    {
        return $callSession->isParticipant((int) $user->getAuthIdentifier());
    }

    public function accept(Authenticatable $user, CallSession $callSession): bool
    {
        return (int) $callSession->receiver_id === (int) $user->getAuthIdentifier();
    }

    public function reject(Authenticatable $user, CallSession $callSession): bool
    {
        return (int) $callSession->receiver_id === (int) $user->getAuthIdentifier();
    }

    public function end(Authenticatable $user, CallSession $callSession): bool
    {
        return $callSession->isParticipant((int) $user->getAuthIdentifier());
    }

    public function signal(Authenticatable $user, CallSession $callSession): bool
    {
        return $callSession->isParticipant((int) $user->getAuthIdentifier());
    }
}
