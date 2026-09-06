<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ApkUploadController extends Controller
{
    /**
     * Upload APK from Flutter
     * POST /api/upload-apk
     * Header: X-Secret-Key: your-secret-key
     */
    public function upload(Request $request)
    {
        // Simple secret key for security (change this in production)
        $secretKey = $request->header('X-Secret-Key');
        if ($secretKey !== env('APK_UPLOAD_SECRET', 'citycourier-secret-2024')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'apk_file' => 'required|file|mimes:apk|max:204800', // 200MB max
            'version' => 'nullable|string|max:50',
            'release_notes' => 'nullable|string',
        ]);

        $file = $request->file('apk_file');
        $version = $request->input('version', date('Y.m.d'));
        $filename = 'citycourier-v' . $version . '-' . time() . '.apk';
        
        // Store in storage/app/public/downloads
        $path = $file->storeAs('downloads', $filename, 'public');

        // Deactivate previous versions
        AppDownload::where('is_active', true)->update(['is_active' => false]);

        // Create new record
        $download = AppDownload::create([
            'version' => $version,
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'file_path' => $path,
            'release_notes' => $request->input('release_notes', 'Auto-uploaded from Flutter'),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'APK v' . $version . ' uploaded successfully!',
            'data' => [
                'id' => $download->id,
                'version' => $download->version,
                'size' => round($download->file_size / 1024 / 1024, 1) . ' MB',
                'download_url' => url('/download/app'),
            ]
        ]);
    }
}
