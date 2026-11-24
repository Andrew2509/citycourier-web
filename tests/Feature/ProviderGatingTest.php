<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PaymentController;
use App\Models\Courier;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\User;
use App\Modules\Earnings\Models\CourierEarning;
use App\Modules\Ledger\Models\WalletLedgerEntry;
use App\Modules\Payout\Services\DestinationService;
use App\Modules\Payout\Services\PayoutService;
use App\Modules\Wallet\Services\WalletLedgerManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesPaymentWalletSchema;

class ProviderGatingTest extends TestCase
{
    use CreatesPaymentWalletSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runPaymentWalletMigrations();
        $this->setUpSpatiePermissionsForAdminTests();

        if (! Schema::hasTable('dana_connections')) {
            Schema::create('dana_connections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('courier_id')->constrained('couriers')->cascadeOnDelete();
                $table->string('provider')->default('dana');
                $table->string('status')->default('not_connected');
                $table->string('account_name')->nullable();
                $table->string('masked_phone')->nullable();
                $table->text('phone_encrypted')->nullable();
                $table->string('external_id')->nullable();
                $table->string('session_id')->nullable();
                $table->string('state_hash')->nullable();
                $table->timestamp('session_expires_at')->nullable();
                $table->string('provider_reference')->nullable();
                $table->text('access_token')->nullable();
                $table->timestamp('consent_at')->nullable();
                $table->timestamp('bound_at')->nullable();
                $table->timestamp('linked_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    private function createAdmin(): User
    {
        $admin = User::create([
            'name' => 'Admin Gate',
            'email' => 'admin.gate@city.test',
            'role' => 'admin',
            'password' => Hash::make('secret123'),
        ]);
        $admin->assignRole('super-admin');

        return $admin;
    }

    private function createCustomer(string $suffix = '1'): User
    {
        $digits = str_pad((string) crc32($suffix) % 10000, 4, '0', STR_PAD_LEFT);

        return User::create([
            'name' => "Customer Gate {$suffix}",
            'email' => "cust.gate{$suffix}@city.test",
            'firebase_uid' => "google-uid-gate-{$suffix}",
            'phone' => "08123456{$digits}",
            'phone_verified_at' => now(),
            'role' => 'customer',
            'password' => Hash::make('secret123'),
        ]);
    }

    private function createCourier(string $suffix = '1'): array
    {
        $digits = str_pad((string) crc32($suffix) % 10000, 4, '0', STR_PAD_LEFT);

        $user = User::create([
            'name' => "Courier Gate {$suffix}",
            'email' => "cour.gate{$suffix}@city.test",
            'phone' => "08139876{$digits}",
            'phone_verified_at' => now(),
            'role' => 'courier',
            'password' => Hash::make('secret123'),
        ]);

        $courier = Courier::create([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'vehicle_type' => 'motor',
            'vehicle_plate' => 'B1234XY',
            'is_verified' => true,
            'is_active' => true,
        ]);

        return [$user, $courier];
    }

    public function test_settings_api_exposes_all_provider_states_and_reflects_admin_toggles(): void
    {
        $admin = $this->createAdmin();

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.providers.whatsapp', true)
            ->assertJsonPath('data.providers.map', true)
            ->assertJsonPath('data.providers.rajaongkir', true)
            ->assertJsonPath('data.providers.xendit', true)
            ->assertJsonPath('data.providers.tomtom', true)
            ->assertJsonPath('data.active_payment_provider', 'xendit')
            ->assertJsonPath('data.topup_provider_enabled', true)
            ->assertJsonMissingPath('data.providers.doku')
            ->assertJsonMissingPath('data.providers.ipaymu');

        $this->actingAs($admin)
            ->postJson('/admin/providers/xendit/toggle', ['enabled' => 0])
            ->assertOk()
            ->assertJsonPath('enabled', false);

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.providers.xendit', false)
            ->assertJsonPath('data.topup_provider_enabled', false);
    }

    public function test_xendit_off_removes_checkout_methods_and_blocks_payment_create_and_courier_withdrawal(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer('xenditgate');
        [, $courier] = $this->createCourier('xenditgate');

        Sanctum::actingAs($customer);
        $this->getJson('/api/payment/methods')
            ->assertOk()
            ->assertJsonPath('xendit_enabled', true)
            ->assertJsonPath('gateway', 'xendit')
            ->assertJsonCount(10, 'data');

        // Admin turns Xendit OFF
        $this->actingAs($admin)
            ->postJson('/admin/providers/xendit/toggle', ['enabled' => 0])
            ->assertOk()
            ->assertJsonPath('enabled', false);

        Sanctum::actingAs($customer);
        $this->getJson('/api/payment/methods')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('xendit_enabled', false)
            ->assertJsonPath('disabled', true)
            ->assertJsonCount(0, 'data');

        $this->postJson('/api/payment/create', [
            'shipment_id' => '99',
            'payment_type' => 'virtual_account',
            'channel_code' => 'BCA',
            'amount' => 25000,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        Sanctum::actingAs($courier->user);
        $this->getJson('/api/courier/withdrawals/destination-inquiry?phone=081234567890')
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.verification_status', 'unavailable')
            ->assertJsonPath('data.status', 'provider_disabled');

        $this->postJson('/api/courier/withdrawals', [
            'amount' => 50000,
            'idempotency_key' => 'wd-xendit-off-1',
            'phone_number' => '081234567890',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'PROVIDER_DISABLED');
    }

    public function test_xendit_and_doku_off_hide_topup_methods_and_block_topups_and_withdrawals_in_sandbox(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer('gw');

        Config::set('payment_wallet.mode', 'sandbox');
        Setting::set('payment_wallet_mode', 'sandbox', 'payment_wallet');
        Setting::set('payment_active_provider', 'xendit', 'payment_wallet');
        Setting::set('payout_active_provider', 'xendit', 'payment_wallet');

        $walletManager = app(WalletLedgerManager::class);
        $wallet = $walletManager->getOrCreateCustomerWallet($customer);
        $walletManager->creditAvailable(
            $wallet,
            200000,
            WalletLedgerEntry::ACCOUNT_SYSTEM_GATEWAY_CLEARING,
            'topup',
            'TOPUP-GATE-1',
            'idem-gate-1',
            'Saldo uji gate'
        );

        $dest = app(DestinationService::class)->addDestination(
            $customer,
            'customer',
            [
                'destination_type' => 'bank',
                'channel_code' => 'ID_BCA',
                'account_number' => '1234567890',
                'account_holder_name' => 'Customer Gate',
                'is_default' => true,
            ]
        );

        // Xendit OFF -> topup & customer payout blocked even in sandbox (no silent fallback to mock)
        $this->actingAs($admin)
            ->postJson('/admin/providers/xendit/toggle', ['enabled' => 0])
            ->assertOk();

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/customer/wallet')
            ->assertOk()
            ->assertJsonPath('data.topup_provider_enabled', false)
            ->assertJsonPath('data.withdrawal_policy.provider_enabled', false)
            ->assertJsonPath('data.withdrawal_policy.enabled', false);

        $this->getJson('/api/v1/customer/wallet/topups/methods')
            ->assertOk()
            ->assertJsonPath('data.provider_enabled', false)
            ->assertJsonCount(0, 'data.methods');

        $this->postJson('/api/v1/customer/wallet/topups', [
            'amount' => 50000,
            'payment_type' => 'va',
            'channel_code' => 'BCA',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        $this->postJson('/api/v1/customer/wallet/withdrawals', [
            'amount' => 50000,
            'destination_id' => $dest->id,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        // Auto payout skips safely without crashing when payout gateway is OFF
        $autoResult = app(PayoutService::class)->runScheduledCourierAutoPayouts();
        $this->assertSame(0, $autoResult['scheduled_payouts']);

        // Legacy DOKU & iPaymu webhook/provider controls are retired (404) & ProviderResolver enforces Xendit
        $this->postJson('/api/v1/webhooks/doku', ['test' => true])->assertStatus(404);
        $this->actingAs($admin)->postJson('/admin/providers/doku/toggle', ['enabled' => 0])->assertStatus(404);
        $this->actingAs($admin)->postJson('/admin/providers/ipaymu/toggle', ['enabled' => 0])->assertStatus(404);

        Setting::set('payment_active_provider', 'doku', 'payment_wallet');
        Setting::set('payout_active_provider', 'doku', 'payment_wallet');
        $resolver = app(\App\Modules\Payment\Providers\ProviderResolver::class);
        $this->assertSame('xendit', $resolver->activePaymentProviderCode());
        $this->assertSame('xendit', $resolver->activePayoutProviderCode());
    }

    public function test_dana_and_ovo_off_block_courier_endpoints(): void
    {
        [$courierUser] = $this->createCourier('ewallet');

        // Legacy DANA/OVO settings remain gateable for historical compatibility
        Setting::set('dana_enabled', '0', 'dana');

        Sanctum::actingAs($courierUser);

        $this->getJson('/api/courier/dana/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'DISABLED')
            ->assertJsonPath('data.provider_enabled', false)
            ->assertJsonPath('data.connected', false);

        $this->postJson('/api/courier/dana/binding')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        $this->postJson('/api/courier/dana/verify', ['phone_number' => '081234567890'])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        // OVO is OFF by default in Admin Panel
        $this->getJson('/api/courier/ovo/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'DISABLED')
            ->assertJsonPath('data.provider_enabled', false);

        $this->postJson('/api/courier/ovo/connect', [
            'phone_number' => '081234567890',
            'account_name' => 'Kurir Test',
            'consent' => true,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        $this->postJson('/api/courier/ovo/withdraw', [
            'amount' => 50000,
            'idempotency_key' => 'ovo-off-1',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        // Turning OVO ON via setting enables /api/courier/ovo/status
        Setting::set('ovo_enabled', '1', 'ovo');

        Sanctum::actingAs($courierUser);
        $this->getJson('/api/courier/ovo/status')
            ->assertOk()
            ->assertJsonPath('data.provider_enabled', true)
            ->assertJsonPath('data.status', 'NOT_CONNECTED');
    }

    public function test_map_rajaongkir_and_whatsapp_off_block_api_endpoints(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer('wa');

        foreach (['map', 'rajaongkir', 'whatsapp'] as $provider) {
            $this->actingAs($admin)
                ->postJson("/admin/providers/{$provider}/toggle", ['enabled' => 0])
                ->assertOk()
                ->assertJsonPath('enabled', false);
        }

        // Map & Geocoding endpoints blocked with 503 PROVIDER_DISABLED
        $this->postJson('/api/shipping/map/routing', [
            'origin' => '106.8,-6.2',
            'destination' => '106.9,-6.3',
        ])->assertStatus(503)->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        $this->getJson('/api/shipping/map/autocomplete?q=jakarta')
            ->assertStatus(503)->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        $this->postJson('/api/shipping/map/matrix', [
            'coordinates' => ['106.8,-6.2', '106.9,-6.3'],
        ])->assertStatus(503)->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        $this->getJson('/api/shipping/map/styles/osm.json')
            ->assertStatus(503)->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        $this->getJson('/api/geocoding/search?q=bandung')
            ->assertStatus(503)->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        $this->getJson('/api/geocoding/reverse?lat=-6.2&lng=106.8')
            ->assertStatus(503)->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        // RajaOngkir endpoints blocked with 503 PROVIDER_DISABLED
        $this->postJson('/api/shipping/match-region', ['city' => 'Jakarta'])
            ->assertStatus(503)->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        Sanctum::actingAs($customer);
        $this->getJson('/api/shipping/provinces')
            ->assertStatus(503)->assertJsonPath('error_code', 'PROVIDER_DISABLED');
        $this->getJson('/api/shipping/cities?province_id=6')
            ->assertStatus(503)->assertJsonPath('error_code', 'PROVIDER_DISABLED');
        $this->postJson('/api/shipping/cost', [
            'origin' => '151',
            'destination' => '152',
            'weight' => 1000,
            'courier' => 'jne',
        ])->assertStatus(503)->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        // WhatsApp OTP endpoints blocked with 503 PROVIDER_DISABLED
        $this->postJson('/api/request-otp', ['phone' => $customer->phone])
            ->assertStatus(503)->assertJsonPath('code', 'PROVIDER_DISABLED');

        $this->postJson('/api/profile/phone/request-otp', ['phone' => '081999888777'])
            ->assertStatus(503)->assertJsonPath('code', 'PROVIDER_DISABLED');

        $this->postJson('/api/security/pin/request-otp')
            ->assertStatus(503)->assertJsonPath('code', 'PROVIDER_DISABLED');

        $this->postJson('/api/courier/request-phone-otp', ['phone' => '081999888777'])
            ->assertStatus(503)->assertJsonPath('code', 'PROVIDER_DISABLED');
    }

    public function test_courier_v1_manual_withdrawal_works_in_sandbox_and_respects_provider_toggle(): void
    {
        $admin = $this->createAdmin();
        [$courierUser, $courier] = $this->createCourier('v1wd');

        Config::set('payment_wallet.mode', 'sandbox');
        Setting::set('payment_wallet_mode', 'sandbox', 'payment_wallet');
        Setting::set('payout_active_provider', 'xendit', 'payment_wallet');

        $walletManager = app(WalletLedgerManager::class);
        $wallet = $walletManager->getOrCreateCourierWallet($courier);
        $walletManager->creditAvailable(
            $wallet,
            150000,
            WalletLedgerEntry::ACCOUNT_SYSTEM_GATEWAY_CLEARING,
            'earning',
            'EARN-V1-1',
            'idem-earn-v1-1',
            'Saldo uji kurir v1'
        );

        Sanctum::actingAs($courierUser);

        $destId = $this->postJson('/api/v1/courier/wallet/destinations', [
            'destination_type' => 'bank',
            'channel_code' => 'ID_BCA',
            'account_number' => '8888123456',
            'account_holder_name' => 'Courier Gate V1',
            'is_default' => true,
        ])
            ->assertStatus(201)
            ->json('data.id');

        $this->getJson('/api/v1/courier/wallet')
            ->assertOk()
            ->assertJsonPath('data.available_balance', 150000)
            ->assertJsonPath('data.payout_policy.provider_enabled', true);

        $this->postJson('/api/v1/courier/wallet/withdrawals', [
            'amount' => 50000,
            'destination_id' => $destId,
            'idempotency_key' => 'cur-wd-sandbox-1',
        ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', 50000)
            ->assertJsonPath('data.mode', 'sandbox');

        // Idempotent replay returns 200 without double deduction
        $this->postJson('/api/v1/courier/wallet/withdrawals', [
            'amount' => 50000,
            'destination_id' => $destId,
            'idempotency_key' => 'cur-wd-sandbox-1',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.idempotent_replay', true);

        // Turn Xendit OFF -> GET /api/v1/courier/wallet still loads cleanly & reports provider_enabled=false,
        // while POST /withdrawals returns 403 PROVIDER_DISABLED
        $this->actingAs($admin)
            ->postJson('/admin/providers/xendit/toggle', ['enabled' => 0])
            ->assertOk();

        Sanctum::actingAs($courierUser);

        $this->getJson('/api/v1/courier/wallet')
            ->assertOk()
            ->assertJsonPath('data.payout_policy.provider_enabled', false);

        $this->postJson('/api/v1/courier/wallet/withdrawals', [
            'amount' => 30000,
            'destination_id' => $destId,
            'idempotency_key' => 'cur-wd-sandbox-2',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'PROVIDER_DISABLED');
    }

    public function test_realtime_destination_inquiry_uses_honest_xendit_statuses(): void
    {
        $customer = $this->createCustomer('inq');

        Sanctum::actingAs($customer);

        // 1. Default Xendit Payouts v2 (without Name Validator enabled): returns `unverified`, never fake names
        $this->postJson('/api/v1/customer/wallet/destinations/inquiry', [
            'destination_type' => 'bank',
            'channel_code' => 'ID_BCA',
            'account_number' => '1234567890',
        ])
            ->assertOk()
            ->assertJsonPath('data.supported', false)
            ->assertJsonPath('data.found', false)
            ->assertJsonPath('data.verification_status', 'unverified')
            ->assertJsonPath('data.can_input_manually', true)
            ->assertJsonPath('data.account_holder_name', null);

        $this->postJson('/api/v1/customer/wallet/destinations/inquiry', [
            'destination_type' => 'ewallet',
            'channel_code' => 'ID_DANA',
            'account_number' => $customer->phone,
        ])
            ->assertOk()
            ->assertJsonPath('data.supported', false)
            ->assertJsonPath('data.found', false)
            ->assertJsonPath('data.verification_status', 'unverified')
            ->assertJsonPath('data.can_input_manually', true)
            ->assertJsonPath('data.account_holder_name', null);

        // 2. Unsupported channel returns `unavailable` and rejects manual inquiry
        $this->postJson('/api/v1/customer/wallet/destinations/inquiry', [
            'destination_type' => 'bank',
            'channel_code' => 'ID_BANK_TIDAK_ADA',
            'account_number' => '1234567890',
        ])
            ->assertOk()
            ->assertJsonPath('data.supported', false)
            ->assertJsonPath('data.found', false)
            ->assertJsonPath('data.verification_status', 'unavailable')
            ->assertJsonPath('data.can_input_manually', false)
            ->assertJsonPath('data.account_holder_name', null);

        // 3. When Xendit Data Services Name Validator is officially enabled: returns `auto_verified` or `verification_failed`
        Setting::set('payment_wallet_mode', 'production', 'payment_wallet');
        Setting::set('xendit_secret_key', 'xnd_production_test_key_12345', 'payment_wallet');
        Setting::set('xendit_enabled', '1', 'payment_wallet');
        Config::set('payment_wallet.xendit.name_validator', [
            'enabled' => true,
            'base_url' => 'https://api.xendit.co',
            'secret_key' => 'xnd_production_test_key_12345',
            'bank_path' => '/v2/validators/bank_accounts',
            'ewallet_path' => '/v2/validators/ewallets',
        ]);

        Http::fake([
            'https://api.xendit.co/v2/validators/bank_accounts' => Http::sequence()
                ->push(['status' => 'COMPLETED', 'account_holder_name' => 'Rina Pemilik Asli'], 200)
                ->push(['status' => 'FAILED'], 404),
            'https://api.xendit.co/v2/validators/ewallets' => Http::response([
                'status' => 'COMPLETED',
                'account_holder_name' => 'Budi Ewallet Asli',
            ], 200),
        ]);

        // Valid bank lookup -> auto_verified
        $this->postJson('/api/v1/customer/wallet/destinations/inquiry', [
            'destination_type' => 'bank',
            'channel_code' => 'ID_BCA',
            'account_number' => '1234567890',
        ])
            ->assertOk()
            ->assertJsonPath('data.supported', true)
            ->assertJsonPath('data.found', true)
            ->assertJsonPath('data.verification_status', 'auto_verified')
            ->assertJsonPath('data.can_input_manually', false)
            ->assertJsonPath('data.account_holder_name', 'RINA PEMILIK ASLI');

        // Failed lookup -> verification_failed
        $this->postJson('/api/v1/customer/wallet/destinations/inquiry', [
            'destination_type' => 'bank',
            'channel_code' => 'ID_BCA',
            'account_number' => '0000000000',
        ])
            ->assertOk()
            ->assertJsonPath('data.supported', true)
            ->assertJsonPath('data.found', false)
            ->assertJsonPath('data.verification_status', 'verification_failed')
            ->assertJsonPath('data.can_input_manually', true)
            ->assertJsonPath('data.account_holder_name', null);

        // Courier e-wallet lookup -> auto_verified
        [, $courier] = $this->createCourier('inq');
        Sanctum::actingAs($courier->user);

        $this->postJson('/api/v1/courier/wallet/destinations/inquiry', [
            'destination_type' => 'ewallet',
            'channel_code' => 'ID_DANA',
            'account_number' => '081234567890',
        ])
            ->assertOk()
            ->assertJsonPath('data.supported', true)
            ->assertJsonPath('data.found', true)
            ->assertJsonPath('data.verification_status', 'auto_verified')
            ->assertJsonPath('data.account_holder_name', 'BUDI EWALLET ASLI');
    }
}
