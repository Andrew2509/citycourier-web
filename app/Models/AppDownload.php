<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppDownload extends Model
{
    protected $fillable = [
        'version',
        'filename',
        'original_filename',
        'file_size',
        'file_path',
        'release_notes',
        'is_active',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the active download
     */
    public static function getActive()
    {
        return static::where('is_active', true)->latest()->first();
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
