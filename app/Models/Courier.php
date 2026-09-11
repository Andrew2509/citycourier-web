<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Courier extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nik',
        'phone',
        'address',
        'city',
        'photo',
        'vehicle_type',
        'vehicle_brand',
        'vehicle_model',
        'vehicle_year',
        'vehicle_color',
        'vehicle_plate',
        'id_card_photo',
        'driving_license_photo',
        'skck_photo',
        'is_verified',
        'is_active',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    /**
     * Get the user that owns this courier profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the orders assigned to this courier.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the wallet belonging to this courier.
     */
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }
}
