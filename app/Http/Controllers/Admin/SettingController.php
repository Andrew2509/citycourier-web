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
        ]);

        Setting::set('orbitwa_api_key', $request->orbitwa_api_key, 'whatsapp');
        Setting::set('orbitwa_base_url', $request->orbitwa_base_url, 'whatsapp');

        return redirect()->back()->with('success', 'Pengaturan WhatsApp berhasil diperbarui.');
    }
}
