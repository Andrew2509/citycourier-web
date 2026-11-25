<?php

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Conversation */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerId = (int) ($request->user()?->getAuthIdentifier() ?? 0);
        $viewerRole = $this->roleForUser($viewerId) ?? Message::ROLE_CUSTOMER;
        $counterpartyRole = $viewerRole === Message::ROLE_CUSTOMER
            ? Message::ROLE_COURIER
            : Message::ROLE_CUSTOMER;

        $counterpartyUser = $viewerRole === Message::ROLE_CUSTOMER
            ? ($this->relationLoaded('courierUser') ? $this->courierUser : null)
            : ($this->relationLoaded('customer') ? $this->customer : null);

        return [
            'id' => (int) $this->id,
            'order_id' => (int) $this->order_id,
            'shipment_id' => $this->shipment_id !== null ? (int) $this->shipment_id : null,
            'order_number' => $this->order_number,
            'customer_user_id' => (int) $this->customer_user_id,
            'courier_user_id' => $this->courier_user_id !== null ? (int) $this->courier_user_id : null,
            'courier_id' => $this->courier_id !== null ? (int) $this->courier_id : null,
            'status' => $this->status,
            'wa_bridged_enabled' => (bool) $this->wa_bridged_enabled,
            'viewer_role' => $viewerRole,
            'unread_count' => $this->unreadCountForRole($viewerRole),
            'last_message_id' => $this->last_message_id !== null ? (int) $this->last_message_id : null,
            'last_message_preview' => $this->last_message_preview,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'counterparty' => [
                'user_id' => $counterpartyRole === Message::ROLE_COURIER
                    ? ($this->courier_user_id !== null ? (int) $this->courier_user_id : null)
                    : (int) $this->customer_user_id,
                'role' => $counterpartyRole,
                'name' => $counterpartyUser?->name,
                'phone' => $counterpartyUser?->phone,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
