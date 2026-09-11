<?php

namespace Database\Seeders;

use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DemoCourierSeeder extends Seeder
{
    /**
     * Seed a demo courier profile so the Manajemen Armada Kurir page
     * (Daftar Kurir) has real backend data, including document photos.
     *
     * Idempotent: updates the existing profile instead of duplicating,
     * and only seeds today's orders when none exist yet for the courier.
     */
    public function run(): void
    {
        $user = User::where('email', 'masbrightly@gmail.com')->first();

        if (! $user) {
            $this->command->warn('User masbrightly@gmail.com not found. Skipping demo courier.');

            return;
        }

        $user->syncRoles(['courier']);

        $courier = Courier::updateOrCreate(
            ['user_id' => $user->id],
            [
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
            ]
        );

        $photos = $this->generatePhotos($user->name, $courier->nik, $courier->vehicle_brand, $courier->vehicle_plate);
        $courier->update($photos);

        $hasOrderToday = Order::where('courier_id', $courier->id)
            ->whereDate('delivered_at', today())
            ->exists();

        if (! $hasOrderToday) {
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
        }

        $this->command->info("Demo courier seeded for {$user->email} (id: {$courier->id}).");
    }

    /**
     * Generate placeholder document images (profile, KTP, SIM, vehicle)
     * and return the Courier column => storage path map.
     */
    private function generatePhotos(string $name, string $nik, string $vehicleBrand, string $vehiclePlate): array
    {
        if (! extension_loaded('gd')) {
            return [];
        }

        $nameShort = $this->shortName($name);
        $nikMasked = $nik ? substr($nik, 0, 4) . '••••••••' . substr($nik, -4) : '-';

        $files = [
            'photo' => ['couriers/photos/demo-kurir-photo.jpg', fn () => $this->drawProfile($nameShort)],
            'id_card_photo' => [
                'couriers/documents/demo-kurir-ktp.jpg',
                fn () => $this->drawDocument('KARTU TANDA PENDUDUK', 'KTP', $name, 'NIK: ' . $nikMasked, 'Berlaku s/d: 18 Agu 2030', [157, 67, 0]),
            ],
            'driving_license_photo' => [
                'couriers/documents/demo-kurir-sim.jpg',
                fn () => $this->drawDocument('SURAT IZIN MENGEMUDI', 'SIM-C', $name, 'Berlaku s/d: 18 Agu 2030', 'Golongan: C', [13, 148, 136]),
            ],
            'skck_photo' => [
                'couriers/documents/demo-kurir-kendaraan.jpg',
                fn () => $this->drawDocument('FOTO KENDARAAN & PLAT', 'KENDARAAN', $vehicleBrand, 'Plat Nomor: ' . $vehiclePlate, 'Tampak depan sesuai berkas', [29, 78, 216]),
            ],
        ];

        $map = [];
        foreach ($files as $column => [$path, $draw]) {
            $bytes = $draw();
            if ($bytes !== null) {
                Storage::disk('public')->put($path, $bytes);
                $map[$column] = $path;
            }
        }

        return $map;
    }

    private function shortName(string $name): string
    {
        $parts = array_values(array_filter(explode(' ', trim($name))));
        if (count($parts) === 0) {
            return 'P';
        }

        return mb_strtoupper(substr($parts[0], 0, 1) . (count($parts) > 1 ? substr(end($parts), 0, 1) : ''));
    }

    private function drawProfile(string $initials): ?string
    {
        $size = 400;
        $img = imagecreatetruecolor($size, $size);

        // Soft background
        imagefilledrectangle($img, 0, 0, $size, $size, $this->rgb($img, [247, 243, 255]));

        // Circle ring + solid circle
        $ring = $this->rgb($img, [253, 186, 140]);
        imagefilledellipse($img, $size / 2, $size / 2, $size - 60, $size - 60, $ring);
        $solid = $this->rgb($img, [249, 115, 22]);
        imagefilledellipse($img, $size / 2, $size / 2, $size - 120, $size - 120, $solid);

        $this->text($img, $initials, $size / 2, $size / 2, 150, [255, 255, 255], true, true);

        return $this->bytes($img);
    }

    private function drawDocument(string $title, string $code, string $name, string $line1, string $line2, array $accent): ?string
    {
        $w = 640;
        $h = 400;
        $img = imagecreatetruecolor($w, $h);

        imagefilledrectangle($img, 0, 0, $w, $h, $this->rgb($img, [235, 237, 245]));

        // Card body
        imagefilledrectangle($img, 24, 24, $w - 24, $h - 24, $this->rgb($img, [255, 255, 255]));
        imagerectangle($img, 24, 24, $w - 24, $h - 24, $this->rgb($img, [210, 215, 230]));

        // Accent header band
        imagefilledrectangle($img, 25, 25, $w - 25, 100, $this->rgb($img, $accent));

        // Code badge
        imagefilledrectangle($img, 40, 40, 168, 84, $this->rgb($img, [255, 255, 255]));
        $this->text($img, $code, 104, 62, 22, $accent, true, true);

        // Title
        $this->text($img, $title, $w - 160, 46, 22, [255, 255, 255], true, true);

        // Detail lines
        $this->text($img, 'Name: ' . $name, 48, 140, 18, [40, 45, 60], false, false);
        $this->text($img, $line1, 48, 175, 18, [40, 45, 60], false, false);
        $this->text($img, $line2, 48, 210, 18, [40, 45, 60], false, false);

        // Footer
        $this->text($img, 'CityCourier - Dokumen Mitra Kurir', 48, 350, 14, [150, 155, 170], false, false);

        return $this->bytes($img);
    }

    private function bytes($img): string
    {
        ob_start();
        imagejpeg($img, null, 92);
        $data = ob_get_clean();
        imagedestroy($img);

        return $data;
    }

    private function rgb($img, array $c)
    {
        return imagecolorallocate($img, $c[0], $c[1], $c[2]);
    }

    /**
     * Draw text centered (or left) with embedded font when available,
     * falling back to GD's built-in bitmaps.
     */
    private function text($img, string $label, int $x, int $y, int $size, array $rgba, bool $bold, bool $center): void
    {
        $font = $this->fontPath($bold);
        $color = $this->rgb($img, $rgba);

        if ($font) {
            $box = imagettfbbox($size, 0, $font, $label);
            $tw = abs($box[2] - $box[0]);
            $th = abs($box[7] - $box[1]);
            $xx = $center ? $x - (int) ($tw / 2) : $x;
            $yy = $center ? $y + (int) ($th / 2) : $y;
            imagettftext($img, $size, 0, $xx, $yy, $color, $font, $label);
        } else {
            $w = strlen($label) * 9;
            $xx = $center ? $x - (int) ($w / 2) : $x;
            imagestring($img, 5, $xx, $y, $label, $color);
        }
    }

    private function fontPath(bool $bold): ?string
    {
        $paths = $bold
            ? ['C:/Windows/Fonts/arialbd.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf']
            : ['C:/Windows/Fonts/arial.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}