<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Payout\Services\DestinationService;
use App\Modules\Wallet\Models\PayoutDestination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CustomerDestinationController extends Controller
{
    public function __construct(
        private readonly DestinationService $destinationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->destinationService->listDestinations($request->user(), 'customer');

        return response()->json([
            'success' => true,
            'message' => 'Daftar rekening/e-wallet tujuan berhasil dimuat.',
            'data' => [
                'items' => $items->map(fn (PayoutDestination $d) => $d->toApiArray()),
                'supported_channels' => $this->destinationService->supportedChannels(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'destination_type' => ['required', 'string', 'in:bank,ewallet'],
            'channel_code' => ['required', 'string', 'max:64'],
            'channel_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'min:6', 'max:40'],
            'account_holder_name' => ['required', 'string', 'min:2', 'max:150'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        try {
            $dest = $this->destinationService->addDestination($request->user(), 'customer', $validated);

            return response()->json([
                'success' => true,
                'message' => 'Rekening/e-wallet tujuan berhasil ditambahkan.',
                'data' => $dest->toApiArray(),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $destination = PayoutDestination::where('user_id', $request->user()->id)
            ->where('owner_type', 'customer')
            ->findOrFail($id);

        $validated = $request->validate([
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'account_holder_name' => ['nullable', 'string', 'min:2', 'max:150'],
        ]);

        $updated = $this->destinationService->updateDestination($destination, $request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Rekening/e-wallet tujuan berhasil diperbarui.',
            'data' => $updated->toApiArray(),
        ]);
    }

    public function inquire(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'destination_type' => ['required', 'string', 'in:bank,ewallet'],
            'channel_code' => ['required', 'string', 'max:64'],
            'account_number' => ['required', 'string', 'min:6', 'max:40'],
        ]);

        try {
            $result = $this->destinationService->inquireDestination($request->user(), $validated);

            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Hasil pengecekan rekening dimuat.',
                'data' => $result,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
