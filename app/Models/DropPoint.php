<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DropPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'address',
        'city',
        'province',
        'description',
        'landmark',
        'phone',
        'pic_name',
        'schedule',
        'open_days',
        'rating',
        'radius_m',
        'capacity_pct',
        'latitude',
        'longitude',
        'is_active',
        'status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'rating' => 'decimal:2',
        'radius_m' => 'integer',
        'capacity_pct' => 'integer',
        'status' => 'string',
        'type' => 'string',
    ];
}
