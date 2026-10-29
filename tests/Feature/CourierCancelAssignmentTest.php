<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CourierCancelAssignmentTest extends TestCase
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
            'email'    => "kurir_{$suffix}_" . uniqid() . '@example.com',
            'phone'    => '0812' . rand(10000000, 99999999),
            'password' => Hash::make('password123'),
            'role'     => 'courier',
        ]);

        $courier = Courier::create([
            'user_id'       => $user->id,
            'phone'         => $user->phone,
            'vehicle_type'  => 'motor',
            'vehicle_brand' => 'Honda Beat',
            'vehicle_plate' => 'L ' . rand(1000, 9999) . ' CC',
            'is_verified'   => true,
            'is_active'     => true,
        ]);

        return [$user, $courier];
    }

    private function createAssignedOrder(
        Courier $courier,
        string $orderStatus = 'assigned',
        ?Carbon $scheduledAt = null,
        ?Carbon $pickupStartedAt = null,
        ?Carbon $pickedUpAt = null
    ): array {
        $customer = User::create([
            'name'     => 'Pelanggan Uji',
            'email'    => 'pelanggan_' . uniqid() . '@example.com',
            'phone'    => '0813' . rand(10000000, 99999999),
            'password' => Hash::make('password123'),
            'role'     => 'user',
        ]);

        $shipmentStatus = match ($orderStatus) {
            'pending'   => 'confirmed',
            'assigned'  => 'assigned',
            'picking_up'=> 'picking_up',
            'picked_up' => 'picked_up',
            'delivering'=> 'delivering',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled',
            default     => 'assigned',
        };

        $shipment = Shipment::create([
            'user_id'             => $customer->id,
            'customer_name'       => $customer->name,
            'customer_phone'      => $customer->phone,
            'sender_name'         => $customer->name,
            'sender_phone'        => $customer->phone,
            'sender_address'      => 'Jl. Semolowaru No. 45, Surabaya',
            'sender_latitude'     => -7.2995,
            'sender_longitude'    => 112.7681,
            'origin_name'         => 'Sukolilo, Surabaya',
            'origin_id'           => 'SUB-01',
            'receiver_name'       => 'Budi Penerima',
            'receiver_phone'      => '081298765432',
            'receiver_address'    => 'Jl. Raya Darmo No. 10, Surabaya',
            'receiver_latitude'   => -7.2880,
            'receiver_longitude'  => 112.7380,
            'destination_name'    => 'Tegalsari, Surabaya',
            'destination_id'      => 'SUB-02',
            'package_description' => 'Dokumen Penting',
            'package_weight'      => 1.5,
            'vehicle'             => 'motor',
            'courier_code'        => 'MOTOR',
            'courier_name'        => 'City Courier Motor',
            'courier_service'     => $scheduledAt ? 'Scheduled' : 'Instant',
            'shipping_cost'       => 15000,
            'total_cost'          => 15000,
            'payment_method'      => 'TUNAI',
            'scheduled_pickup'    => $scheduledAt !== null,
            'pickup_time'         => $scheduledAt ? $scheduledAt->format('d M Y, h:i A') : null,
            'pickup_scheduled_at' => $scheduledAt,
            'status'              => $shipmentStatus,
        ]);

        $order = Order::where('order_number', $shipment->shipment_number)->first();
        if (!$order) {
            $order = Shipment::syncToOrder($shipment);
        }

        $order->update([
            'courier_id'          => $courier->id,
            'status'              => $orderStatus,
            'scheduled_pickup'    => $scheduledAt !== null,
            'pickup_time'         => $scheduledAt ? $scheduledAt->format('d M Y, h:i A') : null,
            'pickup_scheduled_at' => $scheduledAt,
            'pickup_started_at'   => $pickupStartedAt,
            'picked_up_at'        => $pickedUpAt,
        ]);

        return [$shipment->fresh(), $order->fresh()];
    }

    /**
     * 1. Kurir membatalkan penugasan sebelum pickup dimulai (status 'assigned' -> dilepas ke antrean).
     */
    public function test_1_courier_cancels_assignment_before_pickup_starts(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 30, 10, 0, 0, 'Asia/Jakarta'));
        [$courierUser, $courier] = $this->createCourier('A');
        $scheduledAt = Carbon::create(2026, 10, 30, 14, 0, 0, 'Asia/Jakarta'); // 4 jam lagi

        [$shipment, $order] = $this->createAssignedOrder($courier, 'assigned', $scheduledAt);

        $response = $this->actingAs($courierUser, 'sanctum')
            ->postJson('/api/orders/cancel-assignment', [
                'order_id' => $order->id,
                'reason'   => 'Kendala kendaraan.',
                'notes'    => 'Ban motor bocor di jalan.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('action_taken', 'released')
            ->assertJsonPath('title', 'Penugasan Berhasil Dibatalkan')
            ->assertJsonPath('message', 'Penugasan pesanan ini sudah dilepas dari akun kamu.');

        $order->refresh();
        $shipment->refresh();

        // Penugasan kurir dilepas & kembali ke antrean, BUKAN membatalkan pesanan pelanggan
        $this->assertNull($order->courier_id);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals('released', $order->courier_cancel_status);
        $this->assertEquals('Kendala kendaraan.', $order->courier_cancel_reason);
        $this->assertEquals('Ban motor bocor di jalan.', $order->courier_cancel_notes);
        $this->assertEquals($courier->id, $order->courier_cancelled_by_id);
        $this->assertNotNull($order->courier_cancelled_at);

        // Pelanggan tetap melihat status 'confirmed' (Menunggu Penugasan Kurir), bukan 'cancelled'
        $this->assertEquals('confirmed', $shipment->status);

        // Tidak lagi tampil di daftar tugas aktif kurir tersebut
        $activeRes = $this->actingAs($courierUser, 'sanctum')->getJson('/api/orders/active');
        $activeRes->assertStatus(200)->assertJsonPath('data', null);
    }

    /**
     * 2. Kurir mencoba membatalkan pesanan milik kurir lain -> ditolak HTTP 403.
     */
    public function test_2_courier_cannot_cancel_another_couriers_order(): void
    {
        [, $courierA] = $this->createCourier('Owner');
        [$courierUserB, ] = $this->createCourier('Attacker');

        [$shipment, $order] = $this->createAssignedOrder($courierA, 'assigned');

        $response = $this->actingAs($courierUserB, 'sanctum')
            ->postJson('/api/orders/cancel-assignment', [
                'order_id' => $order->id,
                'reason'   => 'Kendala kendaraan.',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'forbidden_not_assigned_courier')
            ->assertJsonPath('title', 'Pembatalan Tidak Dapat Diproses');

        $this->assertEquals($courierA->id, $order->fresh()->courier_id);
        $this->assertEquals('assigned', $order->fresh()->status);
        $this->assertEquals('assigned', $shipment->fresh()->status);
    }

    /**
     * 3. Kurir mencoba membatalkan pesanan yang sudah selesai (delivered) -> ditolak HTTP 422.
     */
    public function test_3_courier_cannot_cancel_completed_order(): void
    {
        [$courierUser, $courier] = $this->createCourier('Done');
        [, $order] = $this->createAssignedOrder($courier, 'delivered');

        $response = $this->actingAs($courierUser, 'sanctum')
            ->postJson('/api/orders/cancel-assignment', [
                'order_id' => $order->id,
                'reason'   => 'Kendala pribadi atau keadaan darurat.',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'order_already_completed')
            ->assertJsonPath('title', 'Pembatalan Tidak Dapat Diproses');

        $this->assertEquals('delivered', $order->fresh()->status);
    }

    /**
     * 4. Kurir mencoba membatalkan setelah barang diambil (picked_up / delivering) -> ditolak HTTP 422.
     */
    public function test_4_courier_cannot_cancel_after_item_picked_up(): void
    {
        [$courierUser, $courier] = $this->createCourier('PickedUp');
        $now = Carbon::now('Asia/Jakarta');
        [$shipment, $order] = $this->createAssignedOrder($courier, 'picked_up', null, $now->copy()->subMinutes(15), $now);

        $response = $this->actingAs($courierUser, 'sanctum')
            ->postJson('/api/orders/cancel-assignment', [
                'order_id' => $order->id,
                'reason'   => 'Kendala kendaraan.',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'item_already_picked_up')
            ->assertJsonPath('title', 'Pembatalan Tidak Dapat Diproses');

        // Penugasan dan status tetap terjaga
        $this->assertEquals($courier->id, $order->fresh()->courier_id);
        $this->assertEquals('picked_up', $order->fresh()->status);
        $this->assertEquals('picked_up', $shipment->fresh()->status);
    }

    /**
     * 5. Kurir mengajukan pembatalan saat jadwal pickup kurang dari 2 jam -> diizinkan & prioritas tinggi untuk admin.
     */
    public function test_5_courier_can_cancel_when_pickup_is_within_2_hours_with_urgent_priority(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 30, 13, 30, 0, 'Asia/Jakarta'));
        [$courierUser, $courier] = $this->createCourier('Urgent');
        // Jadwal pickup pukul 15:00 (tersisa 90 menit / 1.5 jam -> pelanggan dilarang, tetapi kurir diizinkan dengan prioritas tinggi)
        $scheduledAt = Carbon::create(2026, 10, 30, 15, 0, 0, 'Asia/Jakarta');

        [$shipment, $order] = $this->createAssignedOrder($courier, 'assigned', $scheduledAt);

        $response = $this->actingAs($courierUser, 'sanctum')
            ->postJson('/api/orders/cancel-assignment', [
                'order_id' => $order->id,
                'reason'   => 'Tidak dapat menjemput pada jadwal yang ditentukan.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('action_taken', 'released')
            ->assertJsonPath('priority', 'urgent')
            ->assertJsonPath('is_within_2_hours', true);

        $order->refresh();
        $this->assertEquals('urgent', $order->courier_cancel_priority);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals('confirmed', $shipment->fresh()->status);
    }

    /**
     * 6. Permintaan pembatalan harus menunggu persetujuan admin (saat pickup sudah dimulai / status picking_up)
     *    dan baru dilepas setelah admin menyetujui.
     */
    public function test_6_cancellation_request_waits_for_admin_approval_when_picking_up(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 30, 14, 40, 0, 'Asia/Jakarta'));
        [$courierUser, $courier] = $this->createCourier('PickingUp');
        $scheduledAt = Carbon::create(2026, 10, 30, 15, 0, 0, 'Asia/Jakarta');

        [$shipment, $order] = $this->createAssignedOrder(
            $courier,
            'picking_up',
            $scheduledAt,
            Carbon::create(2026, 10, 30, 14, 35, 0, 'Asia/Jakarta')
        );

        $response = $this->actingAs($courierUser, 'sanctum')
            ->postJson('/api/orders/cancel-assignment', [
                'order_id' => $order->id,
                'reason'   => 'Alasan lainnya.',
                'notes'    => 'Jalan menuju lokasi banjir tinggi tidak bisa dilewati.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('action_taken', 'pending_approval')
            ->assertJsonPath('title', 'Permintaan Pembatalan Terkirim')
            ->assertJsonPath(
                'message',
                'Permintaan kamu sedang diperiksa oleh admin. Status penugasan akan diperbarui setelah keputusan diberikan.'
            );

        $order->refresh();
        // Penugasan belum langsung dilepas sebelum keputusan admin
        $this->assertEquals($courier->id, $order->courier_id);
        $this->assertEquals('picking_up', $order->status);
        $this->assertEquals('pending_approval', $order->courier_cancel_status);
        $this->assertEquals('Alasan lainnya.', $order->courier_cancel_reason);
        $this->assertEquals('Jalan menuju lokasi banjir tinggi tidak bisa dilewati.', $order->courier_cancel_notes);

        // Idempotensi: pengajuan kedua oleh kurir yang sama tidak merusak status
        $secondRes = $this->actingAs($courierUser, 'sanctum')
            ->postJson('/api/orders/cancel-assignment', [
                'order_id' => $order->id,
                'reason'   => 'Alasan lainnya.',
                'notes'    => 'Jalan menuju lokasi banjir tinggi tidak bisa dilewati.',
            ]);
        $secondRes->assertStatus(200)
            ->assertJsonPath('already_processed', true)
            ->assertJsonPath('action_taken', 'pending_approval');

        // Admin menyetujui permintaan pembatalan penugasan
        $admin = User::create([
            'name'     => 'Admin Operasional',
            'email'    => 'admin_ops_' . uniqid() . '@example.com',
            'phone'    => '0811' . rand(10000000, 99999999),
            'password' => Hash::make('password123'),
            'role'     => 'admin',
        ]);

        $app = app(\App\Http\Controllers\AdminController::class);
        $adminReq = \Illuminate\Http\Request::create(
            "/admin/orders/{$order->id}/courier-cancel-decision",
            'POST',
            ['decision' => 'approve', 'admin_notes' => 'Disetujui karena kendala banjir']
        );
        $this->actingAs($admin);
        $app->handleCourierCancelDecision($adminReq, $order->fresh(), app(\App\Services\TrackingService::class));

        $order->refresh();
        $shipment->refresh();
        $this->assertNull($order->courier_id);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals('approved', $order->courier_cancel_status);
        $this->assertEquals('confirmed', $shipment->status);
    }

    /**
     * 7. Pesanan yang dilepas kembali ke antrean tanpa penugasan ganda.
     */
    public function test_7_released_order_returns_to_queue_without_double_assignment(): void
    {
        [$courierUser1, $courier1] = $this->createCourier('First');
        [$courierUser2, $courier2] = $this->createCourier('Second');
        [$courierUser3, ] = $this->createCourier('Third');

        [$shipment, $order] = $this->createAssignedOrder($courier1, 'assigned');

        // Kurir 1 melepas penugasan
        $this->actingAs($courierUser1, 'sanctum')
            ->postJson('/api/orders/cancel-assignment', [
                'order_id' => $order->id,
                'reason'   => 'Lokasi pickup sulit dijangkau.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('action_taken', 'released');

        // Pesanan muncul kembali di antrean available
        $availRes = $this->actingAs($courierUser2, 'sanctum')->getJson('/api/orders/available');
        $availRes->assertStatus(200);
        $availableIds = collect($availRes->json('data.data'))->pluck('id')->all();
        $this->assertContains($order->id, $availableIds);

        // Kurir 2 mengambil pesanan dari antrean
        $claim2 = $this->actingAs($courierUser2, 'sanctum')
            ->patchJson('/api/update-status-order', [
                'order_id' => $order->id,
                'status'   => 'assigned',
            ]);
        $claim2->assertStatus(200)->assertJsonPath('success', true);

        // Kurir 3 mencoba mengambil pesanan yang sama -> ditolak HTTP 409 (tidak ada penugasan ganda)
        $claim3 = $this->actingAs($courierUser3, 'sanctum')
            ->patchJson('/api/update-status-order', [
                'order_id' => $order->id,
                'status'   => 'assigned',
            ]);
        $claim3->assertStatus(409)->assertJsonPath('success', false);

        $order->refresh();
        $this->assertEquals($courier2->id, $order->courier_id);
        $this->assertEquals('assigned', $order->status);
        $this->assertNull($order->courier_cancel_status);
        // Riwayat kurir sebelumnya yang membatalkan tetap tersimpan
        $this->assertEquals($courier1->id, $order->courier_cancelled_by_id);
    }

    /**
     * 8. Alasan pembatalan tersimpan dan tampil di halaman admin.
     */
    public function test_8_cancellation_reason_saved_and_displayed_in_admin_views(): void
    {
        [$courierUser, $courier] = $this->createCourier('Display');
        [$shipment, $order] = $this->createAssignedOrder(
            $courier,
            'assigned',
            Carbon::now('Asia/Jakarta')->addMinutes(75)
        );

        $this->actingAs($courierUser, 'sanctum')
            ->postJson('/api/orders/cancel-assignment', [
                'order_id' => $order->id,
                'reason'   => 'Alasan lainnya.',
                'notes'    => 'Rantai motor putus perlu ke bengkel segera.',
            ])
            ->assertStatus(200);

        $order->refresh()->load(['courier.user', 'lastCancelledCourier.user', 'shipment']);
        $shipment->refresh()->load(['order.courier.user', 'order.lastCancelledCourier.user', 'logs']);

        auth()->forgetGuards();

        $orderDetailHtml = view('admin.order-detail', ['order' => $order])->render();
        $this->assertStringContainsString('Informasi Pembatalan Penugasan Kurir', $orderDetailHtml);
        $this->assertStringContainsString('Alasan lainnya.', $orderDetailHtml);
        $this->assertStringContainsString('Rantai motor putus perlu ke bengkel segera.', $orderDetailHtml);
        $this->assertStringContainsString('PRIORITAS TINGGI', $orderDetailHtml);

        $shipmentShowHtml = view('admin.shipments.show', ['shipment' => $shipment])->render();
        $this->assertStringContainsString('Informasi Pembatalan Penugasan Kurir', $shipmentShowHtml);
        $this->assertStringContainsString('Rantai motor putus perlu ke bengkel segera.', $shipmentShowHtml);
    }
}
