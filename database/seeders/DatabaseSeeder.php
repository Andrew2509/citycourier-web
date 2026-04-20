<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Courier;
use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Admin User
        $admin = User::create([
            'name' => 'Admin City Courier',
            'email' => 'admin@citycourier.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Create Demo Couriers
        $courierUsers = [
            ['name' => 'Budi Santoso', 'email' => 'budi@citycourier.com', 'phone' => '081234567890', 'vehicle_type' => 'motor', 'vehicle_plate' => 'B 1234 ABC', 'is_verified' => true, 'is_active' => true],
            ['name' => 'Siti Rahayu', 'email' => 'siti@citycourier.com', 'phone' => '081234567891', 'vehicle_type' => 'motor', 'vehicle_plate' => 'B 5678 DEF', 'is_verified' => true, 'is_active' => true],
            ['name' => 'Andi Wijaya', 'email' => 'andi@citycourier.com', 'phone' => '081234567892', 'vehicle_type' => 'mobil', 'vehicle_plate' => 'B 9012 GHI', 'is_verified' => true, 'is_active' => false],
            ['name' => 'Dewi Lestari', 'email' => 'dewi@citycourier.com', 'phone' => '081234567893', 'vehicle_type' => 'sepeda', 'vehicle_plate' => '-', 'is_verified' => false, 'is_active' => false],
            ['name' => 'Rudi Hartono', 'email' => 'rudi@citycourier.com', 'phone' => '081234567894', 'vehicle_type' => 'motor', 'vehicle_plate' => 'B 3456 JKL', 'is_verified' => false, 'is_active' => false],
        ];

        $couriers = [];
        foreach ($courierUsers as $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('password'),
                'role' => 'courier',
            ]);

            $couriers[] = Courier::create([
                'user_id' => $user->id,
                'phone' => $data['phone'],
                'vehicle_type' => $data['vehicle_type'],
                'vehicle_plate' => $data['vehicle_plate'],
                'is_verified' => $data['is_verified'],
                'is_active' => $data['is_active'],
            ]);
        }

        // Create Demo Orders
        $orders = [
            [
                'customer_name' => 'Ahmad Fauzi',
                'customer_phone' => '089876543210',
                'pickup_address' => 'Jl. Sudirman No. 45, Jakarta Pusat',
                'delivery_address' => 'Jl. Gatot Subroto No. 12, Jakarta Selatan',
                'package_description' => 'Dokumen penting',
                'package_weight' => 0.5,
                'price' => 25000,
                'status' => 'delivered',
                'courier_id' => $couriers[0]->id,
                'delivered_at' => now()->subHours(2),
            ],
            [
                'customer_name' => 'Linda Permata',
                'customer_phone' => '089876543211',
                'pickup_address' => 'Jl. Thamrin No. 88, Jakarta Pusat',
                'delivery_address' => 'Jl. Rasuna Said No. 33, Jakarta Selatan',
                'package_description' => 'Paket elektronik',
                'package_weight' => 2.0,
                'price' => 45000,
                'status' => 'delivering',
                'courier_id' => $couriers[1]->id,
            ],
            [
                'customer_name' => 'Hendra Gunawan',
                'customer_phone' => '089876543212',
                'pickup_address' => 'Jl. Kemang Raya No. 15, Jakarta Selatan',
                'delivery_address' => 'Jl. Pluit Karang No. 7, Jakarta Utara',
                'package_description' => 'Makanan & minuman',
                'package_weight' => 1.5,
                'price' => 35000,
                'status' => 'picking_up',
                'courier_id' => $couriers[0]->id,
            ],
            [
                'customer_name' => 'Rina Susanti',
                'customer_phone' => '089876543213',
                'pickup_address' => 'Jl. Mangga Dua No. 99, Jakarta Barat',
                'delivery_address' => 'Jl. Kelapa Gading No. 21, Jakarta Utara',
                'package_description' => 'Pakaian',
                'package_weight' => 1.0,
                'price' => 30000,
                'status' => 'pending',
            ],
            [
                'customer_name' => 'Yoga Pratama',
                'customer_phone' => '089876543214',
                'pickup_address' => 'Jl. Cikini Raya No. 55, Jakarta Pusat',
                'delivery_address' => 'Jl. Pondok Indah No. 8, Jakarta Selatan',
                'package_description' => 'Buku & alat tulis',
                'package_weight' => 3.0,
                'price' => 40000,
                'status' => 'assigned',
                'courier_id' => $couriers[1]->id,
            ],
            [
                'customer_name' => 'Maya Sari',
                'customer_phone' => '089876543215',
                'pickup_address' => 'Jl. Menteng No. 10, Jakarta Pusat',
                'delivery_address' => 'Jl. BSD No. 44, Tangerang Selatan',
                'package_description' => 'Kado ulang tahun',
                'package_weight' => 0.8,
                'price' => 55000,
                'status' => 'delivered',
                'courier_id' => $couriers[0]->id,
                'delivered_at' => now()->subDay(),
            ],
            [
                'customer_name' => 'Fajar Nugroho',
                'customer_phone' => '089876543216',
                'pickup_address' => 'Jl. Senayan No. 77, Jakarta Selatan',
                'delivery_address' => 'Jl. Depok Raya No. 1, Depok',
                'package_description' => 'Obat-obatan',
                'package_weight' => 0.3,
                'price' => 50000,
                'status' => 'delivered',
                'courier_id' => $couriers[1]->id,
                'delivered_at' => now()->subDays(2),
            ],
        ];

        foreach ($orders as $order) {
            Order::create(array_merge($order, [
                'order_number' => Order::generateOrderNumber(),
            ]));
        }
    }
}
