<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Show the unified Providers page (all provider cards in one view).
     */
    public function providers()
    {
        $whatsapp = [
            'token'       => Setting::get('fonnte_token', env('FONNTE_TOKEN')),
            'send_number' => Setting::get('fonnte_send_number', env('FONNTE_SEND_NUMBER', '')),
            'provider'    => Setting::get('whatsapp_provider', env('WHATSAPP_PROVIDER', 'fonnte')),
        ];

        $rajaongkir = [
            'api_key'      => Setting::get('rajaongkir_api_key', env('RAJAONGKIR_API_KEY')),
            'account_type' => Setting::get('rajaongkir_account_type', env('RAJAONGKIR_ACCOUNT_TYPE', 'starter')),
            'provider'     => Setting::get('rajaongkir_provider', 'rajaongkir'),
        ];
        $rajaongkir_sandbox = Setting::get('rajaongkir_sandbox');

        $payment = [
            'api_key'      => Setting::get('komerce_payment_api_key', env('KOMERCE_PAYMENT_API_KEY', '')),
            'env'          => Setting::get('komerce_payment_env', env('KOMERCE_PAYMENT_ENV', 'sandbox')),
            'callback_key' => Setting::get('komerce_payment_callback_key', env('KOMERCE_PAYMENT_CALLBACK_KEY', '')),
        ];

        $mapProvider = Setting::get('map_provider', env('MAP_PROVIDER', 'osrm'));
        $map = [
            'provider' => $mapProvider,
            'base_url' => Setting::get('map_base_url', env('MAP_BASE_URL', \App\Services\MapService::defaultBaseUrl($mapProvider))),
            'api_key'  => Setting::get('map_api_key', env('MAP_API_KEY', '')),
        ];

        $dana = [
            'mode'          => Setting::get('dana_mode', 'mock'),
            'api_base_url'  => Setting::get('dana_api_base_url', 'https://api.sandbox.dana.id'),
            'client_id'     => Setting::get('dana_client_id', ''),
            'client_secret' => Setting::get('dana_client_secret', ''),
            'merchant_id'   => Setting::get('dana_merchant_id', ''),
            'public_key'    => Setting::get('dana_public_key', ''),
            'private_key'   => Setting::get('dana_private_key', ''),
            'callback_url'  => Setting::get('dana_callback_url', url('/api/courier/dana/callback')),
        ];

        $ovo = [
            'mode'          => Setting::get('ovo_mode', 'mock'),
            'api_base_url'  => Setting::get('ovo_api_base_url', 'https://api.ovo.id'),
            'client_id'     => Setting::get('ovo_client_id', ''),
            'client_secret' => Setting::get('ovo_client_secret', ''),
            'partner_id'    => Setting::get('ovo_partner_id', ''),
            'merchant_id'   => Setting::get('ovo_merchant_id', ''),
            'public_key'    => Setting::get('ovo_public_key', ''),
            'private_key'   => Setting::get('ovo_private_key', ''),
        ];

        $ipaymu = \App\Services\iPaymuService::credentials();
        $ipaymu['webhook_url'] = url('/api/ipaymu/webhook');

        return view('admin.settings.providers', compact('whatsapp', 'rajaongkir', 'rajaongkir_sandbox', 'payment', 'map', 'dana', 'ovo', 'ipaymu'));
    }

    // iPaymu (Payout/Disbursement) Settings
    // ─────────────────────────────────────────────────────────────

    /**
     * Show iPaymu settings form.
     * Nilai default diambil dari credentials() (Settings DB → fallback env).
     */
    public function ipaymu()
    {
        $settings = \App\Services\iPaymuService::credentials();
        $settings['webhook_url'] = url('/api/ipaymu/webhook');

        return view('admin.settings.ipaymu', compact('settings'));
    }

    /**
     * Update iPaymu settings (disimpan ke tabel Settings).
     */
    public function updateIpaymu(Request $request)
    {
        $request->validate([
            'ipaymu_base_url'    => 'required|url',
            'ipaymu_va'          => 'required|string|max:64',
            'ipaymu_api_key'     => 'required|string|min:8',
            'ipaymu_buyer_email' => 'nullable|email|max:150',
            'ipaymu_timeout'     => 'nullable|integer|min:5|max:120',
        ]);

        Setting::set('ipaymu_base_url', $request->ipaymu_base_url, 'ipaymu');
        Setting::set('ipaymu_va', $request->ipaymu_va, 'ipaymu');
        Setting::set('ipaymu_api_key', trim($request->ipaymu_api_key), 'ipaymu');
        Setting::set(
            'ipaymu_payout_enabled',
            $request->boolean('ipaymu_payout_enabled') ? '1' : '0',
            'ipaymu'
        );
        Setting::set('ipaymu_timeout', (string) ($request->ipaymu_timeout ?? 30), 'ipaymu');
        // Email nyata yang dipakai bila email pembeli tidak layak;
        // menghindari penolakan iPaymu "Suspicious buyer".
        Setting::set('ipaymu_buyer_email', trim((string) $request->input('ipaymu_buyer_email', '')), 'ipaymu');

        // merchant_id & secret bersifat sensitif: hanya ditimpa bila memang
        // dikirim, supaya simpan cepat tidak menghapus nilai yang sudah ada.
        foreach (['ipaymu_merchant_id', 'ipaymu_secret_key', 'ipaymu_webhook_secret'] as $sensitiveKey) {
            if ($request->has($sensitiveKey)) {
                Setting::set($sensitiveKey, trim((string) $request->input($sensitiveKey, '')), 'ipaymu');
            }
        }

        \App\Services\iPaymuService::flushCredentialsCache();

        return redirect()->back()->with('success', 'Pengaturan iPaymu berhasil diperbarui.');
    }

    /**
     * Test koneksi iPaymu via inquiry transaksi dummy.
     * Menerima kredensial lewat request agar admin bisa test SEBELUM menyimpan.
     */
    public function testIpaymu(Request $request)
    {
        try {
            $current = \App\Services\iPaymuService::credentials();

            $override = [
                'base_url'   => trim((string) ($request->input('ipaymu_base_url') ?: $current['base_url'])),
                'va'         => trim((string) ($request->input('ipaymu_va') ?: $current['va'])),
                'merchant_id' => trim((string) ($request->input('ipaymu_merchant_id') ?: $current['merchant_id'])),
                'api_key'    => trim((string) ($request->input('ipaymu_api_key') ?: $current['api_key'])),
                'secret_key' => $request->filled('ipaymu_secret_key')
                    ? trim((string) $request->input('ipaymu_secret_key'))
                    : $current['secret_key'],
                'timeout'    => (int) ($request->input('ipaymu_timeout') ?: $current['timeout']),
            ];

            if (empty($override['api_key']) || empty($override['va'] ?: $override['merchant_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'API Key dan VA iPaymu wajib diisi sebelum test koneksi.',
                ], 422);
            }

            $service = new \App\Services\iPaymuService($override);
            $result = $service->inquire('CITY-COURIER-TEST-CONNECTION-' . time());

            // interpretResponse() mengembalikan http_code terstruktur di level
            // hasil, dan raw = isi body provider (bukan envelope HTTP).
            $httpCode = (int) ($result['http_code'] ?? 0);
            $errorMessage = strtolower((string) ($result['error'] ?? ''));
            $rawBody = $result['raw'] ?? [];
            $providerMsg = strtolower((string) ($rawBody['Message'] ?? $rawBody['message']
                ?? $rawBody['Msg'] ?? $rawBody['msg']
                ?? $rawBody['error'] ?? $rawBody['Error']
                ?? $rawBody['StatusDesc'] ?? ''));

            // Respons bisnis "transaksi tidak ditemukan" tetap membuktikan:
            // server dijangkau, tanda tangan diterima, hanya id dummy yang
            // memang tidak ada.
            $isDataNotFound = str_contains($providerMsg, 'tidak ada')
                || str_contains($providerMsg, 'not found');
            $isRouteNotFound = str_contains($providerMsg, 'could not be found');

            // Respons bisnis "transaksi not found" (iPaymu balas HTTP 400 untuk
            // error bisnis) tetap membuktikan: server dijangkau, tanda tangan
            // DAN kredensial diterima — hanya id dummy yang memang tidak ada.
            // Respons bisnis "transaksi not found" (iPaymu balas HTTP 400 untuk
            // error bisnis) tetap membuktikan: server dijangkau, tanda tangan
            // DAN kredensial diterima — hanya id dummy yang memang tidak ada.
            if ($httpCode > 0 && $isDataNotFound && !$isRouteNotFound) {
                return response()->json([
                    'success' => true,
                    'message' => "Koneksi iPaymu Berhasil! Server {$override['base_url']} merespons, tanda tangan & kredensial API diterima "
                        . "(id transaksi dummy memang tidak ditemukan — ini normal dan justru membuktikan koneksi valid).",
                    'data'    => $result,
                ]);
            }

            // Server merespons dengan HTTP < 400 → request tersambung dan
            // tanda tangan diterima.
            if ($httpCode > 0 && $httpCode < 400) {
                return response()->json([
                    'success' => true,
                    'message' => "Koneksi iPaymu Berhasil! Server {$override['base_url']} merespons (HTTP {$httpCode}) dan tanda tangan API diterima."
                        . ($isDataNotFound ? ' (Transaksi dummy memang tidak ada — ini normal untuk tes koneksi.)' : ''),
                    'data'    => $result,
                ]);
            }

            // HTTP 404 rute → base URL / path endpoint keliru (bukan masalah
            // kredensial). Beri petunjuk yang bisa ditindaklanjuti langsung.
            if ($isRouteNotFound || $httpCode === 404) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint iPaymu tidak ditemukan (HTTP 404). Periksa API Base URL: harusnya hanya host, '
                        . "mis. https://sandbox.ipaymu.com — path /api/v2/... ditambahkan otomatis oleh backend. Diterima: {$override['base_url']}",
                ], 400);
            }

            // Bedakan error autentikasi dari penolakan biasa.
            $isAuthIssue = str_contains($errorMessage, 'sign') ||
                str_contains($errorMessage, 'api key') ||
                str_contains($errorMessage, 'apikey') ||
                str_contains($errorMessage, 'unauthorized') ||
                str_contains($errorMessage, 'auth') ||
                $httpCode === 401 || $httpCode === 403;

            return response()->json([
                'success' => false,
                'message' => $isAuthIssue
                    ? 'Server iPaymu menolak kredensial. Periksa API Key, VA, atau mode sandbox/production.'
                        . ($providerMsg !== '' ? ' Alasan server: ' . $providerMsg : '.')
                    : 'Koneksi Gagal: ' . ($result['error'] ?? 'Tidak dapat menghubungi server iPaymu.'),
                'data'    => $result,
            ], $isAuthIssue ? 401 : 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Koneksi Gagal (Exception): ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the WhatsApp settings form.
     */
    public function whatsapp()
    {
        $settings = [
            'token'       => Setting::get('fonnte_token', env('FONNTE_TOKEN')),
            'send_number' => Setting::get('fonnte_send_number', env('FONNTE_SEND_NUMBER', '')),
            'provider'    => Setting::get('whatsapp_provider', env('WHATSAPP_PROVIDER', 'fonnte')),
        ];

        return view('admin.settings.whatsapp', compact('settings'));
    }

    /**
     * Update the WhatsApp settings.
     */
    public function updateWhatsapp(Request $request)
    {
        $request->validate([
            'fonnte_token'       => 'required|string',
            'fonnte_send_number' => 'nullable|string',
            'whatsapp_provider'  => 'nullable|in:fonnte,orbitwa,mock,auto',
        ]);

        Setting::set('fonnte_token', $request->fonnte_token, 'whatsapp');
        Setting::set('fonnte_send_number', $request->fonnte_send_number ?? '', 'whatsapp');
        Setting::set('whatsapp_provider', $request->whatsapp_provider ?? 'fonnte', 'whatsapp');

        return redirect()->back()->with('success', 'Pengaturan WhatsApp berhasil diperbarui.')->with('provider', 'whatsapp');
    }

    /**
     * Test the WhatsApp connection.
     */
    public function testWhatsapp(Request $request, \App\Services\WhatsAppManager $whatsapp)
    {
        $request->validate([
            'phone'              => 'required|string',
            'token'              => 'nullable|string',
            'fonnte_send_number' => 'nullable|string',
        ]);

        try {
            $testToken = $request->input('token');
            $sendNumber = $request->input('fonnte_send_number');

            if (!empty($testToken)) {
                $service = new \App\Services\FonnteWhatsAppService(trim($testToken), $sendNumber);
                $response = $service->sendMessage($request->phone, 'Test koneksi WhatsApp dari City Courier Admin Panel. Jika Anda menerima ini, konfigurasi Fonnte sudah benar.');
            } else {
                $response = $whatsapp->sendMessage($request->phone, 'Test koneksi WhatsApp dari City Courier Admin Panel. Jika Anda menerima ini, konfigurasi Fonnte sudah benar.');
            }

            $isSuccess = $response['success'] ?? false;
            $msg = $isSuccess 
                ? ('Pesan test berhasil dikirim ke ' . $request->phone)
                : ('Gagal mengirim pesan: ' . ($response['message'] ?? 'Kesalahan tidak diketahui'));

            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => $isSuccess,
                    'message' => $msg,
                ], $isSuccess ? 200 : 400);
            }

            if ($isSuccess) {
                return redirect()->back()->with('success', $msg)->with('provider', 'whatsapp');
            }

            return redirect()->back()->with('error', $msg)->with('provider', 'whatsapp');
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exception: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'Exception: ' . $e->getMessage())->with('provider', 'whatsapp');
        }
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

    // ─────────────────────────────────────────────────────────────
    // Map Settings (Mapbox/Maplibre Proxy Settings)
    // ─────────────────────────────────────────────────────────────

    /**
     * Show map settings form.
     */
    public function map()
    {
        $provider = Setting::get('map_provider', env('MAP_PROVIDER', 'osrm'));
        $settings = [
            'provider' => $provider,
            'base_url' => Setting::get('map_base_url', env('MAP_BASE_URL', \App\Services\MapService::defaultBaseUrl($provider))),
            'api_key' => Setting::get('map_api_key', env('MAP_API_KEY', '')),
        ];

        return view('admin.settings.map', compact('settings'));
    }

    /**
     * Update map settings.
     */
    public function updateMap(Request $request)
    {
        $request->validate([
            'map_provider' => 'required|in:osrm,maplibre,mapbox,google',
            'map_base_url' => 'required|url',
            // OSRM publik tidak memerlukan API key.
            'map_api_key' => $request->input('map_provider') === 'osrm' ? 'nullable|string' : 'required|string|min:5',
        ]);

        Setting::set('map_provider', $request->map_provider, 'map');
        Setting::set('map_base_url', $request->map_base_url, 'map');
        Setting::set('map_api_key', $request->map_api_key ?? '', 'map');

        return redirect()->back()->with('success', 'Pengaturan Map Server berhasil diperbarui.');
    }

    /**
     * Test map connection with a simple search.
     */
    public function testMap(\App\Services\MapService $mapService)
    {
        try {
            // Test call to searchPOI with Surabaya coordinates
            $result = $mapService->searchPOI('Surabaya');

            if ($result['success']) {
                $features = $result['data']['features'] ?? [];
                $count = count($features);
                return response()->json([
                    'success' => true,
                    'message' => "Koneksi Berhasil! Ditemukan {$count} lokasi untuk pencarian 'Surabaya'.",
                    'data' => array_slice($features, 0, 5),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => "Koneksi Gagal: " . ($result['message'] ?? 'Kesalahan tidak diketahui'),
                'raw' => $result,
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Koneksi Gagal (Exception): ' . $e->getMessage(),
            ], 500);
        }
    }

    // DANA Provider Settings
    // ─────────────────────────────────────────────────────────────

    /**
     * Show DANA provider settings form.
     * Semua kredensial diambil dari tabel settings (DB) — tanpa fallback env.
     */
    public function dana()
    {
        $settings = [
            'mode'          => Setting::get('dana_mode', 'mock'),
            'api_base_url'  => Setting::get('dana_api_base_url', 'https://api.sandbox.dana.id'),
            'client_id'     => Setting::get('dana_client_id', ''),
            'client_secret' => Setting::get('dana_client_secret', ''),
            'merchant_id'   => Setting::get('dana_merchant_id', ''),
            'public_key'    => Setting::get('dana_public_key', ''),
            'private_key'   => Setting::get('dana_private_key', ''),
            'callback_url'  => Setting::get('dana_callback_url', url('/api/courier/dana/callback')),
        ];

        return view('admin.settings.dana', compact('settings'));
    }

    /**
     * Update DANA provider settings.
     */
    public function updateDana(Request $request)
    {
        $request->validate([
            'dana_mode'          => 'required|in:mock,sandbox,production',
            'dana_api_base_url'  => 'required|url',
            'dana_client_id'     => 'required|string|max:64',
            'dana_client_secret' => 'nullable|string',
            'dana_merchant_id'   => 'required|string|max:64',
            'dana_public_key'    => 'nullable|string',
            'dana_private_key'   => 'nullable|string',
            'dana_callback_url'  => 'required|url',
        ]);

        Setting::set('dana_mode', $request->dana_mode, 'dana');
        Setting::set('dana_api_base_url', $request->dana_api_base_url, 'dana');
        Setting::set('dana_client_id', $request->dana_client_id, 'dana');
        Setting::set('dana_merchant_id', $request->dana_merchant_id, 'dana');
        Setting::set('dana_callback_url', $request->dana_callback_url, 'dana');

        // Kredensial sensitif hanya ditimpa bila memang dikirim, supaya simpan
        // cepat dari halaman Providers tidak menghapus private key yang sudah ada.
        foreach (['dana_client_secret', 'dana_public_key', 'dana_private_key'] as $sensitiveKey) {
            if ($request->has($sensitiveKey)) {
                Setting::set($sensitiveKey, (string) $request->input($sensitiveKey, ''), 'dana');
            }
        }

        return redirect()->back()->with('success', 'Pengaturan Provider DANA berhasil diperbarui.');
    }

    /**
     * Test DANA provider connection (generate binding URL).
     */
    public function testDana()
    {
        try {
            // activeProvider() tidak menyimpan baris DB — hanya validasi kredensial
            // & membangun URL binding (khusus OfficialDanaProvider).
            $service = new \App\Services\DanaService();
            $provider = $service->activeProvider();
            $result = $provider->applyOTT(null);

            if ($result['success'] && !empty($result['redirect_url'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Koneksi DANA Berhasil! URL binding berhasil dibuat.',
                    'data'    => ['redirect_url' => $result['redirect_url']],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Koneksi Gagal: ' . ($result['error'] ?? 'Tidak dapat membuat URL binding.'),
                'detail'  => $result['exception'] ?? null,
                'data'    => $result,
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Koneksi Gagal (Exception): ' . $e->getMessage(),
            ], 500);
        }
    }

    // OVO SNAP Provider Settings
    // ─────────────────────────────────────────────────────────────

    /**
     * Show OVO SNAP provider settings form.
     */
    public function ovo()
    {
        $settings = [
            'mode'          => Setting::get('ovo_mode', 'mock'),
            'api_base_url'  => Setting::get('ovo_api_base_url', 'https://api.ovo.id'),
            'client_id'     => Setting::get('ovo_client_id', ''),
            'client_secret' => Setting::get('ovo_client_secret', ''),
            'partner_id'    => Setting::get('ovo_partner_id', ''),
            'merchant_id'   => Setting::get('ovo_merchant_id', ''),
            'public_key'    => Setting::get('ovo_public_key', ''),
            'private_key'   => Setting::get('ovo_private_key', ''),
        ];

        return view('admin.settings.ovo', compact('settings'));
    }

    /**
     * Update OVO provider settings.
     */
    public function updateOvo(Request $request)
    {
        $request->validate([
            'ovo_mode'         => 'required|in:mock,sandbox,production',
            'ovo_api_base_url' => 'required|url',
            'ovo_client_id'    => 'nullable|string|max:100',
            'ovo_client_secret'=> 'nullable|string',
            'ovo_partner_id'   => 'nullable|string|max:100',
            'ovo_merchant_id'  => 'nullable|string|max:100',
            'ovo_public_key'   => 'nullable|string',
            'ovo_private_key'  => 'nullable|string',
        ]);

        Setting::set('ovo_mode', $request->ovo_mode, 'ovo');
        Setting::set('ovo_api_base_url', $request->ovo_api_base_url, 'ovo');
        Setting::set('ovo_client_id', $request->ovo_client_id ?? '', 'ovo');
        Setting::set('ovo_client_secret', $request->ovo_client_secret ?? '', 'ovo');
        Setting::set('ovo_partner_id', $request->ovo_partner_id ?? '', 'ovo');
        Setting::set('ovo_merchant_id', $request->ovo_merchant_id ?? '', 'ovo');
        Setting::set('ovo_public_key', $request->ovo_public_key ?? '', 'ovo');
        Setting::set('ovo_private_key', $request->ovo_private_key ?? '', 'ovo');

        return redirect()->back()->with('success', 'Pengaturan Provider OVO SNAP berhasil diperbarui.');
    }

    /**
     * Test OVO provider connection (B2B Balance Inquiry).
     */
    public function testOvo()
    {
        try {
            $service = new \App\Services\OvoService();
            $provider = $service->activeProvider();
            $result = $provider->balanceInquiry();

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Koneksi OVO SNAP Berhasil! Saldo deposit mitra terverifikasi: Rp ' . number_format($result['balance'] ?? 0, 0, ',', '.'),
                    'data'    => $result,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Koneksi OVO Gagal: ' . ($result['error'] ?? 'Tidak dapat terhubung ke server OVO.'),
                'data'    => $result,
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Koneksi Gagal (Exception): ' . $e->getMessage(),
            ], 500);
        }
    }
}
