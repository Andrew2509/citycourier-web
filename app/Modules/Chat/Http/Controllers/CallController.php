<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Modules\Chat\Exceptions\CallStateException;
use App\Modules\Chat\Http\Controllers\Concerns\FormatsChatResponse;
use App\Modules\Chat\Http\Requests\InitiateCallRequest;
use App\Modules\Chat\Http\Requests\StoreCallSignalRequest;
use App\Modules\Chat\Http\Resources\CallSessionResource;
use App\Modules\Chat\Models\CallSession;
use App\Modules\Chat\Models\CallSignal;
use App\Modules\Chat\Services\CallSessionService;
use App\Modules\Chat\Services\IceServerConfigProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class CallController extends Controller
{
    use FormatsChatResponse;

    public function __construct(
        private readonly CallSessionService $callService,
        private readonly IceServerConfigProvider $iceConfigProvider,
    ) {
    }

    public function incoming(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $call = $this->callService->getIncomingForUser($userId);

        if ($call === null) {
            return $this->ok(['call' => null, 'ice_servers' => []], 'Tidak ada panggilan masuk aktif.');
        }

        return $this->ok([
            'call' => CallSessionResource::make($call)->resolve($request),
            'ice_servers' => $this->iceConfigProvider->forParticipant($userId),
        ], 'Panggilan masuk aktif ditemukan.');
    }

    public function initiate(InitiateCallRequest $request): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $validated = $request->validated();

        try {
            $call = $this->callService->initiate(
                callerId: $userId,
                conversationId: isset($validated['conversation_id']) ? (int) $validated['conversation_id'] : null,
                orderIdentifier: isset($validated['order_identifier']) ? (string) $validated['order_identifier'] : null,
                initialOffer: isset($validated['offer']) && is_array($validated['offer']) ? $validated['offer'] : null
            );

            return $this->ok([
                'call' => CallSessionResource::make($call)->resolve($request),
                'ice_servers' => $this->iceConfigProvider->forParticipant($userId),
            ], 'Panggilan suara dimulai.', 201);
        } catch (CallStateException $e) {
            return $this->fail($e->getMessage(), $e->errorCode, $e->statusCode);
        }
    }

    public function show(Request $request, CallSession $callSession): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $this->callService->cleanupStaleCalls($userId);
        $call = $callSession->fresh(['conversation', 'caller.courier', 'receiver.courier']) ?? $callSession;

        Gate::forUser($request->user())->authorize('view', $call);

        return $this->ok([
            'call' => CallSessionResource::make($call)->resolve($request),
            'ice_servers' => $call->isActive() ? $this->iceConfigProvider->forParticipant($userId) : [],
        ], 'Status sesi panggilan berhasil dimuat.');
    }

    public function accept(Request $request, CallSession $callSession): JsonResponse
    {
        Gate::forUser($request->user())->authorize('accept', $callSession);
        $userId = (int) $request->user()->getAuthIdentifier();

        $validated = $request->validate([
            'answer' => ['nullable', 'array'],
            'answer.sdp' => ['required_with:answer', 'string', 'max:32768'],
            'answer.type' => ['required_with:answer', 'string', 'in:answer'],
        ]);

        try {
            $call = $this->callService->accept(
                $callSession,
                $userId,
                isset($validated['answer']) && is_array($validated['answer']) ? $validated['answer'] : null
            );

            return $this->ok([
                'call' => CallSessionResource::make($call)->resolve($request),
                'ice_servers' => $this->iceConfigProvider->forParticipant($userId),
            ], 'Panggilan suara diterima.');
        } catch (CallStateException $e) {
            return $this->fail($e->getMessage(), $e->errorCode, $e->statusCode);
        }
    }

    public function reject(Request $request, CallSession $callSession): JsonResponse
    {
        Gate::forUser($request->user())->authorize('reject', $callSession);
        $userId = (int) $request->user()->getAuthIdentifier();
        $reason = $request->filled('reason') ? substr((string) $request->input('reason'), 0, 64) : null;

        try {
            $call = $this->callService->reject($callSession, $userId, $reason);

            return $this->ok([
                'call' => CallSessionResource::make($call)->resolve($request),
                'ice_servers' => [],
            ], 'Panggilan suara ditolak.');
        } catch (CallStateException $e) {
            return $this->fail($e->getMessage(), $e->errorCode, $e->statusCode);
        }
    }

    public function end(Request $request, CallSession $callSession): JsonResponse
    {
        Gate::forUser($request->user())->authorize('end', $callSession);
        $userId = (int) $request->user()->getAuthIdentifier();

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:ended,missed,failed'],
            'reason' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $call = $this->callService->end(
                $callSession,
                $userId,
                $validated['status'] ?? null,
                $validated['reason'] ?? null
            );

            return $this->ok([
                'call' => CallSessionResource::make($call)->resolve($request),
                'ice_servers' => [],
            ], 'Panggilan suara diakhiri.');
        } catch (CallStateException $e) {
            return $this->fail($e->getMessage(), $e->errorCode, $e->statusCode);
        }
    }

    public function storeSignal(StoreCallSignalRequest $request, CallSession $callSession): JsonResponse
    {
        Gate::forUser($request->user())->authorize('signal', $callSession);
        $userId = (int) $request->user()->getAuthIdentifier();
        $validated = $request->validated();

        try {
            $signal = $this->callService->storeSignal(
                $callSession,
                $userId,
                (string) $validated['signal_type'],
                (array) $validated['payload']
            );

            return $this->ok([
                'id' => (int) $signal->id,
                'call_session_id' => (int) $signal->call_session_id,
                'signal_type' => $signal->signal_type,
                'created_at' => $signal->created_at?->toIso8601String(),
            ], 'Sinyal WebRTC dikirim.', 201);
        } catch (CallStateException $e) {
            return $this->fail($e->getMessage(), $e->errorCode, $e->statusCode);
        }
    }

    public function signals(Request $request, CallSession $callSession): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $callSession);
        $userId = (int) $request->user()->getAuthIdentifier();
        $afterId = max(0, (int) $request->query('after_id', 0));

        $signals = $this->callService->listSignalsForUser($callSession, $userId, $afterId);
        $freshCall = $callSession->fresh(['conversation', 'caller.courier', 'receiver.courier']) ?? $callSession;

        return $this->ok([
            'call' => CallSessionResource::make($freshCall)->resolve($request),
            'signals' => $signals->map(fn (CallSignal $s): array => [
                'id' => (int) $s->id,
                'call_session_id' => (int) $s->call_session_id,
                'sender_id' => (int) $s->sender_id,
                'receiver_id' => (int) $s->receiver_id,
                'signal_type' => $s->signal_type,
                'payload' => $s->payload,
                'created_at' => $s->created_at?->toIso8601String(),
            ])->all(),
        ], 'Sinyal WebRTC dimuat.');
    }
}
