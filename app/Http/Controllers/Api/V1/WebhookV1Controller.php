<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Payment\Services\WebhookProcessorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookV1Controller extends Controller
{
    public function __construct(
        private readonly WebhookProcessorService $webhookProcessor
    ) {}

    public function xenditPayment(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'xendit', 'payment');
    }

    public function xenditPayout(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'xendit', 'payout');
    }

    public function doku(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'doku', 'payment');
    }

    private function handleWebhook(Request $request, string $provider, string $category): JsonResponse
    {
        $rawBody = $request->getContent();
        $payload = $request->all();
        if (empty($payload) && $rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $result = $this->webhookProcessor->receiveWebhook(
            $provider,
            $category,
            $request->headers->all(),
            $rawBody,
            $payload
        );

        if (! $result['signature_valid']) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_WEBHOOK_SIGNATURE',
                'message' => 'Webhook signature verification failed.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => $result['duplicate'] ? 'Duplicate webhook ignored cleanly.' : 'Webhook received and processed.',
            'data' => [
                'webhook_id' => $result['webhook']->id,
                'duplicate' => $result['duplicate'],
                'status' => $result['webhook']->fresh()->processing_status,
            ],
        ]);
    }
}
