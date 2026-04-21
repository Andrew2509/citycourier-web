<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DropPoint;
use Illuminate\Http\Request;

class DropPointController extends Controller
{
    /**
     * Display a listing of active drop points.
     */
    public function index()
    {
        $dropPoints = DropPoint::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $dropPoints
        ]);
    }
}
