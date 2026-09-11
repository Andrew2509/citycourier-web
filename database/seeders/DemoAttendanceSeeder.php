<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Courier;
use App\Models\CourierLocation;
use App\Models\DropPoint;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class DemoAttendanceSeeder extends Seeder
{
    /**
     * Seed demo data for the Presensi & Pelacakan Kurir (Ci-Work Attendance) page.
     *
     * Idempotent:
     * - Ensures today's check-in record for the demo courier at the Drop Point Bratang.
     * - Seeds a fresh live telemetry point (speed/battery/battery accuracy) for the map HUD.
     * - Ensures the Drop Point Bratang hub exists and attendance settings are present.
     */
    public function run(): void
    {
        $courier = Courier::whereHas('user', fn ($q) => $q->where('email', 'masbrightly@gmail.com'))->first();

        if (! $courier) {
            $this->command->warn('Demo courier (masbrightly@gmail.com) not found. Skipping attendance seed.');

            return;
        }

        $dropPoint = DropPoint::firstOrCreate(
            ['name' => 'Drop Point Bratang'],
            [
                'address'    => 'Jl. Bratang Gede No. 42, Ngagelrejo, Surabaya',
                'phone'      => '031-5022345',
                'schedule'   => '08:00 - 20:00 WIB',
                'rating'     => 4.8,
                'latitude'   => '-7.2912400',
                'longitude'  => '112.7592100',
                'is_active'  => true,
            ]
        );

        Setting::set('attendance_drop_point', $dropPoint->name, 'attendance');
        Setting::set('attendance_shift_cutoff', '08:00', 'attendance');

        // Today's check-in (demo time 07:42 WIB).
        $existingToday = Attendance::where('courier_id', $courier->id)
            ->whereDate('check_in_at', today())
            ->first();

        $data = [
            'courier_id'       => $courier->id,
            'check_in_at'      => today()->setTime(7, 42),
            'check_out_at'     => null,
            'status'           => 'online',
            'drop_point_name'  => $dropPoint->name,
            'latitude'         => (string) $dropPoint->latitude,
            'longitude'        => (string) $dropPoint->longitude,
            'device'           => 'Aplikasi v1.0.0',
            'note'             => 'Check-in shift pagi dari Drop Point Bratang',
        ];

        if ($existingToday) {
            $existingToday->update($data);
        } else {
            Attendance::create($data);
        }

        // Live telemetry point (map HUD) — freshest one always now.
        $latest = CourierLocation::where('courier_id', $courier->id)
            ->latest('recorded_at')
            ->first();

        $locationData = [
            'courier_id'      => $courier->id,
            'shipment_id'     => null,
            'latitude'        => (string) $dropPoint->latitude,
            'longitude'       => (string) $dropPoint->longitude,
            'accuracy'        => 4.00,
            'speed_kmh'       => 32.0,
            'battery_percent' => 84,
            'recorded_at'     => now()->subSeconds(12),
        ];

        if ($latest) {
            $latest->update($locationData);
        } else {
            CourierLocation::create($locationData);
        }

        $this->command->info("Demo attendance seeded for courier id {$courier->id} at {$dropPoint->name}.");
    }
}