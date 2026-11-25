<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Events\InternalChatMessagePersisted;
use App\Modules\Chat\Events\InternalChatMessagesRead;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatConversationService
{
    public function __construct(
        private readonly OrderParticipantResolver $participantResolver
    ) {
    }

    /**
     * Opens or creates the dedicated conversation room for an order, verifying
     * that the authenticated user belongs to that order as customer or courier.
     */
    public function findOrCreateForOrder(int $actorUserId, int|string $orderIdentifier): Conversation
    {
        $resolved = $this->participantResolver->resolve($orderIdentifier, $actorUserId);
        if ($resolved === null) {
            throw (new ModelNotFoundException())->setModel(Conversation::class, [$orderIdentifier]);
        }

        $isAuthorized = $resolved['customer_user_id'] === $actorUserId
            || ($resolved['courier_user_id'] !== null && $resolved['courier_user_id'] === $actorUserId);

        if (! $isAuthorized) {
            throw new AuthorizationException('Anda tidak memiliki akses ke percakapan pengiriman ini.');
        }

        return DB::transaction(function () use ($resolved): Conversation {
            $query = Conversation::query();
            if ($resolved['order_id'] !== null && $resolved['shipment_id'] !== null) {
                $query->where('order_id', $resolved['order_id'])
                    ->orWhere('shipment_id', $resolved['shipment_id']);
            } elseif ($resolved['order_id'] !== null) {
                $query->where('order_id', $resolved['order_id']);
            } elseif ($resolved['shipment_id'] !== null) {
                $query->where('shipment_id', $resolved['shipment_id']);
            }

            $conversation = $query->lockForUpdate()->first();

            if ($conversation === null) {
                return Conversation::create([
                    'order_id' => $resolved['order_id'],
                    'shipment_id' => $resolved['shipment_id'],
                    'order_number' => $resolved['order_number'],
                    'customer_user_id' => $resolved['customer_user_id'],
                    'courier_user_id' => $resolved['courier_user_id'],
                    'courier_id' => $resolved['courier_id'],
                    'status' => $this->deriveInitialStatus($resolved['status'] ?? null),
                ]);
            }

            $dirty = false;
            if ($conversation->order_id === null && $resolved['order_id'] !== null) {
                $conversation->order_id = $resolved['order_id'];
                $dirty = true;
            }
            if ($conversation->courier_user_id === null && $resolved['courier_user_id'] !== null) {
                $conversation->courier_user_id = $resolved['courier_user_id'];
                $conversation->courier_id = $resolved['courier_id'];
                $dirty = true;
            }
            if ($conversation->shipment_id === null && $resolved['shipment_id'] !== null) {
                $conversation->shipment_id = $resolved['shipment_id'];
                $dirty = true;
            }
            if ($dirty) {
                $conversation->save();
            }

            return $conversation;
        });
    }

    public function listForUser(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        $safePerPage = max(1, min($perPage, 50));

        return Conversation::query()
            ->forParticipant($userId)
            ->with(['customer', 'courierUser', 'lastMessage'])
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->paginate($safePerPage);
    }

    public function totalUnreadForUser(int $userId): int
    {
        $customerUnread = (int) Conversation::query()
            ->where('customer_user_id', $userId)
            ->sum('customer_unread_count');

        $courierUnread = (int) Conversation::query()
            ->where('courier_user_id', $userId)
            ->sum('courier_unread_count');

        return $customerUnread + $courierUnread;
    }

    /**
     * Cursor-based history pagination ordered chronologically (oldest -> newest) in the page slice.
     *
     * @return array{messages: Collection<int, Message>, has_more: bool, next_before_id: ?int}
     */
    public function listMessages(Conversation $conversation, ?int $beforeId = null, int $limit = 30): array
    {
        $safeLimit = max(1, min($limit, (int) config('chat.max_messages_per_page', 100)));

        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->with('sender');

        if ($beforeId !== null && $beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $rows = $query->orderByDesc('id')->limit($safeLimit + 1)->get();
        $hasMore = $rows->count() > $safeLimit;
        $sliced = $rows->take($safeLimit)->reverse()->values();

        return [
            'messages' => $sliced,
            'has_more' => $hasMore,
            'next_before_id' => $hasMore && $sliced->isNotEmpty() ? (int) $sliced->first()->id : null,
        ];
    }

    /**
     * Persists a message atomically inside a transaction with client_message_id idempotency.
     *
     * @return array{message: Message, conversation: Conversation, is_duplicate: bool}
     */
    public function sendInternalMessage(
        Conversation $conversation,
        int $senderUserId,
        ?string $body,
        string $messageType = Message::TYPE_TEXT,
        ?string $clientMessageId = null,
        ?UploadedFile $media = null
    ): array {
        if ($conversation->status !== Conversation::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'conversation' => ['Percakapan pengiriman ini sudah ditutup.'],
            ]);
        }

        $cleanBody = $body !== null ? trim($body) : null;
        if (($cleanBody === null || $cleanBody === '') && $media === null) {
            throw ValidationException::withMessages([
                'body' => ['Isi pesan atau lampiran foto wajib diisi.'],
            ]);
        }

        $storedMedia = null;
        if ($media !== null) {
            $extension = strtolower($media->getClientOriginalExtension() ?: ($media->extension() ?: 'jpg'));
            $filename = Str::uuid()->toString() . '.' . $extension;
            $disk = (string) config('chat.media_disk', 'local');
            $path = $media->storeAs('chat-media/' . $conversation->id, $filename, ['disk' => $disk]);

            $storedMedia = [
                'path' => $path,
                'mime' => $media->getMimeType() ?? $media->getClientMimeType(),
                'size' => $media->getSize(),
            ];
            $messageType = Message::TYPE_IMAGE;
        }

        $result = DB::transaction(function () use (
            $conversation,
            $senderUserId,
            $cleanBody,
            $messageType,
            $clientMessageId,
            $storedMedia
        ): array {
            /** @var Conversation $lockedConversation */
            $lockedConversation = Conversation::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $role = $lockedConversation->roleForUser($senderUserId);
            if ($role === null) {
                throw new AuthorizationException('Pengguna bukan peserta percakapan ini.');
            }

            if ($clientMessageId !== null && $clientMessageId !== '') {
                $existing = Message::query()
                    ->where('conversation_id', $lockedConversation->id)
                    ->where('client_message_id', $clientMessageId)
                    ->first();

                if ($existing !== null) {
                    return [
                        'message' => $existing->load('sender'),
                        'conversation' => $lockedConversation,
                        'is_duplicate' => true,
                    ];
                }
            }

            $now = now();
            $message = Message::create([
                'conversation_id' => $lockedConversation->id,
                'sender_user_id' => $senderUserId,
                'sender_role' => $role,
                'client_message_id' => $clientMessageId,
                'channel' => Message::CHANNEL_INTERNAL,
                'message_type' => $messageType,
                'body' => $cleanBody,
                'media_path' => $storedMedia['path'] ?? null,
                'media_mime' => $storedMedia['mime'] ?? null,
                'media_size' => $storedMedia['size'] ?? null,
                'delivery_status' => Message::STATUS_SENT,
                'sent_at' => $now,
            ]);

            $recipientUnreadCol = $role === Message::ROLE_CUSTOMER
                ? 'courier_unread_count'
                : 'customer_unread_count';

            $lockedConversation->forceFill([
                'last_message_id' => $message->id,
                'last_message_preview' => $message->previewText(),
                'last_message_at' => $now,
                $recipientUnreadCol => (int) $lockedConversation->{$recipientUnreadCol} + 1,
            ])->save();

            return [
                'message' => $message->load('sender'),
                'conversation' => $lockedConversation,
                'is_duplicate' => false,
            ];
        });

        if (! $result['is_duplicate']) {
            InternalChatMessagePersisted::dispatch($result['conversation'], $result['message']);
        }

        return $result;
    }

    /**
     * Marks counterparty messages up to $upToMessageId as read and resets the reader's unread count.
     *
     * @return array{conversation: Conversation, updated_ids: array<int, int>}
     */
    public function markAsRead(Conversation $conversation, int $readerUserId, ?int $upToMessageId = null): array
    {
        $result = DB::transaction(function () use ($conversation, $readerUserId, $upToMessageId): array {
            /** @var Conversation $lockedConversation */
            $lockedConversation = Conversation::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $readerRole = $lockedConversation->roleForUser($readerUserId);
            if ($readerRole === null) {
                throw new AuthorizationException('Pengguna bukan peserta percakapan ini.');
            }

            $query = Message::query()
                ->where('conversation_id', $lockedConversation->id)
                ->where('sender_role', '!=', $readerRole)
                ->where('delivery_status', '!=', Message::STATUS_READ);

            if ($upToMessageId !== null && $upToMessageId > 0) {
                $query->where('id', '<=', $upToMessageId);
            }

            $ids = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
            $now = now();

            if ($ids !== []) {
                Message::query()
                    ->whereIn('id', $ids)
                    ->whereNull('delivered_at')
                    ->update(['delivered_at' => $now]);

                Message::query()
                    ->whereIn('id', $ids)
                    ->update([
                        'delivery_status' => Message::STATUS_READ,
                        'read_at' => $now,
                    ]);
            }

            $unreadColumn = $lockedConversation->unreadColumnForRole($readerRole);
            if ((int) $lockedConversation->{$unreadColumn} !== 0) {
                $lockedConversation->forceFill([$unreadColumn => 0])->save();
            }

            return [
                'conversation' => $lockedConversation,
                'reader_role' => $readerRole,
                'updated_ids' => $ids,
                'read_at_iso' => $now->toIso8601String(),
            ];
        });

        if ($result['updated_ids'] !== []) {
            InternalChatMessagesRead::dispatch(
                $result['conversation'],
                $readerUserId,
                $result['reader_role'],
                $result['updated_ids'],
                $result['read_at_iso']
            );
        }

        return [
            'conversation' => $result['conversation'],
            'updated_ids' => $result['updated_ids'],
        ];
    }

    private function deriveInitialStatus(?string $orderStatus): string
    {
        if (in_array(strtolower((string) $orderStatus), ['cancelled'], true)) {
            return Conversation::STATUS_CLOSED;
        }

        return Conversation::STATUS_ACTIVE;
    }
}
