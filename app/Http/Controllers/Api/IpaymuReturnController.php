<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman kembali dari iPaymu Redirect Payment (publik).
 *
 * iPaymu mengarahkan browser pelanggan ke returnUrl (pembayaran selesai)
 * atau cancelUrl (dibatalkan) setelah hosted checkout. Halaman ini hanya
 * INFORMASI: status pembayaran yang mengikat tetap ditentukan webhook
 * ber-signature + inquiry resmi (lihat iPaymuWebhookController), bukan
 * parameter URL — parameter URL bisa dipalsukan siapa pun.
 */
class IpaymuReturnController extends Controller
{
    /** GET /api/ipaymu/return — pelanggan selesai di halaman iPaymu. */
    public function completed(Request $request): View
    {
        return $this->page($request, false);
    }

    /** GET /api/ipaymu/cancel — pelanggan membatalkan / kembali. */
    public function cancelled(Request $request): View
    {
        return $this->page($request, true);
    }

    private function page(Request $request, bool $cancelled): View
    {
        $reference = (string) (
            $request->query('referenceId')
            ?? $request->query('reference_id')
            ?? $request->query('reference')
            ?? ''
        );

        $payment = $reference !== ''
            ? Payment::where('metadata->ipaymu_reference_id', $reference)->first()
            : null;

        return view('ipaymu.return', [
            'cancelled' => $cancelled,
            'reference' => $reference,
            'payment'   => $payment,
        ]);
    }
}
