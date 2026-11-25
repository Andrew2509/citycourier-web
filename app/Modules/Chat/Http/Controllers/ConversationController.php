<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Modules\Chat\Http\Controllers\Concerns\FormatsChatResponse;
use App\Modules\Chat\Http\Resources\ConversationResource;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Services\ChatConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    use FormatsChatResponse;

    public function __construct(
        private readonly ChatConversationService $conversationService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $perPage = (int) $request->integer('per_page', 20);
        $paginator = $this->conversationService->listForUser($userId, $perPage);

        return $this->ok([
            'conversations' => ConversationResource::collection($paginator->items())->resolve($request),
            'total_unread' => $this->conversationService->totalUnreadForUser($userId),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Daftar percakapan berhasil dimuat.');
    }

    public function openByOrder(Request $request, string $orderIdentifier): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $conversation = $this->conversationService->findOrCreateForOrder($userId, $orderIdentifier);
        $conversation->load(['customer', 'courierUser', 'lastMessage']);

        return $this->ok(
            ConversationResource::make($conversation)->resolve($request),
            'Ruang percakapan pengiriman siap digunakan.'
        );
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $conversation);
        $conversation->load(['customer', 'courierUser', 'lastMessage']);

        return $this->ok(
            ConversationResource::make($conversation)->resolve($request),
            'Detail percakapan berhasil dimuat.'
        );
    }

    public function unreadSummary(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();

        return $this->ok([
            'total_unread' => $this->conversationService->totalUnreadForUser($userId),
        ], 'Ringkasan pesan belum dibaca berhasil dimuat.');
    }
}
