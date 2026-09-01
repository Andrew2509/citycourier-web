<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedAddress extends Model
{
    use HasFactory;    protected $fillable = [
        'user_id', 'name', 'phone', 'address',
        'province_id', 'province_name', 'city_id', 'city_name',
        'subdistrict_id', 'subdistrict_name', 'is_favorite',
        'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
            'latitude'    => 'decimal:8',
            'longitude'   => 'decimal:8',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
