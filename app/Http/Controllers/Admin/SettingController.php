<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Show the WhatsApp settings form.
     */
    public function whatsapp()
    {
        $settings = [
            'api_key' => Setting::get('orbitwa_api_key', env('ORBITWA_API_KEY')),
            'base_url' => Setting::get('orbitwa_base_url', env('ORBITWA_BASE_URL', 'https://orbitwaapi.site/api/v1')),
            'device_id' => Setting::get('orbitwa_device_id', env('ORBITWA_DEVICE_ID')),
        ];

        return view('admin.settings.whatsapp', compact('settings'));
    }

    /**
     * Update the WhatsApp settings.
     */
    public function updateWhatsapp(Request $request)
    {
        $request->validate([
            'orbitwa_api_key' => 'required|string',
            'orbitwa_base_url' => 'required|url',
            'orbitwa_device_id' => 'nullable|string',
        ]);

        Setting::set('orbitwa_api_key', $request->orbitwa_api_key, 'whatsapp');
        Setting::set('orbitwa_base_url', $request->orbitwa_base_url, 'whatsapp');
        Setting::set('orbitwa_device_id', $request->orbitwa_device_id, 'whatsapp');

        return redirect()->back()->with('success', 'Pengaturan WhatsApp berhasil diperbarui.');
    }

    /**
     * Test the WhatsApp connection.
     */
    public function testWhatsapp(Request $request, \App\Services\WhatsAppService $wa)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $response = $wa->sendMessage($request->phone, 'Test koneksi WhatsApp dari City Courier Admin Panel. Jika Anda menerima ini, konfigurasi OrbitWA sudah benar.');

        if ($response['success']) {
            return redirect()->back()->with('success', 'Pesan test berhasil dikirim ke ' . $request->phone);
        }

        return redirect()->back()->with('error', 'Gagal mengirim pesan: ' . ($response['message'] ?? 'Kesalahan tidak diketahui'));
    }

    /**
     * Show the RajaOngkir settings form.
     */
    public function rajaongkir()
    {
        $settings = [
            'api_key' => Setting::get('rajaongkir_api_key', env('RAJAONGKIR_API_KEY')),
            'account_type' => Setting::get('rajaongkir_account_type', env('RAJAONGKIR_ACCOUNT_TYPE', 'starter')),
            'provider' => Setting::get('rajaongkir_provider', 'rajaongkir'),
        ];

        return view('admin.settings.rajaongkir', compact('settings'));
    }

    /**
     * Update the RajaOngkir settings.
     */
    public function updateRajaongkir(Request $request)
    {
        $request->validate([
            'rajaongkir_api_key' => 'required|string',
            'rajaongkir_account_type' => 'required|in:starter,basic,pro',
            'rajaongkir_provider' => 'required|in:rajaongkir,komerce',
        ]);

        Setting::set('rajaongkir_api_key', $request->rajaongkir_api_key, 'rajaongkir');
        Setting::set('rajaongkir_account_type', $request->rajaongkir_account_type, 'rajaongkir');
        Setting::set('rajaongkir_provider', $request->rajaongkir_provider, 'rajaongkir');
        Setting::set('rajaongkir_sandbox', $request->has('rajaongkir_sandbox') ? 1 : 0, 'rajaongkir');

        return redirect()->back()->with('success', 'Pengaturan RajaOngkir berhasil diperbarui.');
    }

    /**
     * Test the RajaOngkir connection.
     */
    public function testRajaongkir(\App\Services\RajaOngkirService $service)
    {
        try {
            if ($service->getProvider() === 'komerce') {
                $response = $service->searchDestination('Jakarta');
            } else {
                $response = $service->getProvinces();
            }
            
            // Standard RajaOngkir logic
            if (isset($response['rajaongkir']['status']['code']) && $response['rajaongkir']['status']['code'] == 200) {
                $count = count($response['rajaongkir']['results']);
                return response()->json([
                    'success' => true,
                    'message' => "Koneksi Berhasil! Terdeteksi $count provinsi.",
                    'data' => array_slice($response['rajaongkir']['results'], 0, 5)
                ]);
            }

            // Komerce logic (New platform structure)
            $isKomerceSuccess = (isset($response['status']) && $response['status'] == true) || 
                               (isset($response['meta']['status']) && $response['meta']['status'] == 'success');

            if ($isKomerceSuccess) {
                $count = count($response['data']);
                return response()->json([
                    'success' => true,
                    'message' => "Koneksi Berhasil (Komerce)! Terdeteksi $count provinsi.",
                    'data' => array_slice($response['data'], 0, 5)
                ]);
            }

            // Handle Failures
            $errorMessage = 'Kesalahan tidak diketahui';
            $statusCode = 400;

            if (isset($response['meta']['message'])) {
                $errorMessage = $response['meta']['message'];
            } elseif (isset($response['message'])) {
                $errorMessage = $response['message'];
            } elseif (isset($response['rajaongkir']['status']['description'])) {
                $errorMessage = $response['rajaongkir']['status']['description'];
            } elseif (is_string($response)) {
                $errorMessage = $response;
            } else {
                $errorMessage = json_encode($response);
            }

            // If empty response or unexpected structure
            if (!$response || (isset($response['status']) && $response['status'] == false) || (isset($response['meta']['status']) && $response['meta']['status'] == 'failed')) {
                $errorMessage = $response['meta']['message'] ?? ($response['message'] ?? ($response['error'] ?? 'Response Kosong atau Tidak Valid'));
            }

            return response()->json([
                'success' => false,
                'message' => "Koneksi Gagal: " . $errorMessage,
                'raw' => $response // For debugging
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Koneksi Gagal (Exception): " . $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Komerce Payment API Settings
    // ─────────────────────────────────────────────────────────────

    /**
     * Tampilkan form pengaturan Komerce Payment API.
     */
    public function payment()
    {
        $settings = [
            'api_key'      => Setting::get('komerce_payment_api_key', env('KOMERCE_PAYMENT_API_KEY', '')),
            'env'          => Setting::get('komerce_payment_env', env('KOMERCE_PAYMENT_ENV', 'sandbox')),
            'callback_key' => Setting::get('komerce_payment_callback_key', env('KOMERCE_PAYMENT_CALLBACK_KEY', '')),
        ];

        return view('admin.settings.payment', compact('settings'));
    }

    /**
     * Simpan pengaturan Komerce Payment API.
     */
    public function updatePayment(Request $request)
    {
        $request->validate([
            'komerce_payment_api_key'      => 'required|string|min:10',
            'komerce_payment_env'          => 'required|in:sandbox,production',
            'komerce_payment_callback_key' => 'nullable|string',
        ]);

        Setting::set('komerce_payment_api_key',      $request->komerce_payment_api_key,      'payment');
        Setting::set('komerce_payment_env',          $request->komerce_payment_env,          'payment');
        Setting::set('komerce_payment_callback_key', $request->komerce_payment_callback_key ?? '', 'payment');

        return redirect()->back()->with('success', 'Pengaturan Komerce Payment berhasil diperbarui.');
    }

    /**
     * Test koneksi ke Komerce Payment API.
     */
    public function testPayment(\App\Services\KomercePaymentService $paymentService)
    {
        try {
            $result = $paymentService->getPaymentMethods();

            $code    = $result['meta']['code'] ?? 0;
            $message = $result['meta']['message'] ?? 'Tidak ada respons';

            if ($code === 200) {
                $count = count($result['data'] ?? []);
                return response()->json([
                    'success' => true,
                    'message' => "Koneksi Berhasil! Terdeteksi {$count} metode pembayaran.",
                    'data'    => $result['data'] ?? [],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => "Koneksi Gagal: {$message}",
                'raw'     => $result,
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Koneksi Gagal (Exception): ' . $e->getMessage(),
            ], 500);
        }
    }
}
