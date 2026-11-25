<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Modules\Chat\Events\UserTypingBroadcast;
use App\Modules\Chat\Http\Controllers\Concerns\FormatsChatResponse;
use App\Modules\Chat\Http\Requests\StoreMessageRequest;
use App\Modules\Chat\Http\Resources\MessageResource;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Services\ChatConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MessageController extends Controller
{
    use FormatsChatResponse;

    public function __construct(
        private readonly ChatConversationService $conversationService
    ) {
    }

    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $conversation);

        $beforeId = $request->filled('before_id') ? (int) $request->integer('before_id') : null;
        $limit = (int) $request->integer('limit', (int) config('chat.messages_per_page', 30));

        $page = $this->conversationService->listMessages($conversation, $beforeId, $limit);

        return $this->ok([
            'messages' => MessageResource::collection($page['messages'])->resolve($request),
            'has_more' => $page['has_more'],
            'next_before_id' => $page['next_before_id'],
        ], 'Riwayat pesan berhasil dimuat.');
    }

    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::forUser($request->user())->authorize('sendMessage', $conversation);

        $validated = $request->validated();
        $result = $this->conversationService->sendInternalMessage(
            conversation: $conversation,
            senderUserId: (int) $request->user()->getAuthIdentifier(),
            body: $validated['body'] ?? null,
            messageType: $validated['message_type'] ?? Message::TYPE_TEXT,
            clientMessageId: $validated['client_message_id'] ?? null,
            media: $request->file('media'),
        );

        return $this->ok(
            MessageResource::make($result['message'])->resolve($request),
            $result['is_duplicate'] ? 'Pesan sudah tersimpan.' : 'Pesan berhasil dikirim.',
            $result['is_duplicate'] ? 200 : 201
        );
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::forUser($request->user())->authorize('markRead', $conversation);

        $upToMessageId = $request->filled('last_read_message_id')
            ? (int) $request->integer('last_read_message_id')
            : null;

        $result = $this->conversationService->markAsRead(
            $conversation,
            (int) $request->user()->getAuthIdentifier(),
            $upToMessageId
        );

        return $this->ok([
            'conversation_id' => (int) $conversation->id,
            'updated_message_ids' => $result['updated_ids'],
            'updated_count' => count($result['updated_ids']),
        ], 'Status baca pesan diperbarui.');
    }

    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::forUser($request->user())->authorize('sendTyping', $conversation);

        $userId = (int) $request->user()->getAuthIdentifier();
        $isTyping = $request->boolean('is_typing', true);
        $throttleSeconds = (int) config('chat.typing_throttle_seconds', 3);
        $throttleKey = sprintf('chat:typing:%d:%d:%d', $conversation->id, $userId, $isTyping ? 1 : 0);

        $throttled = ! Cache::add($throttleKey, 1, now()->addSeconds(max(1, $throttleSeconds)));
        if (! $throttled) {
            $role = $conversation->roleForUser($userId) ?? Message::ROLE_CUSTOMER;
            try {
                UserTypingBroadcast::dispatch($conversation, $userId, $role, $isTyping);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->ok([
            'conversation_id' => (int) $conversation->id,
            'is_typing' => $isTyping,
            'throttled' => $throttled,
        ], 'Status mengetik diproses.');
    }

    public function media(Request $request, Message $message): Response
    {
        Gate::forUser($request->user())->authorize('view', $message->conversation);

        $disk = (string) config('chat.media_disk', 'local');
        if ($message->media_path === null || ! Storage::disk($disk)->exists($message->media_path)) {
            abort(404, 'Media tidak ditemukan.');
        }

        return Storage::disk($disk)->response($message->media_path, null, [
            'Content-Type' => $message->media_mime ?? 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
