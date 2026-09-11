<?php

namespace Database\Seeders;

use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoCourierSeeder extends Seeder
{
    /**
     * Seed a demo courier profile so the Manajemen Armada Kurir page
     * (Daftar Kurir) has real backend data.
     *
     * Idempotent: skips if a Courier profile already exists for the user.
     */
    public function run(): void
    {
        $user = User::where('email', 'masbrightly@gmail.com')->first();

        if (! $user) {
            return;
        }

        if (Courier::where('user_id', $user->id)->exists()) {
            return;
        }

        $user->syncRoles(['courier']);

        $courier = Courier::create([
            'user_id' => $user->id,
            'nik' => '3174061808000002',
            'phone' => '081343323155',
            'address' => 'Jl. Tunjungan No. 88, Genteng, Surabaya',
            'city' => 'Surabaya',
            'vehicle_type' => 'motor',
            'vehicle_brand' => 'Honda Vario 160',
            'vehicle_year' => '2023',
            'vehicle_plate' => 'B 1234 ABC',
            'is_verified' => true,
            'is_active' => true,
            'latitude' => '-7.2574719',
            'longitude' => '112.7520883',
        ]);

        $orders = [
            [
                'customer_name' => 'Ahmad Fauzi',
                'customer_phone' => '089876543210',
                'pickup_address' => 'Jl. Sudirman No. 45, Jakarta Pusat',
                'delivery_address' => 'Jl. Gatot Subroto No. 12, Jakarta Selatan',
                'package_description' => 'Dokumen penting',
                'package_weight' => 0.5,
                'price' => 15000,
                'status' => 'delivered',
                'picked_up_at' => now()->subHours(4),
                'delivering_at' => now()->subHours(3),
                'delivered_at' => now()->subHours(2),
            ],
            [
                'customer_name' => 'Maya Sari',
                'customer_phone' => '089876543215',
                'pickup_address' => 'Jl. Menteng No. 10, Jakarta Pusat',
                'delivery_address' => 'Jl. BSD No. 44, Tangerang Selatan',
                'package_description' => 'Kado ulang tahun',
                'package_weight' => 0.8,
                'price' => 15000,
                'status' => 'delivered',
                'picked_up_at' => now()->subHours(2),
                'delivering_at' => now()->subHour(),
                'delivered_at' => now()->subMinutes(15),
            ],
        ];

        foreach ($orders as $order) {
            Order::create(array_merge($order, [
                'order_number' => Order::generateOrderNumber(),
                'courier_id' => $courier->id,
            ]));
        }

        $this->command->info("Demo courier seeded for {$user->email} (id: {$courier->id}).");
    }
}