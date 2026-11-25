<?php

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\Models\CallSession;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Support\AvatarUrlResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CallSession */
class CallSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerId = (int) ($request->user()?->getAuthIdentifier() ?? 0);
        $conv = $this->relationLoaded('conversation') ? $this->conversation : null;
        $callerUser = $this->relationLoaded('caller') ? $this->caller : null;
        $receiverUser = $this->relationLoaded('receiver') ? $this->receiver : null;

        $callerIsCourier = $conv !== null && $conv->courier_user_id !== null
            && (int) $conv->courier_user_id === (int) $this->caller_id;
        $receiverIsCourier = $conv !== null && $conv->courier_user_id !== null
            && (int) $conv->courier_user_id === (int) $this->receiver_id;

        $viewerRole = (int) $this->caller_id === $viewerId ? 'caller' : 'receiver';

        return [
            'id' => (int) $this->id,
            'call_uuid' => $this->call_uuid,
            'order_id' => $this->order_id !== null ? (int) $this->order_id : null,
            'order_number' => $conv?->order_number ?? ($this->order_id !== null ? 'ORD-' . $this->order_id : null),
            'conversation_id' => (int) $this->conversation_id,
            'caller_id' => (int) $this->caller_id,
            'receiver_id' => (int) $this->receiver_id,
            'status' => $this->status,
            'end_reason' => $this->end_reason,
            'viewer_role' => $viewerRole,
            'caller' => [
                'user_id' => (int) $this->caller_id,
                'role' => $callerIsCourier ? Message::ROLE_COURIER : Message::ROLE_CUSTOMER,
                'name' => $callerUser?->name ?? ($callerIsCourier ? 'Kurir City Courier' : 'Customer'),
                'avatar' => AvatarUrlResolver::resolveForUser($callerUser, $callerIsCourier),
            ],
            'receiver' => [
                'user_id' => (int) $this->receiver_id,
                'role' => $receiverIsCourier ? Message::ROLE_COURIER : Message::ROLE_CUSTOMER,
                'name' => $receiverUser?->name ?? ($receiverIsCourier ? 'Kurir City Courier' : 'Customer'),
                'avatar' => AvatarUrlResolver::resolveForUser($receiverUser, $receiverIsCourier),
            ],
            'started_at' => $this->started_at?->toIso8601String(),
            'answered_at' => $this->answered_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'duration' => $this->duration,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
