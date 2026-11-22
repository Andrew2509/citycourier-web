<?php

namespace Tests\Feature;

use App\Models\Order;
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
 * Integrasi Checkout Pembayaran Pesanan — Xendit Only (Payments API v3).
 */
class XenditCheckoutPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    private function createSchema(): void
    {
        foreach (['payments', 'payment_webhooks', 'orders', 'shipments', 'settings', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('role')->default('customer');
            $table->rememberToken();
            $table->timestamps();
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

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('payment_status')->default('unpaid');
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('shipment_number')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_phone', 20)->nullable();
            $table->string('status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->timestamps();
        });
    }

    private function createUser(): User
    {
        return User::create([
            'name' => 'Rina Pelanggan',
            'email' => 'rina@example.com',
            'password' => bcrypt('secret'),
            'phone' => '081234567890',
        ]);
    }

    private function configureXendit(string $secret = 'xnd_development_checkout_123', string $cbToken = 'wh_cb_checkout_123'): void
    {
        Setting::set('payment_wallet_mode', 'sandbox', 'payment_wallet');
        Setting::set('payment_active_provider', 'xendit', 'payment_wallet');
        Setting::set('xendit_enabled', '1', 'payment_wallet');
        Setting::set('xendit_base_url', 'https://api.xendit.co', 'payment_wallet');
        Setting::set('xendit_secret_key', $secret, 'payment_wallet');
        Setting::set('xendit_callback_token', $cbToken, 'payment_wallet');
    }

    public function test_methods_returns_xendit_channels_without_doku_or_ipaymu(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/payment/methods')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('xendit_enabled', true)
            ->assertJsonPath('gateway', 'xendit');

        $codes = array_column($response->json('data'), 'code');
        $this->assertContains('QRIS', $codes);
        $this->assertContains('BCA_VIRTUAL_ACCOUNT', $codes);
        $this->assertContains('ID_DANA', $codes);
        $this->assertContains('ID_OVO', $codes);
        $this->assertContains('ID_SHOPEEPAY', $codes);
        $this->assertContains('ID_LINKAJA', $codes);
    }

    public function test_production_methods_fails_cleanly_when_xendit_unconfigured(): void
    {
        Setting::set('payment_wallet_mode', 'production', 'payment_wallet');
        config([
            'payment_wallet.mode' => 'production',
            'payment_wallet.xendit.secret_key' => '',
        ]);

        Sanctum::actingAs($this->createUser());
        $this->getJson('/api/payment/methods')
            ->assertStatus(502)
            ->assertJsonPath('success', false);
    }

    public function test_create_va_qris_and_ewallet_payments_via_xendit_v3_with_idempotency(): void
    {
        $this->configureXendit();
        $user = $this->createUser();
        $shipment = Shipment::create([
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_phone' => $user->phone,
            'status' => 'pending',
        ]);

        Http::fake([
            'https://api.xendit.co/v3/payment_requests' => Http::sequence()
                ->push([
                    'id' => 'pr-va-001',
                    'status' => 'REQUIRES_ACTION',
                    'actions' => [
                        ['type' => 'PRESENT_TO_CUSTOMER', 'value' => '8808001234567'],
                    ],
                ], 200)
                ->push([
                    'id' => 'pr-qris-001',
                    'status' => 'REQUIRES_ACTION',
                    'actions' => [
                        ['type' => 'PRESENT_TO_CUSTOMER', 'value' => '0002010102122660...QRIS'],
                    ],
                ], 200)
                ->push([
                    'id' => 'pr-dana-001',
                    'status' => 'REQUIRES_ACTION',
                    'actions' => [
                        ['type' => 'REDIRECT_CUSTOMER', 'value' => 'https://checkout.xendit.co/ewallet/pr-dana-001'],
                    ],
                ], 200),
        ]);

        Sanctum::actingAs($user);

        // 1. Virtual Account + Idempotency
        $vaRes = $this->postJson('/api/payment/create', [
            'shipment_id' => (string) $shipment->id,
            'payment_type' => 'virtual_account',
            'channel_code' => 'BCA',
            'amount' => 45000,
            'idempotency_key' => 'IDEM-VA-001',
        ]);

        $vaRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.provider', 'xendit')
            ->assertJsonPath('data.payment_id', 'pr-va-001')
            ->assertJsonPath('data.va_number', '8808001234567');

        // Re-sending same idempotency_key returns existing record (status 200, no duplicate Xendit call)
        $this->postJson('/api/payment/create', [
            'shipment_id' => (string) $shipment->id,
            'payment_type' => 'virtual_account',
            'channel_code' => 'BCA',
            'amount' => 45000,
            'idempotency_key' => 'IDEM-VA-001',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment_id', 'pr-va-001')
            ->assertJsonPath('data.idempotent_replay', true);

        // 2. QRIS
        $this->postJson('/api/payment/create', [
            'shipment_id' => (string) $shipment->id,
            'payment_type' => 'qris',
            'channel_code' => 'QRIS',
            'amount' => 20000,
            'idempotency_key' => 'IDEM-QRIS-001',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.payment_id', 'pr-qris-001')
            ->assertJsonPath('data.qr_string', '0002010102122660...QRIS');

        // 3. E-Wallet DANA
        $this->postJson('/api/payment/create', [
            'shipment_id' => (string) $shipment->id,
            'payment_type' => 'ewallet',
            'channel_code' => 'ID_DANA',
            'amount' => 30000,
            'idempotency_key' => 'IDEM-DANA-001',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.payment_id', 'pr-dana-001')
            ->assertJsonPath('data.payment_mode', 'redirect')
            ->assertJsonPath('data.payment_url', 'https://checkout.xendit.co/ewallet/pr-dana-001');

        // Ensure secret key never leaked in response JSON
        $this->assertStringNotContainsString('xnd_development_checkout_123', $vaRes->getContent());
        Http::assertSentCount(3);
    }

    public function test_status_sync_and_verified_xendit_webhook_update_order_and_shipment(): void
    {
        $this->configureXendit('xnd_development_sync_123', 'cb_secret_sync_123');
        $user = $this->createUser();
        $order = Order::create(['payment_status' => 'unpaid']);
        $shipment = Shipment::create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'shipment_id' => (string) $shipment->id,
            'payment_id' => 'pr-sync-999',
            'payment_type' => 'qris',
            'channel_code' => 'QRIS',
            'amount' => 50000,
            'status' => 'pending',
            'metadata' => [
                'provider' => 'xendit',
                'xendit_reference_id' => 'CC-SYNC-999',
            ],
        ]);

        // Unverified webhook token MUST be rejected (401)
        $this->postJson('/api/v1/webhooks/xendit/payment', [
            'event' => 'payment.succeeded',
            'data' => [
                'payment_request_id' => 'pr-sync-999',
                'reference_id' => 'CC-SYNC-999',
                'status' => 'SUCCEEDED',
            ],
        ], ['x-callback-token' => 'wrong-token'])
            ->assertStatus(401);

        $this->assertSame('pending', $payment->fresh()->status);

        // Verified Xendit payment webhook marks payment + order + shipment paid
        $this->postJson('/api/v1/webhooks/xendit/payment', [
            'event' => 'payment.succeeded',
            'id' => 'evt-pr-sync-1',
            'data' => [
                'payment_request_id' => 'pr-sync-999',
                'reference_id' => 'CC-SYNC-999',
                'status' => 'SUCCEEDED',
            ],
        ], ['x-callback-token' => 'cb_secret_sync_123'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('confirmed', $shipment->fresh()->status);
    }

    public function test_status_endpoint_queries_xendit_v3_and_rejects_other_users(): void
    {
        $this->configureXendit();
        $owner = $this->createUser();
        $other = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('secret'),
        ]);

        Payment::create([
            'user_id' => $owner->id,
            'payment_id' => 'pr-check-1',
            'payment_type' => 'bank_transfer',
            'channel_code' => 'BCA_VIRTUAL_ACCOUNT',
            'amount' => 75000,
            'status' => 'pending',
            'metadata' => ['provider' => 'xendit'],
        ]);

        Http::fake([
            'https://api.xendit.co/v3/payment_requests/pr-check-1' => Http::response([
                'payment_request_id' => 'pr-check-1',
                'status' => 'SUCCEEDED',
            ], 200),
        ]);

        Sanctum::actingAs($other);
        $this->getJson('/api/payment/pr-check-1/status')->assertStatus(404);

        Sanctum::actingAs($owner);
        $this->getJson('/api/payment/pr-check-1/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_legacy_ipaymu_and_doku_routes_do_not_exist(): void
    {
        $this->postJson('/api/ipaymu/webhook', [])->assertStatus(404);
        $this->get('/api/ipaymu/return')->assertStatus(404);
        $this->postJson('/api/v1/webhooks/doku', [])->assertStatus(404);
    }
}
