<?php

namespace Database\Seeders;

use App\Models\DropPoint;
use Illuminate\Database\Seeder;

class DropPointDefaultSeeder extends Seeder
{
    public function run(): void
    {
        $hub = DropPoint::updateOrCreate(
            ['name' => 'Kantor Cabang CityCourier'],
            [
                'type' => 'hub',
                'address' => 'Jl. Garuda XI No. 52, Wedoro, Kec. Waru, Kabupaten Sidoarjo, Jawa Timur 61256',
                'city' => 'Sidoarjo',
                'province' => 'Jawa Timur',
                'description' => 'Kantor Cabang & Hub Sortir Utama CityCourier untuk Wilayah Surabaya Raya.',
                'landmark' => 'Dekat Bundaran Waru & Pasar Modern Sidoarjo',
                'phone' => '021-8123456',
                'pic_name' => 'Budi Santoso',
                'schedule' => '07:00 - 21:00 WIB',
                'open_days' => 'Buka Setiap Hari (7 Hari)',
                'rating' => 5.00,
                'radius_m' => 50,
                'capacity_pct' => 22,
                'latitude' => -7.3436,
                'longitude' => 112.7485,
                'is_active' => true,
                'status' => 'active',
            ]
        );

        $this->command?->info("Drop point '{$hub->name}' (id {$hub->id}) siap.");

        DropPoint::whereNull('type')->update(['type' => 'hub']);
        DropPoint::whereNull('status')->update(['status' => 'active']);
        DropPoint::whereNull('province')->update(['province' => 'Jawa Timur']);
        DropPoint::whereNull('radius_m')->update(['radius_m' => 50]);
    }
}