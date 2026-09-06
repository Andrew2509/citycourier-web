<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AppDownloadController extends Controller
{
    /**
     * Show upload form
     */
    public function index()
    {
        $downloads = AppDownload::latest()->get();
        $active = AppDownload::getActive();
        
        return view('admin.app-download', compact('downloads', 'active'));
    }

    /**
     * Add new APK version (Google Drive only)
     */
    public function store(Request $request)
    {
        $request->validate([
            'version' => 'required|string|max:50',
            'google_drive_url' => 'required|url',
            'file_size' => 'nullable|numeric',
            'release_notes' => 'nullable|string',
        ]);

        // Deactivate previous versions
        AppDownload::where('is_active', true)->update(['is_active' => false]);

        // Calculate file size
        $fileSize = 0;
        if ($request->file_size) {
            $fileSize = (int) ($request->file_size * 1024 * 1024); // Convert MB to bytes
        }

        // Create new record
        $download = AppDownload::create([
            'version' => $request->version,
            'filename' => 'citycourier-v' . $request->version . '.apk',
            'original_filename' => 'citycourier-v' . $request->version . '.apk',
            'file_size' => $fileSize,
            'file_path' => '',
            'google_drive_url' => $request->google_drive_url,
            'release_notes' => $request->release_notes,
            'is_active' => true,
        ]);

        return redirect()->route('admin.app-download')->with('success', 'APK v' . $request->version . ' berhasil ditambahkan!');
    }

    /**
     * Download the APK via Google Drive
     */
    public function download()
    {
        $download = AppDownload::getActive();
        
        if (!$download) {
            abort(404, 'File APK tidak ditemukan');
        }

        // Always redirect to Google Drive
        if ($download->google_drive_url) {
            return redirect($download->google_drive_url);
        }

        abort(404, 'Link download tidak tersedia');
    }

    /**
     * Delete a download
     */
    public function destroy(AppDownload $appDownload)
    {
        $appDownload->delete();
        return redirect()->route('admin.app-download')->with('success', 'APK berhasil dihapus!');
    }

    /**
     * Set active version
     */
    public function setActive(AppDownload $appDownload)
    {
        AppDownload::where('is_active', true)->update(['is_active' => false]);
        $appDownload->update(['is_active' => true]);

        return redirect()->route('admin.app-download')->with('success', 'Versi v' . $appDownload->version . ' diaktifkan!');
    }
}
