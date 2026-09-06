<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AppDownload;
use Illuminate\Support\Facades\File;

class SyncApk extends Command
{
    protected $signature = 'app:sync-apk {--path= : Path to APK file}';
    protected $description = 'Sync APK file to database for download';

    public function handle()
    {
        $apkPath = $this->option('path');
        
        // Default paths to check
        $flutterPath = env('FLUTTER_PROJECT_PATH', 'F:/Flutter/city_courierapp');
        
        $paths = [
            $apkPath,
            $flutterPath . '/build/app/outputs/flutter-apk/app-debug.apk',
            $flutterPath . '/build/app/outputs/flutter-apk/app-release.apk',
            $flutterPath . '/build/app/outputs/flutter-apk/app.apk',
        ];
        
        $foundPath = null;
        foreach ($paths as $path) {
            if ($path && file_exists($path)) {
                $foundPath = $path;
                break;
            }
        }
        
        if (!$foundPath) {
            $this->error('APK file not found!');
            $this->info('Searched in:');
            foreach ($paths as $path) {
                if ($path) {
                    $this->line('  - ' . $path);
                }
            }
            return 1;
        }
        
        $this->info("Found APK: {$foundPath}");
        
        // Get version from file
        $version = date('Y.m.d');
        $filename = 'citycourier-v' . $version . '.apk';
        
        // Ensure storage directory exists
        $storageDir = storage_path('app/public/downloads');
        if (!File::isDirectory($storageDir)) {
            File::makeDirectory($storageDir, 0755, true);
        }
        
        // Copy to storage
        $destPath = $storageDir . '/' . $filename;
        File::copy($foundPath, $destPath);
        
        $this->info("Copied to: {$destPath}");
        
        // Deactivate previous versions
        AppDownload::where('is_active', true)->update(['is_active' => false]);
        
        // Create database record
        $download = AppDownload::create([
            'version' => $version,
            'filename' => $filename,
            'original_filename' => basename($foundPath),
            'file_size' => filesize($foundPath),
            'file_path' => 'downloads/' . $filename,
            'release_notes' => 'Auto-synced from flutter build',
            'is_active' => true,
        ]);
        
        $this->info("APK synced to database successfully!");
        $this->info("Version: v{$version}");
        $this->info("Size: " . round(filesize($foundPath) / 1024 / 1024, 1) . " MB");
        
        return 0;
    }
}
