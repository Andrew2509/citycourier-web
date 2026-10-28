<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function index()
    {
        // For now returning a static response or fetching from DB if you have a settings table.
        // Uncomment the line below if you have a 'settings' table:
        // $maxDistance = DB::table('settings')->where('key', 'courier_max_distance_meters')->value('value') ?? 2000;
        
        $maxDistance = 2000;

        return response()->json([
            'success' => true,
            'data' => [
                'courier_max_distance_meters' => (int) $maxDistance
            ]
        ]);
    }
}
