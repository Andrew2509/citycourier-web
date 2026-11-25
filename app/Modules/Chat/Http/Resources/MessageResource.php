<?php

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $mediaUrl = $this->media_path !== null
            ? url('/api/v1/chat/media/' . $this->id)
            : null;

        return [
            'id' => (int) $this->id,
            'conversation_id' => (int) $this->conversation_id,
            'sender_user_id' => $this->sender_user_id !== null ? (int) $this->sender_user_id : null,
            'sender_name' => $this->relationLoaded('sender') ? $this->sender?->name : null,
            'sender_role' => $this->sender_role,
            'client_message_id' => $this->client_message_id,
            'channel' => $this->channel,
            'message_type' => $this->message_type,
            'body' => $this->body,
            'media_url' => $mediaUrl,
            'media_mime' => $this->media_mime,
            'media_size' => $this->media_size !== null ? (int) $this->media_size : null,
            'delivery_status' => $this->delivery_status,
            'wa_message_id' => $this->wa_message_id,
            'wa_status' => $this->wa_status,
            'wa_error_code' => $this->wa_error_code,
            'wa_error_message' => $this->wa_error_message,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
