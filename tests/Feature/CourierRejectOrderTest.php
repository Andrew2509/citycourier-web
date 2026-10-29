<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderRejection;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CourierRejectOrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default'                    => 'mysql',
            'database.connections.mysql.host'     => '127.0.0.1',
            'database.connections.mysql.port'     => '3306',
            'database.connections.mysql.database' => 'city-courier-web',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
            'app.timezone'                        => 'Asia/Jakarta',
        ]);

        DB::purge('mysql');
        DB::connection('mysql')->beginTransaction();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::connection('mysql')->rollBack();
        parent::tearDown();
    }

    private function createCourier(string $suffix): array
    {
        $user = User::create([
            'name'     => "Kurir {$suffix}",
            'email'    => "kurir_rej_{$suffix}_" . uniqid() . '@example.com',
            'phone'    => '0812' . rand(10000000, 99999999),
            'password' => Hash::make('password123'),
            'role'     => 'courier',
        ]);

        $courier = Courier::create([
            'user_id'       => $user->id,
            'phone'         => $user->phone,
            'vehicle_type'  => 'motor',
            'vehicle_brand' => 'Honda Vario',
            'vehicle_plate' => 'L ' . rand(1000, 9999) . ' RJ',
            'is_verified'   => true,
            'is_active'     => true,
        ]);

        return [$user, $courier];
    }

    private function createPendingOrder(
        ?Courier $courier = null,
        string $orderStatus = 'pending',
        ?Carbon $scheduledAt = null,
        ?string $pickupTime = null
    ): array {
        $customer = User::create([
            'name'     => 'Pelanggan Uji Penolakan',
            'email'    => 'pelanggan_rej_' . uniqid() . '@example.com',
            'phone'    => '0813' . rand(10000000, 99999999),
            'password' => Hash::make('password123'),
            'role'     => 'user',
        ]);

        $shipmentStatus = match ($orderStatus) {
            'pending'    => 'confirmed',
            'assigned'   => 'assigned',
            'picking_up' => 'picking_up',
            'picked_up'  => 'picked_up',
            'delivering' => 'delivering',
            'delivered'  => 'delivered',
            'cancelled'  => 'cancelled',
            default      => 'confirmed',
        };

        $shipmentNumber = 'CC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

        $shipment = Shipment::create([
            'shipment_number'     => $shipmentNumber,
            'tracking_number'     => 'TRK-' . strtoupper(substr(uniqid(), -8)),
            'user_id'             => $customer->id,
            'customer_name'       => $customer->name,
            'customer_phone'      => $customer->phone,
            'sender_name'         => $customer->name,
            'sender_phone'        => $customer->phone,
            'sender_address'      => 'Jl. Tenggilis Mejoyo No. 1, Surabaya',
            'origin_name'         => 'Surabaya',
            'origin_id'           => 'SBY',
            'receiver_name'       => 'Penerima Uji',
            'receiver_phone'      => '081512345678',
            'receiver_address'    => 'Jl. Waru No. 20, Sidoarjo',
            'destination_name'    => 'Sidoarjo',
            'destination_id'      => 'SDA',
            'package_description' => 'Paket Elektronik',
            'package_weight'      => 2.0,
            'vehicle'             => 'motor',
            'courier_code'        => 'MOTOR',
            'courier_name'        => 'City Courier Motor',
            'courier_service'     => 'Instant',
            'shipping_cost'       => 15000,
            'total_cost'          => 15000,
            'payment_method'      => 'COD',
            'payment_status'      => 'paid',
            'status'              => $shipmentStatus,
            'scheduled_pickup'    => $scheduledAt !== null,
            'pickup_time'         => $pickupTime ?? ($scheduledAt ? $scheduledAt->format('h:i A') : null),
            'pickup_scheduled_at' => $scheduledAt ? $scheduledAt->format('Y-m-d H:i:s') : null,
        ]);

        $order = Order::where('order_number', $shipment->shipment_number)->first();
        if (!$order) {
            $order = Shipment::syncToOrder($shipment);
        }

        $order->update([
            'courier_id'          => $courier?->id,
            'status'              => $orderStatus,
            'scheduled_pickup'    => $scheduledAt !== null,
            'pickup_time'         => $pickupTime ?? ($scheduledAt ? $scheduledAt->format('h:i A') : null),
            'pickup_scheduled_at' => $scheduledAt ? $scheduledAt->format('Y-m-d H:i:s') : null,
        ]);

        return [$customer, $shipment->fresh(), $order->fresh()];
    }

    /**
     * Skenario 1 & 2: Kurir berhasil menolak pesanan yang belum diterima dan alasan penolakan tersimpan dengan benar.
     */
    public function test_courier_can_reject_unaccepted_order_and_reason_is_saved(): void
    {
        [$user, $courier] = $this->createCourier('A');
        [, $shipment, $order] = $this->createPendingOrder(null, 'pending');

        // Tanpa alasan -> ditolak 422
        $invalidResp = $this->actingAs($user, 'sanctum')->postJson('/api/orders/reject', [
            'order_id' => $order->id,
            'reason'   => '',
        ]);
        $invalidResp->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');

        // Alasan lainnya tanpa keterangan tambahan -> ditolak 422
        $otherNoNotesResp = $this->actingAs($user, 'sanctum')->postJson('/api/orders/reject', [
            'order_id' => $order->id,
            'reason'   => 'Alasan lainnya.',
            'notes'    => '   ',
        ]);
        $otherNoNotesResp->assertStatus(422)
            ->assertJsonPath('error_code', 'missing_other_notes');

        // Penolakan valid dengan alasan dan catatan tambahan
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/orders/reject', [
            'order_id' => $order->id,
            'reason'   => 'Alasan lainnya.',
            'notes'    => 'Sedang berada di luar jangkauan area penjemputan.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('already_rejected', false)
            ->assertJsonPath('title', 'Pesanan Telah Ditolak')
            ->assertJsonPath('message', 'Pesanan berhasil ditolak dan dikembalikan ke sistem penugasan.');

        $this->assertDatabaseHas('order_rejections', [
            'order_id'   => $order->id,
            'courier_id' => $courier->id,
            'reason'     => 'Alasan lainnya.',
            'notes'      => 'Sedang berada di luar jangkauan area penjemputan.',
        ]);
    }

    /**
     * Skenario 3 & 4: Pesanan tidak lagi muncul sebagai penawaran aktif bagi kurir yang menolak,
     * namun tetap tersedia untuk kurir lain dan proses penugasan berikutnya.
     */
    public function test_rejected_order_hidden_from_rejecting_courier_but_remains_available_for_other_couriers(): void
    {
        [$userA, $courierA] = $this->createCourier('Penolak');
        [$userB, $courierB] = $this->createCourier('Lain');
        [, $shipment, $order] = $this->createPendingOrder(null, 'pending');

        // Kurir A menolak pesanan
        $this->actingAs($userA, 'sanctum')->postJson('/api/orders/reject', [
            'order_id' => $order->id,
            'reason'   => 'Lokasi pickup terlalu jauh.',
        ])->assertStatus(200);

        // Kurir A memeriksa daftar available orders -> pesanan ini TIDAK muncul
        $availAResp = $this->actingAs($userA, 'sanctum')->getJson('/api/orders/available');
        $availAResp->assertStatus(200);
        $idsForA = collect($availAResp->json('data.data'))->pluck('id')->all();
        $this->assertNotContains($order->id, $idsForA);

        // Kurir B memeriksa daftar available orders -> pesanan ini TETAP muncul
        $availBResp = $this->actingAs($userB, 'sanctum')->getJson('/api/orders/available');
        $availBResp->assertStatus(200);
        $idsForB = collect($availBResp->json('data.data'))->pluck('id')->all();
        $this->assertContains($order->id, $idsForB);

        // Kurir B masih dapat menerima pesanan tersebut
        $acceptResp = $this->actingAs($userB, 'sanctum')->patchJson('/api/update-status-order', [
            'order_id' => $order->id,
            'status'   => 'assigned',
        ]);
        $acceptResp->assertStatus(200);

        $order->refresh();
        $this->assertSame('assigned', $order->status);
        $this->assertSame($courierB->id, (int) $order->courier_id);
    }

    /**
     * Skenario 5: Penolakan tidak membatalkan pesanan pelanggan.
     */
    public function test_rejection_does_not_cancel_customer_shipment(): void
    {
        [$user, $courier] = $this->createCourier('NoCancel');
        [, $shipment, $order] = $this->createPendingOrder(null, 'pending');

        $this->actingAs($user, 'sanctum')->postJson('/api/orders/reject', [
            'order_id' => $order->id,
            'reason'   => 'Kapasitas barang tidak sesuai.',
        ])->assertStatus(200);

        $order->refresh();
        $shipment->refresh();

        $this->assertSame('pending', $order->status);
        $this->assertNull($order->cancelled_at);
        $this->assertSame('confirmed', $shipment->status);
        $this->assertNull($shipment->cancelled_at);
    }

    /**
     * Skenario 6: Kurir tidak dapat menolak pesanan milik kurir lain atau penawaran yang sudah tidak aktif.
     */
    public function test_courier_cannot_reject_order_taken_by_another_courier(): void
    {
        [$userA, $courierA] = $this->createCourier('Owner');
        [$userB, $courierB] = $this->createCourier('Intruder');
        [, $shipment, $order] = $this->createPendingOrder($courierA, 'assigned');

        $response = $this->actingAs($userB, 'sanctum')->postJson('/api/orders/reject', [
            'order_id' => $order->id,
            'reason'   => 'Sedang menangani pesanan lain.',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'already_taken_by_other_courier');

        $order->refresh();
        $this->assertSame('assigned', $order->status);
        $this->assertSame($courierA->id, (int) $order->courier_id);
    }

    /**
     * Skenario 7: Kurir tidak dapat menggunakan tombol/endpoint Tolak Pesanan setelah menerima pesanan.
     */
    public function test_courier_cannot_use_reject_endpoint_after_accepting_order(): void
    {
        [$user, $courier] = $this->createCourier('Accepted');
        [, $shipment, $order] = $this->createPendingOrder($courier, 'assigned');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/orders/reject', [
            'order_id' => $order->id,
            'reason'   => 'Kendaraan tidak sesuai atau sedang bermasalah.',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'already_accepted_use_cancel_assignment');

        $order->refresh();
        $this->assertSame('assigned', $order->status);
        $this->assertSame($courier->id, (int) $order->courier_id);
    }

    /**
     * Skenario 8: Penolakan tidak mengubah jadwal pickup dan tidak dibatasi aturan 2 jam pelanggan.
     */
    public function test_rejection_preserves_pickup_schedule_and_works_within_two_hours_of_pickup(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 14:15:00', 'Asia/Jakarta'));

        [$user, $courier] = $this->createCourier('Sched');
        $scheduledAt = Carbon::parse('2026-10-30 15:00:00', 'Asia/Jakarta'); // Tersisa 45 menit (<= 2 jam)
        [, $shipment, $order] = $this->createPendingOrder(null, 'pending', $scheduledAt, '03:00 PM');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/orders/reject', [
            'order_id' => $order->id,
            'reason'   => 'Tidak tersedia pada jadwal pickup.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $order->refresh();
        $shipment->refresh();

        $this->assertSame('2026-10-30 15:00:00', $order->getRawOriginal('pickup_scheduled_at'));
        $this->assertSame('03:00 PM', $order->pickup_time);
        $this->assertSame('2026-10-30 15:00:00', $shipment->getRawOriginal('pickup_scheduled_at'));
        $this->assertSame('03:00 PM', $shipment->pickup_time);
    }

    /**
     * Skenario 9: Admin dapat melihat riwayat penolakan kurir (nama/ID kurir, waktu, alasan, nomor pengiriman).
     */
    public function test_admin_can_view_rejection_history_and_shipment_logs(): void
    {
        [$user, $courier] = $this->createCourier('History');
        [, $shipment, $order] = $this->createPendingOrder(null, 'pending');

        $this->actingAs($user, 'sanctum')->postJson('/api/orders/reject', [
            'order_id' => $order->id,
            'reason'   => 'Kendaraan tidak sesuai atau sedang bermasalah.',
            'notes'    => 'Ban motor bocor.',
        ])->assertStatus(200);

        $orderWithRejections = Order::with('rejections.courier.user')->find($order->id);
        $this->assertCount(1, $orderWithRejections->rejections);
        $rejection = $orderWithRejections->rejections->first();
        $this->assertSame($courier->id, (int) $rejection->courier_id);
        $this->assertSame('Kendaraan tidak sesuai atau sedang bermasalah.', $rejection->reason);
        $this->assertSame('Ban motor bocor.', $rejection->notes);

        $log = $shipment->logs()->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Tawaran pesanan ditolak oleh kurir', $log->description);
        $this->assertStringContainsString('Kendaraan tidak sesuai atau sedang bermasalah.', $log->description);
    }

    /**
     * Skenario 10: Penolakan ganda dan permintaan bersamaan bersifat idempoten dan tidak merusak status pesanan.
     */
    public function test_duplicate_rejection_is_idempotent_and_preserves_order_status(): void
    {
        [$user, $courier] = $this->createCourier('DoubleReject');
        [, $shipment, $order] = $this->createPendingOrder(null, 'pending');

        $first = $this->actingAs($user, 'sanctum')->postJson('/api/orders/reject', [
            'order_id' => $order->id,
            'reason'   => 'Lokasi pickup terlalu jauh.',
        ]);
        $first->assertStatus(200)
            ->assertJsonPath('already_rejected', false);

        $second = $this->actingAs($user, 'sanctum')->postJson('/api/orders/reject', [
            'order_id' => $order->id,
            'reason'   => 'Lokasi pickup terlalu jauh.',
        ]);
        $second->assertStatus(200)
            ->assertJsonPath('already_rejected', true);

        $count = OrderRejection::where('order_id', $order->id)
            ->where('courier_id', $courier->id)
            ->count();
        $this->assertSame(1, $count);

        $order->refresh();
        $shipment->refresh();
        $this->assertSame('pending', $order->status);
        $this->assertSame('confirmed', $shipment->status);
    }
}
