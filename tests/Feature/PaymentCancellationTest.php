<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pembatalan pembayaran checkout pesanan menggunakan Xendit API v3 +
 * kompatibilitas penutupan aman untuk data historis non-Xendit.
 */
class PaymentCancellationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->dropSchema();
        $this->createSchema();
    }

    private function dropSchema(): void
    {
        foreach (['payments', 'payment_webhooks', 'shipment_logs', 'orders', 'shipments', 'withdrawals', 'settings', 'roles', 'model_has_roles', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone', 20)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('shipment_id')->nullable();
            $table->string('payment_id');
            $table->string('payment_type');
            $table->string('channel_code')->nullable();
            $table->integer('amount');
            $table->string('status');
            $table->string('va_number')->nullable();
            $table->text('qr_string')->nullable();
            $table->string('payment_url')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('callback_data')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->index();
            $table->string('event_type', 80)->index();
            $table->string('external_id', 120)->nullable()->index();
            $table->string('provider_reference', 120)->nullable()->index();
            $table->string('webhook_hash', 64)->unique();
            $table->boolean('signature_valid')->default(false);
            $table->string('processing_status', 32)->default('received')->index();
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('courier_id')->nullable();
            $table->string('payment_status')->default('unpaid');
            $table->boolean('scheduled_pickup')->default(false);
            $table->timestamp('pickup_scheduled_at')->nullable();
            $table->timestamp('pickup_started_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by', 50)->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('cancelled_from_status', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('shipment_number')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->string('sender_phone', 20)->nullable();
            $table->string('status')->default('pending');
            $table->boolean('scheduled_pickup')->default(false);
            $table->timestamp('pickup_scheduled_at')->nullable();
            $table->string('pickup_time')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by', 50)->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('cancelled_from_status', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id');
            $table->unsignedBigInteger('courier_id')->nullable();
            $table->string('status');
            $table->string('location')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('accuracy', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    private function user(): User
    {
        return User::create([
            'name' => 'Tester',
            'email' => 'tester-cancel@example.com',
            'password' => bcrypt('secret'),
            'phone' => '08123456789',
        ]);
    }

    private function configureXendit(): void
    {
        Setting::set('payment_wallet_mode', 'sandbox', 'payment_wallet');
        Setting::set('payment_active_provider', 'xendit', 'payment_wallet');
        Setting::set('xendit_enabled', '1', 'payment_wallet');
        Setting::set('xendit_base_url', 'https://api.xendit.co', 'payment_wallet');
        Setting::set('xendit_secret_key', 'xnd_development_cancel_12345', 'payment_wallet');
        Setting::set('xendit_callback_token', 'cb-cancel-token-123', 'payment_wallet');
    }

    private function payment(User $user, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'user_id' => $user->id,
            'payment_id' => 'pr-cancel-001',
            'payment_type' => 'bank_transfer',
            'channel_code' => 'BNI_VIRTUAL_ACCOUNT',
            'amount' => 13300,
            'status' => 'pending',
            'va_number' => '8808123456799',
            'metadata' => [
                'provider' => 'xendit',
                'xendit_reference_id' => 'CC-94-20261030120000',
                'payment_mode' => 'direct',
            ],
        ], $overrides));
    }

    private function shipmentFor(User $user): Shipment
    {
        return Shipment::create([
            'user_id' => $user->id,
            'shipment_number' => 'RESI-CANCEL-1',
            'tracking_number' => 'RESI-CANCEL-1',
            'status' => 'pending',
        ]);
    }

    public function test_pending_xendit_payment_cancelled_via_v3_cancel_api(): void
    {
        $this->configureXendit();
        $user = $this->user();
        $payment = $this->payment($user);

        Http::fake([
            'https://api.xendit.co/v3/payment_requests/pr-cancel-001' => Http::response([
                'payment_request_id' => 'pr-cancel-001',
                'status' => 'REQUIRES_ACTION',
            ], 200),
            'https://api.xendit.co/v3/payment_requests/pr-cancel-001/cancel' => Http::response([
                'payment_request_id' => 'pr-cancel-001',
                'status' => 'CANCELED',
            ], 200),
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/payment/cancel', ['payment_id' => $payment->payment_id])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('payment.status', 'canceled')
            ->assertJsonPath('payment.provider', 'xendit')
            ->assertJsonPath('payment.provider_cancel_api', true)
            ->assertJsonPath('payment.reference_id', 'CC-94-20261030120000');

        $fresh = $payment->fresh();
        $this->assertSame('canceled', $fresh->status);
        $this->assertSame('provider_canceled', $fresh->metadata['cancellation']['result']);
        $this->assertTrue($fresh->metadata['cancellation']['provider_cancel_api']);
    }

    public function test_paid_payment_requires_refund_and_is_not_cancelled(): void
    {
        $this->configureXendit();
        $user = $this->user();
        $payment = $this->payment($user, ['order_id' => 5]);

        Http::fake([
            'https://api.xendit.co/v3/payment_requests/pr-cancel-001' => Http::response([
                'payment_request_id' => 'pr-cancel-001',
                'status' => 'SUCCEEDED',
            ], 200),
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/payment/cancel', ['payment_id' => $payment->payment_id])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'payment_already_paid');

        $fresh = $payment->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertNotNull($fresh->paid_at);
        $this->assertTrue($fresh->metadata['cancellation']['refund_required']);
        $this->assertSame('requires_refund', $fresh->metadata['cancellation']['result']);
    }

    public function test_expired_transaction_syncs_local_status(): void
    {
        $this->configureXendit();
        $user = $this->user();
        $payment = $this->payment($user);

        Http::fake([
            'https://api.xendit.co/v3/payment_requests/pr-cancel-001' => Http::response([
                'payment_request_id' => 'pr-cancel-001',
                'status' => 'EXPIRED',
            ], 200),
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/payment/cancel', ['payment_id' => $payment->payment_id])
            ->assertOk()
            ->assertJsonPath('payment.status', 'expired');

        $this->assertSame('expired', $payment->fresh()->status);
    }

    public function test_unknown_provider_status_changes_nothing(): void
    {
        $this->configureXendit();
        $user = $this->user();
        $payment = $this->payment($user);

        Http::fake([
            'https://api.xendit.co/v3/payment_requests/pr-cancel-001' => Http::response([
                'message' => 'Gateway error',
            ], 500),
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/payment/cancel', ['payment_id' => $payment->payment_id])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'payment_status_unknown');

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_shipment_cancel_blocked_when_paid_or_unknown_and_cancels_when_pending(): void
    {
        $this->configureXendit();
        $user = $this->user();
        $shipment = $this->shipmentFor($user);
        $payment = $this->payment($user, [
            'shipment_id' => $shipment->id,
            'payment_id' => 'pr-ship-001',
        ]);

        Http::fake([
            'https://api.xendit.co/v3/payment_requests/pr-ship-001' => Http::sequence()
                ->push(['status' => 'SUCCEEDED'], 200)
                ->push(['status' => 'REQUIRES_ACTION'], 200),
            'https://api.xendit.co/v3/payment_requests/pr-ship-001/cancel' => Http::response([
                'status' => 'CANCELED',
            ], 200),
        ]);

        Sanctum::actingAs($user);

        // First call -> paid at Xendit -> 422
        $this->postJson('/api/shipments/RESI-CANCEL-1/cancel')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'payment_already_paid');
        $this->assertSame('pending', $shipment->fresh()->status);

        // Fresh pending shipment & payment -> pending at Xendit -> cancelled via /cancel
        $pendingShipment = Shipment::create([
            'user_id' => $user->id,
            'shipment_number' => 'RESI-CANCEL-2',
            'tracking_number' => 'RESI-CANCEL-2',
            'status' => 'pending',
        ]);
        $pendingPayment = $this->payment($user, [
            'shipment_id' => $pendingShipment->id,
            'payment_id' => 'pr-ship-002',
        ]);

        Http::fake([
            'https://api.xendit.co/v3/payment_requests/pr-ship-002' => Http::response([
                'status' => 'REQUIRES_ACTION',
            ], 200),
            'https://api.xendit.co/v3/payment_requests/pr-ship-002/cancel' => Http::response([
                'status' => 'CANCELED',
            ], 200),
        ]);

        $this->postJson('/api/shipments/RESI-CANCEL-2/cancel')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('payment.status', 'canceled')
            ->assertJsonPath('payment.cancellation_result', 'provider_canceled');

        $this->assertSame('cancelled', $pendingShipment->fresh()->status);
        $this->assertSame('canceled', $pendingPayment->fresh()->status);
    }

    public function test_historical_non_xendit_payment_closes_locally_without_calling_retired_provider(): void
    {
        $user = $this->user();
        $historicalPayment = $this->payment($user, [
            'payment_id' => 'HIST-IPAYMU-99001',
            'metadata' => [
                'provider' => 'ipaymu',
                'ipaymu_reference_id' => 'CC-HIST-99001',
            ],
        ]);

        Http::fake(); // zero external calls expected for historical records

        Sanctum::actingAs($user);
        $this->postJson('/api/payment/cancel', ['payment_id' => 'HIST-IPAYMU-99001'])
            ->assertOk()
            ->assertJsonPath('payment.status', 'canceled')
            ->assertJsonPath('payment.provider_cancel_api', false);

        Http::assertNothingSent();
        $this->assertSame('canceled', $historicalPayment->fresh()->status);
    }
}
