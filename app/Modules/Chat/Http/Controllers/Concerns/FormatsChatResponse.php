<?php

namespace App\Modules\Chat\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

trait FormatsChatResponse
{
    protected function ok(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function fail(
        string $message,
        string $errorCode = 'CHAT_ERROR',
        int $status = 422,
        array $errors = []
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'error_code' => $errorCode,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
