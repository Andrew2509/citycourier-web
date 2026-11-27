<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\CiWorkController;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CourierAvailabilityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesPaymentWalletSchema;

class CourierAvailabilityTest extends TestCase
{
    use CreatesPaymentWalletSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBaseTablesForWalletTests();

        Schema::table('couriers', function (Blueprint $table): void {
            if (! Schema::hasColumn('couriers', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable();
            }
            if (! Schema::hasColumn('couriers', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable();
            }
            if (! Schema::hasColumn('couriers', 'work_status')) {
                $table->string('work_status', 24)->nullable();
            }
            if (! Schema::hasColumn('couriers', 'status_changed_at')) {
                $table->timestamp('status_changed_at')->nullable();
            }
        });

        Schema::table('shipments', function (Blueprint $table): void {
            foreach (['sender_latitude', 'sender_longitude', 'receiver_latitude', 'receiver_longitude'] as $col) {
                if (! Schema::hasColumn('shipments', $col)) {
                    $table->decimal($col, 10, 7)->nullable();
                }
            }
            if (! Schema::hasColumn('shipments', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }
            if (! Schema::hasColumn('shipments', 'cancelled_by')) {
                $table->string('cancelled_by', 50)->nullable();
            }
            if (! Schema::hasColumn('shipments', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable();
            }
            if (! Schema::hasColumn('shipments', 'cancelled_from_status')) {
                $table->string('cancelled_from_status', 50)->nullable();
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            foreach (['issue_reason', 'issue_notes', 'pickup_photo', 'cancelled_by', 'cancelled_from_status', 'courier_cancel_status', 'courier_cancel_reason', 'courier_cancel_priority'] as $col) {
                if (! Schema::hasColumn('orders', $col)) {
                    $table->string($col)->nullable();
                }
            }
            foreach (['cancellation_reason', 'courier_cancel_notes'] as $col) {
                if (! Schema::hasColumn('orders', $col)) {
                    $table->text($col)->nullable();
                }
            }
            foreach (['pickup_started_at', 'picked_up_at', 'delivering_at', 'cancelled_at', 'courier_cancelled_at'] as $col) {
                if (! Schema::hasColumn('orders', $col)) {
                    $table->timestamp($col)->nullable();
                }
            }
            if (! Schema::hasColumn('orders', 'courier_cancelled_by_id')) {
                $table->unsignedBigInteger('courier_cancelled_by_id')->nullable();
            }
        });

        if (! Schema::hasTable('shipment_logs')) {
            Schema::create('shipment_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
                $table->unsignedBigInteger('courier_id')->nullable();
                $table->string('status');
                $table->string('location')->nullable();
                $table->text('description')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('accuracy', 8, 2)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('courier_locations')) {
            Schema::create('courier_locations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('courier_id');
                $table->unsignedBigInteger('shipment_id')->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->decimal('accuracy', 8, 2)->nullable();
                $table->decimal('speed_kmh', 8, 2)->nullable();
                $table->unsignedInteger('battery_percent')->nullable();
                $table->timestamp('recorded_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('roles')) {
            (require database_path('migrations/2026_04_17_071227_create_permission_tables.php'))->up();
        }

        (require database_path('migrations/2026_10_29_215000_create_order_rejections_table.php'))->up();
    }

    private function createCourier(bool $isActive = true, bool $isVerified = true, string $suffix = '1'): array
    {
        $user = User::create([
            'name'     => "Kurir {$suffix}",
            'email'    => "kurir_{$suffix}_" . uniqid() . '@example.com',
            'phone'    => '0812' . rand(10000000, 99999999),
            'role'     => 'courier',
            'password' => Hash::make('password123'),
        ]);

        $courier = Courier::create([
            'user_id'       => $user->id,
            'phone'         => $user->phone,
            'vehicle_type'  => 'motor',
            'vehicle_plate' => 'L ' . rand(1000, 9999) . ' CC',
            'is_verified'   => $isVerified,
            'is_active'     => $isActive,
            'work_status'   => $isActive ? Courier::STATUS_ONLINE : Courier::STATUS_OFFLINE,
            'latitude'      => -7.2575,
            'longitude'     => 112.7521,
        ]);

        return [$user, $courier];
    }

    private function createOrderWithShipment(?User $customer = null, string $status = 'pending', ?Courier $courier = null): array
    {
        $customer ??= User::create([
            'name'     => 'Customer ' . uniqid(),
            'email'    => 'cust_' . uniqid() . '@example.com',
            'phone'    => '0813' . rand(10000000, 99999999),
            'role'     => 'customer',
            'password' => Hash::make('password123'),
        ]);

        $num = 'SHP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        $trk = 'CC-' . strtoupper(substr(uniqid(), -8));

        $shipment = Shipment::create([
            'shipment_number'  => $num,
            'tracking_number'  => $trk,
            'user_id'          => $customer->id,
            'customer_name'    => $customer->name,
            'customer_phone'   => $customer->phone,
            'sender_name'      => 'Pengirim',
            'sender_phone'     => $customer->phone,
            'sender_address'   => 'Jl. Darmo No. 1',
            'receiver_name'    => 'Penerima',
            'receiver_phone'   => '081299990000',
            'receiver_address' => 'Jl. Ahmad Yani No. 2',
            'status'           => $status === 'pending' ? 'confirmed' : $status,
            'shipping_cost'    => 20000,
            'total_cost'       => 20000,
        ]);

        $order = Order::where('order_number', $shipment->shipment_number)->first();
        if ($order) {
            $order->update([
                'courier_id'       => $courier?->id,
                'pickup_latitude'  => -7.2890,
                'pickup_longitude' => 112.7340,
                'delivery_latitude' => -7.3100,
                'delivery_longitude' => 112.7350,
                'package_description' => 'Dokumen',
                'package_weight'   => 1.5,
                'vehicle'          => 'motor',
                'price'            => 20000,
                'status'           => $status,
            ]);
        } else {
            $order = Order::create([
                'order_number'        => $num,
                'courier_id'          => $courier?->id,
                'customer_name'       => $customer->name,
                'customer_phone'      => $customer->phone,
                'pickup_address'      => 'Jl. Darmo No. 1',
                'pickup_latitude'     => -7.2890,
                'pickup_longitude'    => 112.7340,
                'delivery_address'    => 'Jl. Ahmad Yani No. 2',
                'delivery_latitude'   => -7.3100,
                'delivery_longitude'  => 112.7350,
                'package_description' => 'Dokumen',
                'package_weight'      => 1.5,
                'vehicle'             => 'motor',
                'price'               => 20000,
                'status'              => $status,
            ]);
        }

        return [$customer, $shipment, $order];
    }

    public function test_online_idle_courier_receives_offers_and_becomes_on_delivery_upon_accepting(): void
    {
        [$courierUser, $courier] = $this->createCourier(isActive: true, isVerified: true, suffix: 'A');
        [, , $order1] = $this->createOrderWithShipment();
        [, , $order2] = $this->createOrderWithShipment();

        Sanctum::actingAs($courierUser);

        // 1. Online tanpa pesanan aktif -> dapat melihat penawaran
        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonPath('meta.eligible_for_orders', true)
            ->assertJsonPath('meta.work_status', 'online')
            ->assertJsonCount(2, 'data.data');

        // Push notification gate mengizinkan
        $availability = app(CourierAvailabilityService::class);
        $this->assertTrue($availability->shouldSendPushOfferNotification($courier, $order1));
        $this->assertNotNull($availability->buildNewOrderOfferNotificationPayload($courier, $order1));

        // 2. Terima order 1 -> berhasil dan status kurir otomatis berubah ke on_delivery
        $this->patchJson('/api/update-status-order', [
            'order_id' => $order1->id,
            'status'   => 'assigned',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('courier_status.work_status', 'on_delivery')
            ->assertJsonPath('courier_status.can_accept_orders', false);

        $this->assertSame('on_delivery', $courier->fresh()->work_status);
        $this->assertFalse($availability->shouldSendPushOfferNotification($courier, $order2));
        $this->assertNull($availability->buildNewOrderOfferNotificationPayload($courier, $order2));

        // 3. Saat on_delivery, available orders harus kosong & menerima order 2 ditolak (409)
        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.eligible_for_orders', false)
            ->assertJsonPath('meta.work_status', 'on_delivery');

        $this->patchJson('/api/update-status-order', [
            'order_id' => $order2->id,
            'status'   => 'assigned',
        ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'courier_busy');

        $this->assertSame('pending', $order2->fresh()->status);
        $this->assertNull($order2->fresh()->courier_id);
    }

    public function test_busy_status_persists_across_all_active_delivery_stages_and_blocks_manual_override(): void
    {
        [$courierUser, $courier] = $this->createCourier(isActive: true, isVerified: true, suffix: 'B');
        [, , $order1] = $this->createOrderWithShipment(status: 'assigned', courier: $courier);
        [, , $order2] = $this->createOrderWithShipment();

        Sanctum::actingAs($courierUser);

        foreach (['assigned', 'picking_up', 'picked_up', 'delivering'] as $activeStatus) {
            $order1->update(['status' => $activeStatus]);

            $this->getJson('/api/courier/details')
                ->assertOk()
                ->assertJsonPath('data.work_status', 'on_delivery')
                ->assertJsonPath('data.can_accept_orders', false);

            $this->getJson('/api/orders/available')
                ->assertOk()
                ->assertJsonPath('data', [])
                ->assertJsonPath('meta.eligible_for_orders', false);

            // Upaya paksa ubah status ke online atau offline selama mengantar harus ditolak
            $this->putJson('/api/courier/status', ['status' => 'online'])
                ->assertStatus(409)
                ->assertJsonPath('error_code', 'courier_has_active_order');

            $this->putJson('/api/courier/status', ['status' => 'offline'])
                ->assertStatus(409)
                ->assertJsonPath('error_code', 'courier_has_active_order');

            $this->patchJson('/api/update-status-order', [
                'order_id' => $order2->id,
                'status'   => 'assigned',
            ])->assertStatus(409);
        }

        // Pastikan status picked_up ikut terhitung sebagai active_orders di stats
        $order1->update(['status' => 'picked_up']);
        $this->getJson('/api/courier/stats')
            ->assertOk()
            ->assertJsonPath('data.active_orders', 1);
    }

    public function test_offline_courier_cannot_see_receive_or_accept_orders_or_push_notifications(): void
    {
        [$courierUser, $courier] = $this->createCourier(isActive: true, isVerified: true, suffix: 'C');
        [, , $order] = $this->createOrderWithShipment();

        Sanctum::actingAs($courierUser);

        // Sebelumnya online dan sempat melihat penawaran yang belum diterima
        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');

        // Courier beralih ke OFFLINE saat ada penawaran yang belum diterima
        $this->putJson('/api/courier/status', ['status' => 'offline'])
            ->assertOk()
            ->assertJsonPath('data.work_status', 'offline')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.can_accept_orders', false);

        $this->assertFalse($courier->fresh()->is_active);
        $this->assertSame('offline', $courier->fresh()->work_status);

        // Tidak ada penawaran lagi & push notification diblokir
        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.eligible_for_orders', false)
            ->assertJsonPath('meta.work_status', 'offline');

        $availability = app(CourierAvailabilityService::class);
        $this->assertFalse($availability->shouldSendPushOfferNotification($courier->fresh(), $order));
        $this->assertNull($availability->buildNewOrderOfferNotificationPayload($courier->fresh(), $order));

        // Penawaran lama mencoba diterima setelah Offline -> harus ditolak 409
        $this->patchJson('/api/update-status-order', [
            'order_id' => $order->id,
            'status'   => 'assigned',
        ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'courier_offline');

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->courier_id);
    }

    public function test_unverified_courier_cannot_go_online_or_accept_orders(): void
    {
        [$courierUser, $courier] = $this->createCourier(isActive: false, isVerified: false, suffix: 'U');
        [, , $order] = $this->createOrderWithShipment();

        Sanctum::actingAs($courierUser);

        $this->putJson('/api/courier/status', ['status' => 'online'])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'courier_not_verified');

        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.reason_code', 'courier_not_verified');

        $this->patchJson('/api/update-status-order', [
            'order_id' => $order->id,
            'status'   => 'assigned',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'courier_not_verified');

        $this->assertFalse($courier->fresh()->is_active);
    }

    public function test_courier_returning_to_online_after_delivered_can_accept_new_orders(): void
    {
        [$courierUser, $courier] = $this->createCourier(isActive: true, isVerified: true, suffix: 'D');
        [, , $order1] = $this->createOrderWithShipment();
        [, , $order2] = $this->createOrderWithShipment();

        Sanctum::actingAs($courierUser);

        $this->patchJson('/api/update-status-order', [
            'order_id' => $order1->id,
            'status'   => 'assigned',
        ])->assertOk();

        $this->patchJson('/api/update-status-order', ['order_id' => $order1->id, 'status' => 'picking_up'])->assertOk();
        $this->patchJson('/api/update-status-order', ['order_id' => $order1->id, 'status' => 'picked_up'])->assertOk();
        $this->patchJson('/api/update-status-order', ['order_id' => $order1->id, 'status' => 'delivering'])->assertOk();

        // Selesaikan pengiriman -> status kurir kembali online & order2 kembali ditawarkan
        $this->patchJson('/api/update-status-order', [
            'order_id' => $order1->id,
            'status'   => 'delivered',
        ])
            ->assertOk()
            ->assertJsonPath('courier_status.work_status', 'online')
            ->assertJsonPath('courier_status.can_accept_orders', true);

        $this->assertSame('online', $courier->fresh()->work_status);

        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonPath('meta.eligible_for_orders', true)
            ->assertJsonCount(1, 'data.data');

        $this->patchJson('/api/update-status-order', [
            'order_id' => $order2->id,
            'status'   => 'assigned',
        ])->assertOk();
    }

    public function test_legal_cancellation_updates_courier_status_according_to_shift_preference(): void
    {
        [$courierUser, $courier] = $this->createCourier(isActive: true, isVerified: true, suffix: 'E');
        [, , $order1] = $this->createOrderWithShipment();
        [, $shipment2, $order2] = $this->createOrderWithShipment();

        Sanctum::actingAs($courierUser);

        // Skenario A: Kurir online membatalkan penugasan secara sah sebelum pickup -> kembali Online
        $this->patchJson('/api/update-status-order', [
            'order_id' => $order1->id,
            'status'   => 'assigned',
        ])->assertOk();

        $this->postJson('/api/orders/cancel-assignment', [
            'order_id' => $order1->id,
            'reason'   => 'vehicle_issue',
        ])
            ->assertOk()
            ->assertJsonPath('action_taken', 'released')
            ->assertJsonPath('courier_status.work_status', 'online')
            ->assertJsonPath('courier_status.can_accept_orders', true);

        // Skenario B: Customer membatalkan order saat kurir is_active=true -> kurir kembali Online;
        // jika shift kurir dinonaktifkan (is_active=false), kurir kembali ke Offline.
        $this->patchJson('/api/update-status-order', [
            'order_id' => $order2->id,
            'status'   => 'assigned',
        ])->assertOk();

        // Admin/sistem menonaktifkan shift saat kurir sedang dalam penugasan -> tetap on_delivery sampai dibatalkan
        $courier->update(['is_active' => false]);
        $this->assertSame('on_delivery', $courier->fresh()->effectiveWorkStatus());

        $customer = User::findOrFail($shipment2->user_id);
        Sanctum::actingAs($customer);
        $this->postJson('/api/shipments/' . $shipment2->shipment_number . '/cancel', [
            'reason' => 'Berubah pikiran',
        ])->assertOk();

        $this->assertSame('offline', $courier->fresh()->work_status);
        $this->assertFalse($courier->fresh()->isAvailableForOrders());

        // Skenario C: Admin menyetujui permintaan pembatalan penugasan saat picking_up -> kurir kembali ke shift-nya (online)
        [, $courier3User] = [$courier, null];
        [$user3, $courier3] = $this->createCourier(isActive: true, isVerified: true, suffix: 'ADM');
        [, , $order3] = $this->createOrderWithShipment(status: 'picking_up', courier: $courier3);
        app(CourierAvailabilityService::class)->syncPersistedStatus($courier3->fresh());
        $this->assertSame('on_delivery', $courier3->fresh()->work_status);

        Sanctum::actingAs($user3);
        $this->postJson('/api/orders/cancel-assignment', [
            'order_id' => $order3->id,
            'reason'   => 'personal_emergency',
        ])->assertOk()->assertJsonPath('action_taken', 'pending_approval');

        $adminController = app(\App\Http\Controllers\AdminController::class);
        $adminController->handleCourierCancelDecision(
            new Request(['decision' => 'approve', 'admin_notes' => 'Disetujui']),
            $order3->fresh(),
            app(\App\Services\TrackingService::class)
        );

        $this->assertSame('pending', $order3->fresh()->status);
        $this->assertNull($order3->fresh()->courier_id);
        $this->assertSame('online', $courier3->fresh()->work_status);
        $this->assertTrue($courier3->fresh()->isAvailableForOrders());
    }

    public function test_atomic_locking_prevents_multiple_active_orders_or_double_claim(): void
    {
        [$user1, $courier1] = $this->createCourier(isActive: true, isVerified: true, suffix: 'R1');
        [$user2, $courier2] = $this->createCourier(isActive: true, isVerified: true, suffix: 'R2');
        [, , $orderA] = $this->createOrderWithShipment();
        [, , $orderB] = $this->createOrderWithShipment();

        // Dua kurir mencoba mengklaim order yang sama -> hanya satu yang berhasil
        Sanctum::actingAs($user1);
        $this->patchJson('/api/update-status-order', ['order_id' => $orderA->id, 'status' => 'assigned'])
            ->assertOk();

        Sanctum::actingAs($user2);
        $this->patchJson('/api/update-status-order', ['order_id' => $orderA->id, 'status' => 'assigned'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'order_unavailable');

        // Kurir 1 mencoba menerima order kedua -> ditolak 409 courier_busy, hanya 1 pesanan aktif
        Sanctum::actingAs($user1);
        $this->patchJson('/api/update-status-order', ['order_id' => $orderB->id, 'status' => 'assigned'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'courier_busy');

        $this->assertSame(
            1,
            Order::where('courier_id', $courier1->id)->whereIn('status', Order::ACTIVE_STATUSES)->count()
        );
        $this->assertNull($orderB->fresh()->courier_id);
    }

    public function test_ci_work_auto_assign_skips_offline_and_busy_couriers_and_assigns_at_most_one_order(): void
    {
        [, $onlineCourier] = $this->createCourier(isActive: true, isVerified: true, suffix: 'ON');
        [, $offlineCourier] = $this->createCourier(isActive: false, isVerified: true, suffix: 'OFF');
        [, $busyCourier] = $this->createCourier(isActive: true, isVerified: true, suffix: 'BSY');

        // Beri busyCourier satu pesanan aktif terlebih dahulu
        [, , $existingActive] = $this->createOrderWithShipment(status: 'delivering', courier: $busyCourier);
        [, , $pending1] = $this->createOrderWithShipment();
        [, , $pending2] = $this->createOrderWithShipment();

        $controller = app(CiWorkController::class);
        $response = $controller->autoAssign(new Request());
        $payload = $response->getData(true);

        // Hanya 1 order ditugaskan ke 1 kurir online yang tersedia; order kedua tetap pending
        $this->assertTrue($payload['ok']);
        $this->assertSame(1, $payload['assigned']);

        $this->assertSame(1, Order::where('courier_id', $onlineCourier->id)->whereIn('status', Order::ACTIVE_STATUSES)->count());
        $this->assertSame(0, Order::where('courier_id', $offlineCourier->id)->count());
        $this->assertSame(1, Order::where('courier_id', $busyCourier->id)->count());
        $this->assertSame($existingActive->id, Order::where('courier_id', $busyCourier->id)->value('id'));
        $this->assertSame(1, Order::whereIn('id', [$pending1->id, $pending2->id])->where('status', 'pending')->count());
    }
}
