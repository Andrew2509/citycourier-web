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
}
