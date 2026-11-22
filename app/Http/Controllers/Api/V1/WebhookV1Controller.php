<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Payment\Services\WebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookV1Controller extends Controller
{
    public function __construct(
        private readonly WebhookHandler $webhookHandler
    ) {}

    public function xenditPayment(Request $request): JsonResponse
    {
        $result = $this->webhookHandler->handleXenditPayment(
            $request->headers->all(),
            (string) $request->getContent(),
            $request->all()
        );

        return $this->toJsonResponse($result, 'payment');
    }

    public function xenditPayout(Request $request): JsonResponse
    {
        $result = $this->webhookHandler->handleXenditPayout(
            $request->headers->all(),
            (string) $request->getContent(),
            $request->all()
        );

        return $this->toJsonResponse($result, 'payout');
    }

    /**
     * @param  array{webhook: mixed, duplicate: bool, signature_valid: bool}  $result
     */
    private function toJsonResponse(array $result, string $category): JsonResponse
    {
        if (! $result['signature_valid']) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_WEBHOOK_SIGNATURE',
                'message' => 'Signature/token webhook Xendit tidak valid.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => $result['duplicate']
                ? 'Webhook duplicate ignored idempotently.'
                : 'Webhook processed.',
            'data' => [
                'webhook_id' => $result['webhook']->id,
                'provider' => 'xendit',
                'category' => $category,
                'duplicate' => $result['duplicate'],
                'status' => $result['webhook']->processing_status,
            ],
        ]);
    }
}
