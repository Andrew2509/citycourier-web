<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Events\CallSessionUpdatedBroadcast;
use App\Modules\Chat\Events\CallSignalBroadcast;
use App\Modules\Chat\Events\IncomingCallBroadcast;
use App\Modules\Chat\Exceptions\CallStateException;
use App\Modules\Chat\Jobs\SendCallPushNotificationJob;
use App\Modules\Chat\Models\CallSession;
use App\Modules\Chat\Models\CallSignal;
use App\Modules\Chat\Models\Conversation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CallSessionService
{
    public function __construct(
        private readonly ChatConversationService $conversationService,
        private readonly OrderParticipantResolver $participantResolver,
    ) {
    }

    /**
     * Transitions expired ringing calls to `missed` and overly old accepted calls to `ended`,
     * and cleans up obsolete signaling rows.
     */
    public function cleanupStaleCalls(?int $userId = null): void
    {
        $now = now();

        $expiredRingingQuery = CallSession::query()
            ->where('status', CallSession::STATUS_RINGING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now);

        if ($userId !== null) {
            $expiredRingingQuery->where(function ($q) use ($userId): void {
                $q->where('caller_id', $userId)->orWhere('receiver_id', $userId);
            });
        }

        $expiredRinging = $expiredRingingQuery->get();
        foreach ($expiredRinging as $call) {
            $updated = DB::transaction(function () use ($call, $now): ?CallSession {
                /** @var CallSession|null $locked */
                $locked = CallSession::query()->whereKey($call->id)->lockForUpdate()->first();
                if ($locked === null || $locked->status !== CallSession::STATUS_RINGING) {
                    return null;
                }

                $locked->forceFill([
                    'status' => CallSession::STATUS_MISSED,
                    'end_reason' => 'missed_timeout',
                    'ended_at' => $locked->expires_at ?? $now,
                    'duration' => 0,
                ])->save();

                CallSignal::query()->where('call_session_id', $locked->id)->delete();

                return $locked;
            });

            if ($updated !== null) {
                $updated->loadMissing(['conversation', 'caller.courier', 'receiver.courier']);
                CallSessionUpdatedBroadcast::dispatch($updated);
                $this->dispatchCallPush($updated->id, 'call_cancelled');
            }
        }

        $maxAcceptedCutoff = $now->copy()->subSeconds(max(300, (int) config('voip.max_call_seconds', 14400)));
        $staleAcceptedQuery = CallSession::query()
            ->where('status', CallSession::STATUS_ACCEPTED)
            ->where(function ($q) use ($maxAcceptedCutoff): void {
                $q->where('answered_at', '<=', $maxAcceptedCutoff)
                    ->orWhere(function ($q2) use ($maxAcceptedCutoff): void {
                        $q2->whereNull('answered_at')->where('created_at', '<=', $maxAcceptedCutoff);
                    });
            });

        if ($userId !== null) {
            $staleAcceptedQuery->where(function ($q) use ($userId): void {
                $q->where('caller_id', $userId)->orWhere('receiver_id', $userId);
            });
        }

        foreach ($staleAcceptedQuery->get() as $stale) {
            $updated = DB::transaction(function () use ($stale, $now): ?CallSession {
                /** @var CallSession|null $locked */
                $locked = CallSession::query()->whereKey($stale->id)->lockForUpdate()->first();
                if ($locked === null || $locked->status !== CallSession::STATUS_ACCEPTED) {
                    return null;
                }

                $duration = $locked->answered_at !== null
                    ? max(0, (int) $locked->answered_at->diffInSeconds($now))
                    : 0;

                $locked->forceFill([
                    'status' => CallSession::STATUS_ENDED,
                    'end_reason' => 'stale_cleanup',
                    'ended_at' => $now,
                    'duration' => $duration,
                ])->save();

                CallSignal::query()->where('call_session_id', $locked->id)->delete();

                return $locked;
            });

            if ($updated !== null) {
                $updated->loadMissing(['conversation', 'caller.courier', 'receiver.courier']);
                CallSessionUpdatedBroadcast::dispatch($updated);
            }
        }
    }

    /**
     * Initiates a voice call session after validating order assignment and concurrency rules.
     *
     * @param  array{sdp?: string, type?: string}|null  $initialOffer
     */
    public function initiate(
        int $callerId,
        ?int $conversationId = null,
        ?string $orderIdentifier = null,
        ?array $initialOffer = null
    ): CallSession {
        if (! (bool) config('voip.enabled', true)) {
            throw new CallStateException(
                'Fitur panggilan suara saat ini sedang dinonaktifkan.',
                'VOIP_DISABLED',
                503
            );
        }

        if ($conversationId !== null) {
            $conversation = Conversation::query()->findOrFail($conversationId);
            if (! $conversation->isParticipant($callerId)) {
                throw new AuthorizationException('Anda tidak memiliki akses ke percakapan ini.');
            }
        } elseif ($orderIdentifier !== null && trim($orderIdentifier) !== '') {
            $conversation = $this->conversationService->findOrCreateForOrder($callerId, trim($orderIdentifier));
        } else {
            throw new CallStateException(
                'conversation_id atau order_identifier wajib disertakan.',
                'INVALID_TARGET',
                422
            );
        }

        $callSession = DB::transaction(function () use ($conversation, $callerId, $initialOffer): CallSession {
            /** @var Conversation $lockedConv */
            $lockedConv = Conversation::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedConv->isParticipant($callerId)) {
                throw new AuthorizationException('Anda tidak berhak memulai panggilan pada pesanan ini.');
            }

            if ($lockedConv->status !== Conversation::STATUS_ACTIVE) {
                throw new CallStateException(
                    'Percakapan pengiriman sudah ditutup sehingga panggilan tidak dapat dimulai.',
                    'CONVERSATION_CLOSED',
                    422
                );
            }

            if ($lockedConv->courier_user_id === null || (int) $lockedConv->courier_user_id <= 0) {
                throw new CallStateException(
                    'Kurir belum ditugaskan pada pesanan ini.',
                    'COURIER_NOT_ASSIGNED',
                    422
                );
            }

            $this->assertOrderAssignmentValid($lockedConv, $callerId);

            $receiverId = (int) $lockedConv->customer_user_id === $callerId
                ? (int) $lockedConv->courier_user_id
                : (int) $lockedConv->customer_user_id;

            if ($receiverId <= 0 || $receiverId === $callerId) {
                throw new CallStateException(
                    'Penerima panggilan tidak valid untuk pesanan ini.',
                    'INVALID_RECEIVER',
                    422
                );
            }

            $this->cleanupStaleCalls();

            $activeConflict = CallSession::query()
                ->whereIn('status', CallSession::ACTIVE_STATUSES)
                ->where(function ($q) use ($lockedConv, $callerId, $receiverId): void {
                    $q->where('conversation_id', $lockedConv->id)
                        ->orWhereIn('caller_id', [$callerId, $receiverId])
                        ->orWhereIn('receiver_id', [$callerId, $receiverId]);
                })
                ->lockForUpdate()
                ->first();

            if ($activeConflict !== null) {
                $sameConv = (int) $activeConflict->conversation_id === (int) $lockedConv->id;
                throw new CallStateException(
                    $sameConv
                        ? 'Masih ada sesi panggilan aktif pada percakapan ini.'
                        : 'Salah satu peserta sedang berada dalam panggilan lain.',
                    $sameConv ? 'CALL_ALREADY_ACTIVE' : 'RECIPIENT_BUSY',
                    409
                );
            }

            $now = now();
            $timeoutSeconds = max(15, (int) config('voip.ring_timeout_seconds', 45));

            $created = CallSession::create([
                'call_uuid' => Str::uuid()->toString(),
                'order_id' => $lockedConv->order_id,
                'conversation_id' => $lockedConv->id,
                'caller_id' => $callerId,
                'receiver_id' => $receiverId,
                'status' => CallSession::STATUS_RINGING,
                'started_at' => $now,
                'expires_at' => $now->copy()->addSeconds($timeoutSeconds),
            ]);

            if ($initialOffer !== null && ! empty($initialOffer['sdp'])) {
                CallSignal::create([
                    'call_session_id' => $created->id,
                    'sender_id' => $callerId,
                    'receiver_id' => $receiverId,
                    'signal_type' => CallSignal::TYPE_OFFER,
                    'payload' => [
                        'sdp' => (string) $initialOffer['sdp'],
                        'type' => (string) ($initialOffer['type'] ?? 'offer'),
                    ],
                    'created_at' => $now,
                ]);
            }

            return $created;
        });

        $callSession->loadMissing(['conversation', 'caller.courier', 'receiver.courier']);
        IncomingCallBroadcast::dispatch($callSession);
        $this->dispatchCallPush($callSession->id, 'incoming_call');

        return $callSession;
    }

    /**
     * Accepts a ringing incoming call for the designated receiver.
     *
     * @param  array{sdp?: string, type?: string}|null  $initialAnswer
     */
    public function accept(CallSession $callSession, int $actorId, ?array $initialAnswer = null): CallSession
    {
        $updated = DB::transaction(function () use ($callSession, $actorId, $initialAnswer): CallSession {
            /** @var CallSession $locked */
            $locked = CallSession::query()->whereKey($callSession->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->receiver_id !== $actorId) {
                throw new AuthorizationException('Hanya penerima panggilan yang dapat menjawab panggilan ini.');
            }

            if ($locked->status === CallSession::STATUS_RINGING && $locked->expires_at !== null && $locked->expires_at->isPast()) {
                $locked->forceFill([
                    'status' => CallSession::STATUS_MISSED,
                    'end_reason' => 'missed_timeout',
                    'ended_at' => $locked->expires_at,
                    'duration' => 0,
                ])->save();
                CallSignal::query()->where('call_session_id', $locked->id)->delete();

                throw new CallStateException('Panggilan sudah melewati batas waktu berdering.', 'CALL_EXPIRED', 410);
            }

            if ($locked->status !== CallSession::STATUS_RINGING) {
                throw new CallStateException(
                    'Panggilan sudah tidak dalam status berdering.',
                    'INVALID_CALL_STATE',
                    409
                );
            }

            $now = now();
            $locked->forceFill([
                'status' => CallSession::STATUS_ACCEPTED,
                'answered_at' => $now,
            ])->save();

            if ($initialAnswer !== null && ! empty($initialAnswer['sdp'])) {
                $signal = CallSignal::create([
                    'call_session_id' => $locked->id,
                    'sender_id' => $actorId,
                    'receiver_id' => (int) $locked->caller_id,
                    'signal_type' => CallSignal::TYPE_ANSWER,
                    'payload' => [
                        'sdp' => (string) $initialAnswer['sdp'],
                        'type' => (string) ($initialAnswer['type'] ?? 'answer'),
                    ],
                    'created_at' => $now,
                ]);
                CallSignalBroadcast::dispatch($signal);
            }

            return $locked;
        });

        $updated->loadMissing(['conversation', 'caller.courier', 'receiver.courier']);
        CallSessionUpdatedBroadcast::dispatch($updated);

        return $updated;
    }

    public function reject(CallSession $callSession, int $actorId, ?string $reason = null): CallSession
    {
        $updated = DB::transaction(function () use ($callSession, $actorId, $reason): CallSession {
            /** @var CallSession $locked */
            $locked = CallSession::query()->whereKey($callSession->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->receiver_id !== $actorId) {
                throw new AuthorizationException('Hanya penerima panggilan yang dapat menolak panggilan ini.');
            }

            if ($locked->status !== CallSession::STATUS_RINGING) {
                throw new CallStateException(
                    'Panggilan tidak lagi menunggu jawaban.',
                    'INVALID_CALL_STATE',
                    409
                );
            }

            $locked->forceFill([
                'status' => CallSession::STATUS_REJECTED,
                'end_reason' => $reason ?: 'rejected_by_receiver',
                'ended_at' => now(),
                'duration' => 0,
            ])->save();

            CallSignal::query()->where('call_session_id', $locked->id)->delete();

            return $locked;
        });

        $updated->loadMissing(['conversation', 'caller.courier', 'receiver.courier']);
        CallSessionUpdatedBroadcast::dispatch($updated);
        $this->dispatchCallPush($updated->id, 'call_cancelled');

        return $updated;
    }

    public function end(
        CallSession $callSession,
        int $actorId,
        ?string $requestedStatus = null,
        ?string $reason = null
    ): CallSession {
        $wasRinging = false;

        $updated = DB::transaction(function () use ($callSession, $actorId, $requestedStatus, $reason, &$wasRinging): CallSession {
            /** @var CallSession $locked */
            $locked = CallSession::query()->whereKey($callSession->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isParticipant($actorId)) {
                throw new AuthorizationException('Anda tidak berhak mengakhiri panggilan ini.');
            }

            if (! $locked->isActive()) {
                return $locked;
            }

            $wasRinging = $locked->status === CallSession::STATUS_RINGING;
            $now = now();

            $targetStatus = match ($requestedStatus) {
                CallSession::STATUS_MISSED => CallSession::STATUS_MISSED,
                CallSession::STATUS_FAILED => CallSession::STATUS_FAILED,
                CallSession::STATUS_ENDED => CallSession::STATUS_ENDED,
                default => $wasRinging ? CallSession::STATUS_MISSED : CallSession::STATUS_ENDED,
            };

            $duration = ($locked->answered_at !== null && $targetStatus === CallSession::STATUS_ENDED)
                ? max(0, (int) $locked->answered_at->diffInSeconds($now))
                : ($locked->answered_at !== null ? max(0, (int) $locked->answered_at->diffInSeconds($now)) : 0);

            $locked->forceFill([
                'status' => $targetStatus,
                'end_reason' => $reason ?: ($wasRinging ? 'cancelled_before_answer' : 'hangup'),
                'ended_at' => $now,
                'duration' => $duration,
            ])->save();

            CallSignal::query()->where('call_session_id', $locked->id)->delete();

            return $locked;
        });

        $updated->loadMissing(['conversation', 'caller.courier', 'receiver.courier']);
        CallSessionUpdatedBroadcast::dispatch($updated);
        if ($wasRinging) {
            $this->dispatchCallPush($updated->id, 'call_cancelled');
        }

        return $updated;
    }

    /**
     * Persists and broadcasts WebRTC SDP/ICE signaling metadata between authorized participants.
     *
     * @param  array<string, mixed>  $payload
     */
    public function storeSignal(
        CallSession $callSession,
        int $senderId,
        string $signalType,
        array $payload
    ): CallSignal {
        $this->cleanupStaleCalls($senderId);
        $fresh = $callSession->fresh() ?? $callSession;

        if (! $fresh->isParticipant($senderId)) {
            throw new AuthorizationException('Anda tidak berhak mengirim sinyal WebRTC pada panggilan ini.');
        }

        if (! $fresh->isActive()) {
            throw new CallStateException(
                'Sesi panggilan sudah berakhir sehingga sinyal WebRTC diabaikan.',
                'CALL_NOT_ACTIVE',
                409
            );
        }

        $receiverId = $fresh->counterpartyIdFor($senderId);
        if ($receiverId === null) {
            throw new AuthorizationException('Penerima sinyal tidak ditemukan.');
        }

        $signal = CallSignal::create([
            'call_session_id' => $fresh->id,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'signal_type' => $signalType,
            'payload' => $payload,
            'created_at' => now(),
        ]);

        CallSignalBroadcast::dispatch($signal);

        return $signal;
    }

    /**
     * @return Collection<int, CallSignal>
     */
    public function listSignalsForUser(CallSession $callSession, int $userId, int $afterId = 0): Collection
    {
        $this->cleanupStaleCalls($userId);

        if (! $callSession->isParticipant($userId)) {
            throw new AuthorizationException('Anda tidak memiliki akses ke sinyal panggilan ini.');
        }

        $query = CallSignal::query()
            ->where('call_session_id', $callSession->id)
            ->where('receiver_id', $userId)
            ->orderBy('id');

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        return $query->limit(100)->get();
    }

    public function getIncomingForUser(int $userId): ?CallSession
    {
        $this->cleanupStaleCalls($userId);

        return CallSession::query()
            ->where('receiver_id', $userId)
            ->where('status', CallSession::STATUS_RINGING)
            ->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with(['conversation', 'caller.courier', 'receiver.courier'])
            ->orderByDesc('id')
            ->first();
    }

    private function assertOrderAssignmentValid(Conversation $conversation, int $actorUserId): void
    {
        $identifier = $conversation->order_number
            ?? ($conversation->order_id !== null ? (string) $conversation->order_id : null)
            ?? ($conversation->shipment_id !== null ? (string) $conversation->shipment_id : null);

        if ($identifier === null) {
            return;
        }

        $resolved = $this->participantResolver->resolve($identifier, $actorUserId);
        if ($resolved === null) {
            return;
        }

        if (strtolower((string) ($resolved['status'] ?? '')) === 'cancelled') {
            throw new CallStateException(
                'Pesanan telah dibatalkan sehingga panggilan tidak dapat dilakukan.',
                'ORDER_CANCELLED',
                422
            );
        }

        if (
            (int) $resolved['customer_user_id'] !== (int) $conversation->customer_user_id
            || (
                $resolved['courier_user_id'] !== null
                && (int) $resolved['courier_user_id'] !== (int) $conversation->courier_user_id
            )
        ) {
            throw new AuthorizationException('Penugasan kurir atau pelanggan pada pesanan ini sudah berubah.');
        }
    }

    private function dispatchCallPush(int $callSessionId, string $eventType): void
    {
        if ((bool) config('chat.use_queue_worker', false) || config('queue.default') === 'sync') {
            SendCallPushNotificationJob::dispatch($callSessionId, $eventType);

            return;
        }

        SendCallPushNotificationJob::dispatchAfterResponse($callSessionId, $eventType);
    }
}
