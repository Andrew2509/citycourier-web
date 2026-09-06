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
     * Upload new APK
     */
    public function store(Request $request)
    {
        $request->validate([
            'apk_file' => 'required|file|mimes:apk|max:204800', // 200MB max
            'version' => 'required|string|max:50',
            'release_notes' => 'nullable|string',
        ]);

        $file = $request->file('apk_file');
        $filename = 'citycourier-v' . $request->version . '-' . time() . '.apk';
        
        // Store in storage/app/public/downloads
        $path = $file->storeAs('downloads', $filename, 'public');

        // Deactivate previous versions
        AppDownload::where('is_active', true)->update(['is_active' => false]);

        // Create new record
        $download = AppDownload::create([
            'version' => $request->version,
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'file_path' => $path,
            'release_notes' => $request->release_notes,
            'is_active' => true,
        ]);

        return redirect()->route('admin.app-download')->with('success', 'APK v' . $request->version . ' berhasil diupload!');
    }

    /**
     * Download the APK - optimized for large files
     */
    public function download()
    {
        $download = AppDownload::getActive();
        
        if (!$download) {
            abort(404, 'File APK tidak ditemukan');
        }

        $fullPath = storage_path('app/public/' . $download->file_path);
        
        if (!file_exists($fullPath)) {
            abort(404, 'File APK tidak ditemukan di server');
        }

        $fileSize = filesize($fullPath);
        
        // Set headers for large file download
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="CityCourier.apk"');
        header('Content-Length: ' . $fileSize);
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-cache, must-revalidate');
        
        // Enable output buffering off for streaming
        @ini_set('output_buffering', 'Off');
        @ini_set('zlib.output_compression', false);
        @ini_set('memory_limit', '256M');
        
        // Clear any previous output
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Stream the file in chunks
        $chunkSize = 1024 * 1024; // 1MB chunks
        $handle = fopen($fullPath, 'rb');
        
        if ($handle) {
            while (!feof($handle)) {
                $buffer = fread($handle, $chunkSize);
                echo $buffer;
                flush();
            }
            fclose($handle);
        }
        
        exit;
    }

    /**
     * Delete a download
     */
    public function destroy(AppDownload $appDownload)
    {
        // Delete file from storage
        $fullPath = storage_path('app/public/' . $appDownload->file_path);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

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
