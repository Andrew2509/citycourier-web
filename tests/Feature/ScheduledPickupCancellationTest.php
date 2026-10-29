<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ScheduledPickupCancellationTest extends TestCase
{
    protected User $customerA;
    protected User $customerB;
    protected User $courierUser;
    protected Courier $courier;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Gunakan koneksi MySQL proyek agar fitur lockForUpdate() dan skema tabel produksi teruji penuh
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.database' => 'city-courier-web',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
            'app.timezone' => 'Asia/Jakarta',
        ]);

        DB::purge('mysql');
        DB::connection('mysql')->beginTransaction();

        $this->customerA = User::create([
            'name'     => 'Pelanggan Utama',
            'email'    => 'cust_a_' . uniqid() . '@example.com',
            'phone'    => '081234567801',
            'password' => Hash::make('password123'),
            'role'     => 'user',
        ]);

        $this->customerB = User::create([
            'name'     => 'Pelanggan Lain',
            'email'    => 'cust_b_' . uniqid() . '@example.com',
            'phone'    => '081234567802',
            'password' => Hash::make('password123'),
            'role'     => 'user',
        ]);

        $this->courierUser = User::create([
            'name'     => 'Kurir Uji',
            'email'    => 'courier_' . uniqid() . '@example.com',
            'phone'    => '081234567803',
            'password' => Hash::make('password123'),
            'role'     => 'courier',
        ]);

        $this->courier = Courier::create([
            'user_id'       => $this->courierUser->id,
            'phone'         => '081234567803',
            'vehicle_type'  => 'motor',
            'vehicle_brand' => 'Honda',
            'vehicle_model' => 'Vario',
            'vehicle_plate' => 'L 1234 XY',
            'is_active'     => true,
            'is_verified'   => true,
        ]);

        $this->adminUser = User::create([
            'name'     => 'Admin City Courier',
            'email'    => 'admin_' . uniqid() . '@example.com',
            'phone'    => '081343323155',
            'password' => Hash::make('password123'),
            'role'     => 'admin',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::connection('mysql')->rollBack();
        parent::tearDown();
    }

    /**
     * Helper untuk membuat Shipment terjadwal beserta Order terkait.
     */
    protected function createScheduledShipment(
        Carbon $pickupScheduledAt,
        string $status = 'confirmed',
        ?User $owner = null,
        ?int $courierId = null,
        ?Carbon $pickupStartedAt = null
    ): array {
        $owner = $owner ?? $this->customerA;

        $shipment = Shipment::create([
            'user_id'             => $owner->id,
            'customer_name'       => $owner->name,
            'customer_phone'      => $owner->phone,
            'sender_name'         => $owner->name,
            'sender_phone'        => $owner->phone,
            'sender_address'      => 'Jl. Semolowaru No. 45, Surabaya',
            'origin_name'         => 'Surabaya',
            'receiver_name'       => 'Penerima Surabaya',
            'receiver_phone'      => '081299998888',
            'receiver_address'    => 'Jl. Raya Darmo No. 10, Surabaya',
            'destination_name'    => 'Surabaya',
            'package_description' => 'Dokumen Penting',
            'package_weight'      => 1.5,
            'courier_code'        => 'motor',
            'courier_name'        => 'City Courier Motor',
            'courier_service'     => 'Scheduled',
            'vehicle'             => 'motor',
            'shipping_cost'       => 15000,
            'total_cost'          => 15000,
            'payment_method'      => 'COD',
            'scheduled_pickup'    => true,
            'pickup_time'         => $pickupScheduledAt->format('h:i A'),
            'pickup_scheduled_at' => $pickupScheduledAt,
            'status'              => $status,
        ]);

        $order = Order::where('order_number', $shipment->shipment_number)->first();
        if (!$order) {
            $order = Order::create([
                'order_number'        => $shipment->shipment_number,
                'courier_id'          => $courierId,
                'customer_name'       => $shipment->customer_name,
                'customer_phone'      => $shipment->customer_phone,
                'pickup_address'      => $shipment->sender_address,
                'delivery_address'    => $shipment->receiver_address,
                'package_description' => $shipment->package_description,
                'package_weight'      => $shipment->package_weight,
                'vehicle'             => 'motor',
                'price'               => $shipment->total_cost,
                'scheduled_pickup'    => true,
                'pickup_time'         => $shipment->pickup_time,
                'pickup_scheduled_at' => $pickupScheduledAt,
                'status'              => $status === 'confirmed' ? 'pending' : $status,
                'pickup_started_at'   => $pickupStartedAt,
            ]);
        } else {
            $order->update([
                'courier_id'        => $courierId,
                'status'            => $status === 'confirmed' ? 'pending' : $status,
                'pickup_started_at' => $pickupStartedAt,
            ]);
        }

        return [$shipment->fresh(), $order->fresh()];
    }

    /**
     * Skenario 1:
     * Waktu pickup masih > 2 jam (15:00 WIB, sekarang 12:59 WIB -> sisa 121 menit):
     * Pembatalan mandiri berhasil jika status memenuhi syarat.
     */
    public function test_1_pickup_more_than_2_hours_away_succeeds(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 30, 12, 59, 0, 'Asia/Jakarta'));
        $pickupAt = Carbon::create(2026, 10, 30, 15, 0, 0, 'Asia/Jakarta');

        [$shipment, $order] = $this->createScheduledShipment($pickupAt, 'confirmed');

        $response = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/shipments/{$shipment->tracking_number}/cancel", [
                'reason' => 'Ingin mengubah alamat tujuan pengiriman',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $shipment->refresh();
        $order->refresh();

        $this->assertEquals('cancelled', $shipment->status);
        $this->assertEquals('customer', $shipment->cancelled_by);
        $this->assertEquals('confirmed', $shipment->cancelled_from_status);
        $this->assertEquals('Ingin mengubah alamat tujuan pengiriman', $shipment->cancellation_reason);
        $this->assertNotNull($shipment->cancelled_at);

        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('customer', $order->cancelled_by);
    }

    /**
     * Skenario 2:
     * Waktu pickup tepat 2 jam (15:00 WIB, sekarang 13:00 WIB -> sisa 120 menit):
     * Pembatalan mandiri ditolak karena harus > 120 menit.
     */
    public function test_2_pickup_exactly_2_hours_away_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 30, 13, 0, 0, 'Asia/Jakarta'));
        $pickupAt = Carbon::create(2026, 10, 30, 15, 0, 0, 'Asia/Jakarta');

        [$shipment, $order] = $this->createScheduledShipment($pickupAt, 'confirmed');

        $response = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/shipments/{$shipment->tracking_number}/cancel", [
                'reason' => 'Batal kirim',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success'    => false,
                'error_code' => 'within_2_hours',
            ]);

        $this->assertEquals('confirmed', $shipment->fresh()->status);
        $this->assertEquals('pending', $order->fresh()->status);
    }

    /**
     * Skenario 3:
     * Waktu pickup kurang dari 2 jam (15:00 WIB, sekarang 14:00 WIB -> sisa 60 menit):
     * Pembatalan mandiri ditolak.
     */
    public function test_3_pickup_less_than_2_hours_away_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 30, 14, 0, 0, 'Asia/Jakarta'));
        $pickupAt = Carbon::create(2026, 10, 30, 15, 0, 0, 'Asia/Jakarta');

        [$shipment, $order] = $this->createScheduledShipment($pickupAt, 'assigned', $this->customerA, $this->courier->id);

        $response = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/shipments/{$shipment->tracking_number}/cancel", [
                'reason' => 'Salah jadwal',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success'    => false,
                'error_code' => 'within_2_hours',
            ]);

        $this->assertEquals('assigned', $shipment->fresh()->status);
        $this->assertEquals('assigned', $order->fresh()->status);
    }

    /**
     * Skenario 4:
     * Waktu pickup sudah lewat (15:00 WIB, sekarang 15:00 WIB & 15:30 WIB):
     * Pembatalan mandiri ditolak dan pesanan tidak dibatalkan otomatis.
     */
    public function test_4_pickup_already_passed_is_rejected(): void
    {
        $pickupAt = Carbon::create(2026, 10, 30, 15, 0, 0, 'Asia/Jakarta');
        [$shipment, $order] = $this->createScheduledShipment($pickupAt, 'confirmed');

        // Tepat pukul 15:00
        Carbon::setTestNow(Carbon::create(2026, 10, 30, 15, 0, 0, 'Asia/Jakarta'));
        $resExact = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/shipments/{$shipment->tracking_number}/cancel");
        $resExact->assertStatus(422)
            ->assertJson([
                'success'    => false,
                'error_code' => 'pickup_time_passed',
            ]);

        // Lewat 30 menit (pukul 15:30)
        Carbon::setTestNow(Carbon::create(2026, 10, 30, 15, 30, 0, 'Asia/Jakarta'));
        $resPast = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/shipments/{$shipment->tracking_number}/cancel");
        $resPast->assertStatus(422)
            ->assertJson([
                'success'    => false,
                'error_code' => 'pickup_time_passed',
            ]);

        $this->assertEquals('confirmed', $shipment->fresh()->status);
    }

    /**
     * Skenario 5:
     * Proses pickup sudah dimulai (status picking_up / picked_up):
     * Pembatalan mandiri ditolak meskipun jadwal pickup masih > 2 jam.
     */
    public function test_5_pickup_already_started_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 30, 11, 0, 0, 'Asia/Jakarta'));
        $pickupAt = Carbon::create(2026, 10, 30, 15, 0, 0, 'Asia/Jakarta');

        [$shipment, $order] = $this->createScheduledShipment(
            $pickupAt,
            'picking_up',
            $this->customerA,
            $this->courier->id,
            Carbon::now('Asia/Jakarta')
        );

        $response = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/shipments/{$shipment->tracking_number}/cancel", [
                'reason' => 'Ingin membatalkan',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success'    => false,
                'error_code' => 'pickup_already_started',
            ]);

        $this->assertEquals('picking_up', $shipment->fresh()->status);
        $this->assertEquals('picking_up', $order->fresh()->status);
    }

    /**
     * Skenario 6:
     * Pesanan sudah dibatalkan: permintaan kedua bersifat idempoten dan tidak merusak data.
     */
    public function test_6_order_already_cancelled_second_request_is_idempotent(): void
    {
        $firstCancelTime = Carbon::create(2026, 10, 30, 10, 0, 0, 'Asia/Jakarta');
        Carbon::setTestNow($firstCancelTime);
        $pickupAt = Carbon::create(2026, 10, 30, 15, 0, 0, 'Asia/Jakarta');

        [$shipment, $order] = $this->createScheduledShipment($pickupAt, 'confirmed');

        // Permintaan pertama
        $res1 = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/shipments/{$shipment->tracking_number}/cancel", [
                'reason' => 'Alasan Pertama Asli',
            ]);
        $res1->assertStatus(200);

        $shipment->refresh();
        $originalCancelledAt = $shipment->cancelled_at->toIso8601String();
        $logsCountAfterFirst = $shipment->logs()->where('status', 'cancelled')->count();

        // Permintaan kedua (10 menit kemudian dengan alasan berbeda)
        Carbon::setTestNow(Carbon::create(2026, 10, 30, 10, 10, 0, 'Asia/Jakarta'));
        $res2 = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/shipments/{$shipment->tracking_number}/cancel", [
                'reason' => 'Alasan Kedua Tidak Boleh Menimpa',
            ]);

        $res2->assertStatus(200)
            ->assertJson([
                'success'           => true,
                'already_cancelled' => true,
            ]);

        $shipment->refresh();
        $this->assertEquals('Alasan Pertama Asli', $shipment->cancellation_reason);
        $this->assertEquals($originalCancelledAt, $shipment->cancelled_at->toIso8601String());
        $this->assertEquals($logsCountAfterFirst, $shipment->logs()->where('status', 'cancelled')->count());
    }

    /**
     * Skenario 7:
     * Pelanggan mencoba membatalkan pesanan milik pengguna lain: ditolak (HTTP 403).
     */
    public function test_7_customer_cannot_cancel_another_users_order(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 30, 10, 0, 0, 'Asia/Jakarta'));
        $pickupAt = Carbon::create(2026, 10, 30, 15, 0, 0, 'Asia/Jakarta');

        [$shipment, $order] = $this->createScheduledShipment($pickupAt, 'confirmed', $this->customerA);

        $response = $this->actingAs($this->customerB, 'sanctum')
            ->postJson("/api/shipments/{$shipment->tracking_number}/cancel", [
                'reason' => 'Mencoba batalkan milik orang lain',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success'    => false,
                'error_code' => 'forbidden_ownership',
            ]);

        $this->assertEquals('confirmed', $shipment->fresh()->status);
    }

    /**
     * Skenario 8:
     * Jadwal pickup diubah di database, lalu pembatalan diuji ulang menggunakan jadwal terbaru dari database.
     */
    public function test_8_pickup_schedule_updated_uses_latest_database_schedule(): void
    {
        // Waktu sekarang: 13:00 WIB
        Carbon::setTestNow(Carbon::create(2026, 10, 30, 13, 0, 0, 'Asia/Jakarta'));

        // Awalnya jadwal pukul 14:30 WIB (sisa 90 menit <= 120 menit -> ditolak)
        $initialPickup = Carbon::create(2026, 10, 30, 14, 30, 0, 'Asia/Jakarta');
        [$shipment, $order] = $this->createScheduledShipment($initialPickup, 'confirmed');

        $resRejected = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/shipments/{$shipment->tracking_number}/cancel");
        $resRejected->assertStatus(422)
            ->assertJson(['error_code' => 'within_2_hours']);

        // Jadwal diubah di database menjadi pukul 17:00 WIB (sisa 240 menit > 120 menit)
        $updatedPickup = Carbon::create(2026, 10, 30, 17, 0, 0, 'Asia/Jakarta');
        $shipment->update([
            'pickup_scheduled_at' => $updatedPickup,
            'pickup_time'         => '05:00 PM',
        ]);

        // Uji ulang pembatalan -> berhasil menggunakan jadwal terbaru dari DB
        $resAllowed = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/shipments/{$shipment->tracking_number}/cancel", [
                'reason' => 'Jadwal sudah diundur ke sore dan tetap batal',
            ]);
        $resAllowed->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('cancelled', $shipment->fresh()->status);
    }

    /**
     * Skenario 9:
     * Pesanan sudah ditugaskan ke kurir (status assigned), lalu dibatalkan secara sah (> 2 jam):
     * Penugasan kurir diperbarui dengan benar (status Order menjadi cancelled, hilang dari tugas aktif kurir,
     * namun riwayat courier_id tetap tersimpan).
     */
    public function test_9_assigned_order_cancellation_updates_courier_assignment(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 30, 9, 0, 0, 'Asia/Jakarta'));
        $pickupAt = Carbon::create(2026, 10, 30, 15, 0, 0, 'Asia/Jakarta');

        [$shipment, $order] = $this->createScheduledShipment(
            $pickupAt,
            'assigned',
            $this->customerA,
            $this->courier->id
        );

        // Pastikan sebelum dibatalkan, order muncul sebagai tugas aktif kurir
        $activeBefore = $this->actingAs($this->courierUser, 'sanctum')
            ->getJson('/api/orders/active');
        $activeBefore->assertStatus(200);
        $this->assertEquals($order->id, $activeBefore->json('data.id'));

        // Pelanggan membatalkan secara sah (6 jam sebelum pickup)
        $cancelRes = $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/shipments/{$shipment->tracking_number}/cancel", [
                'reason' => 'Pengiriman ditunda minggu depan',
            ]);
        $cancelRes->assertStatus(200);

        $order->refresh();
        $shipment->refresh();

        $this->assertEquals('cancelled', $shipment->status);
        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('assigned', $order->cancelled_from_status);
        // Riwayat kurir tetap tercatat
        $this->assertEquals($this->courier->id, $order->courier_id);

        // Setelah dibatalkan, order tidak lagi muncul sebagai tugas aktif kurir
        $activeAfter = $this->actingAs($this->courierUser, 'sanctum')
            ->getJson('/api/orders/active');
        $activeAfter->assertStatus(200);
        $this->assertNull($activeAfter->json('data'));
    }

    /**
     * Skenario 10:
     * Kurir mencoba memulai pickup setelah pesanan dibatalkan: ditolak oleh backend.
     */
    public function test_10_courier_cannot_start_pickup_after_order_is_cancelled(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 30, 11, 0, 0, 'Asia/Jakarta'));
        $pickupAt = Carbon::create(2026, 10, 30, 15, 0, 0, 'Asia/Jakarta');

        [$shipment, $order] = $this->createScheduledShipment(
            $pickupAt,
            'assigned',
            $this->customerA,
            $this->courier->id
        );

        // Pelanggan membatalkan pesanan pada pukul 11:00 WIB
        $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/shipments/{$shipment->tracking_number}/cancel", [
                'reason' => 'Dibatalkan pelanggan',
            ])->assertStatus(200);

        // Pada pukul 14:40 WIB, kurir mencoba menekan Mulai Pickup (status: picking_up)
        Carbon::setTestNow(Carbon::create(2026, 10, 30, 14, 40, 0, 'Asia/Jakarta'));
        $courierRes = $this->actingAs($this->courierUser, 'sanctum')
            ->patchJson('/api/update-status-order', [
                'order_id' => $order->id,
                'status'   => 'picking_up',
            ]);

        $courierRes->assertStatus(409)
            ->assertJson([
                'success'    => false,
                'error_code' => 'order_cancelled',
            ]);

        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertNull($order->fresh()->pickup_started_at);
        $this->assertEquals('cancelled', $shipment->fresh()->status);
    }

    /**
     * Skenario 11:
     * Kondisi hampir bersamaan antara pembatalan dan mulai pickup: hasil akhir tetap konsisten.
     */
    public function test_11_near_simultaneous_cancellation_and_start_pickup_maintains_consistent_state(): void
    {
        // Kasus 11A: Pesanan non-scheduled / dalam toleransi di mana kurir berhasil memulai pickup lebih dulu,
        // lalu sepersekian detik kemudian request cancel pelanggan masuk -> cancel ditolak, status konsisten 'picking_up'.
        Carbon::setTestNow(Carbon::create(2026, 10, 30, 12, 0, 0, 'Asia/Jakarta'));
        $pickupAtA = Carbon::create(2026, 10, 30, 15, 0, 0, 'Asia/Jakarta');
        [$shipmentA, $orderA] = $this->createScheduledShipment($pickupAtA, 'assigned', $this->customerA, $this->courier->id);
        // Set non-scheduled untuk menguji balapan langsung antara start pickup vs cancel pada jam yang sama
        $shipmentA->update(['scheduled_pickup' => false, 'pickup_scheduled_at' => null]);
        $orderA->update(['scheduled_pickup' => false, 'pickup_scheduled_at' => null]);

        // 1. Kurir berhasil mengunci & memulai pickup
        $this->actingAs($this->courierUser, 'sanctum')
            ->patchJson('/api/update-status-order', [
                'order_id' => $orderA->id,
                'status'   => 'picking_up',
            ])->assertStatus(200);

        // 2. Request pembatalan pelanggan yang masuk tepat setelahnya ditolak karena status terkunci sudah picking_up
        $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/shipments/{$shipmentA->tracking_number}/cancel")
            ->assertStatus(422)
            ->assertJson(['error_code' => 'pickup_already_started']);

        $this->assertEquals('picking_up', $shipmentA->fresh()->status);
        $this->assertEquals('picking_up', $orderA->fresh()->status);

        // Kasus 11B: Pelanggan berhasil mengunci & membatalkan lebih dulu,
        // lalu sepersekian detik kemudian request mulai pickup kurir masuk -> mulai pickup ditolak, status konsisten 'cancelled'.
        [$shipmentB, $orderB] = $this->createScheduledShipment($pickupAtA, 'assigned', $this->customerA, $this->courier->id);
        $shipmentB->update(['scheduled_pickup' => false, 'pickup_scheduled_at' => null]);
        $orderB->update(['scheduled_pickup' => false, 'pickup_scheduled_at' => null]);

        // 1. Pelanggan berhasil membatalkan
        $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/shipments/{$shipmentB->tracking_number}/cancel")
            ->assertStatus(200);

        // 2. Request mulai pickup kurir yang masuk tepat setelahnya ditolak
        $this->actingAs($this->courierUser, 'sanctum')
            ->patchJson('/api/update-status-order', [
                'order_id' => $orderB->id,
                'status'   => 'picking_up',
            ])->assertStatus(409)
            ->assertJson(['error_code' => 'order_cancelled']);

        $this->assertEquals('cancelled', $shipmentB->fresh()->status);
        $this->assertEquals('cancelled', $orderB->fresh()->status);
    }

    /**
     * Skenario Tambahan (Kewenangan Admin):
     * Admin tetap dapat membatalkan pesanan meskipun sisa waktu pickup <= 2 jam,
     * dan tercatat cancelled_by = 'admin'.
     */
    public function test_12_admin_can_cancel_order_within_2_hours_cutoff(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 30, 14, 15, 0, 'Asia/Jakarta'));
        $pickupAt = Carbon::create(2026, 10, 30, 15, 0, 0, 'Asia/Jakarta');

        [$shipment, $order] = $this->createScheduledShipment($pickupAt, 'assigned', $this->customerA, $this->courier->id);

        // Pelanggan ditolak karena sisa waktu 45 menit (<= 2 jam)
        $this->actingAs($this->customerA, 'sanctum')
            ->postJson("/api/shipments/{$shipment->tracking_number}/cancel")
            ->assertStatus(422);

        // Admin membatalkan atas permintaan pelanggan melalui bantuan CS
        $adminRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/shipments/{$shipment->tracking_number}/cancel", [
                'reason' => 'Dibatalkan oleh Admin atas permintaan pelanggan via WhatsApp CS',
            ]);

        $adminRes->assertStatus(200)
            ->assertJson(['success' => true]);

        $shipment->refresh();
        $order->refresh();

        $this->assertEquals('cancelled', $shipment->status);
        $this->assertEquals('admin', $shipment->cancelled_by);
        $this->assertEquals('Admin City Courier', $shipment->cancelled_by_label);
        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('admin', $order->cancelled_by);
    }
}
