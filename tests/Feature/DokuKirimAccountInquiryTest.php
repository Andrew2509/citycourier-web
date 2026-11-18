<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Payment\Services\GatewayCredentialsResolver;
use App\Modules\Payout\Services\DokuKirimAccountInquiryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesPaymentWalletSchema;

class DokuKirimAccountInquiryTest extends TestCase
{
    use CreatesPaymentWalletSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runPaymentWalletMigrations();
        $this->setUpSpatiePermissionsForAdminTests();
        Cache::flush();
    }

    private function createCustomer(): User
    {
        return User::create([
            'name' => 'Pelanggan Kirim',
            'email' => 'kirim.cust@city.test',
            'phone' => '081111111111',
            'phone_verified_at' => now(),
            'role' => 'customer',
            'password' => Hash::make('secret123'),
        ]);
    }

    private function createCourier(): User
    {
        $user = User::create([
            'name' => 'Kurir Kirim',
            'email' => 'kirim.courier@city.test',
            'phone' => '082222222222',
            'phone_verified_at' => now(),
            'role' => 'courier',
            'password' => Hash::make('secret123'),
        ]);

        Courier::create([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'vehicle_type' => 'motor',
            'vehicle_plate' => 'L1234AB',
            'is_verified' => true,
            'is_active' => true,
        ]);

        return $user;
    }

    private function configureDokuKirim(string $mode = 'production'): array
    {
        $pair = DokuKirimAccountInquiryService::generateRsaKeyPair();
        $resolver = app(GatewayCredentialsResolver::class);

        Config::set('payment_wallet.mode', $mode);
        Setting::set('payment_wallet_mode', $mode, 'payment_wallet');
        Setting::set('doku_enabled', '1', 'doku');
        Setting::set('doku_kirim_inquiry_enabled', '1', 'doku');
        Setting::set('doku_sandbox_url', 'https://api-sandbox.doku.com', 'doku');
        Setting::set('doku_production_url', 'https://api.doku.com', 'doku');
        Setting::set('doku_client_id', 'MCH-TEST-001', 'doku');
        Setting::set('doku_kirim_channel_id', 'H2H', 'doku');
        Setting::set('doku_customer_number', '6281234567890', 'doku');

        $resolver->saveEncryptedSecret('doku_secret_key', 'SK-TEST-SECRET-123456', 'doku');
        $resolver->saveEncryptedSecret('doku_private_key', $pair['private_key'], 'doku');

        return $pair;
    }

    public function test_kirim_doku_verifies_real_bank_and_ewallet_names_with_snap_signatures(): void
    {
        $pair = $this->configureDokuKirim('production');
        $customer = $this->createCustomer();
        $courier = $this->createCourier();

        $captured = [];
        Http::fake(function ($request) use ($pair, &$captured) {
            $url = $request->url();
            $captured[] = [
                'url' => $url,
                'headers' => $request->headers(),
                'body' => $request->body(),
            ];

            if (str_ends_with($url, '/authorization/v1/access-token/b2b')) {
                $clientKey = $request->header('X-CLIENT-KEY')[0] ?? '';
                $timestamp = $request->header('X-TIMESTAMP')[0] ?? '';
                $sig = base64_decode($request->header('X-SIGNATURE')[0] ?? '', true);
                $ok = openssl_verify(
                    $clientKey.'|'.$timestamp,
                    $sig,
                    openssl_pkey_get_public($pair['public_key']),
                    OPENSSL_ALGO_SHA256
                );
                $this->assertSame(1, $ok, 'B2B RSA-SHA256 signature must verify with DOKU Public Key');

                return Http::response([
                    'responseCode' => '2007300',
                    'responseMessage' => 'Successful',
                    'accessToken' => 'snap-token-abc',
                    'tokenType' => 'Bearer',
                    'expiresIn' => 900,
                ], 200);
            }

            if (str_ends_with($url, '/snap/v1.1/emoney/bank-account-inquiry')) {
                $auth = $request->header('Authorization')[0] ?? '';
                $this->assertSame('Bearer snap-token-abc', $auth);
                $this->assertSame('H2H', $request->header('CHANNEL-ID')[0] ?? '');
                $this->assertSame('MCH-TEST-001', $request->header('X-PARTNER-ID')[0] ?? '');

                // Verify symmetric HMAC-SHA512 signature matches SNAP spec
                $body = $request->body();
                $timestamp = $request->header('X-TIMESTAMP')[0] ?? '';
                $expectedSig = base64_encode(hash_hmac(
                    'sha512',
                    'POST:/snap/v1.1/emoney/bank-account-inquiry:snap-token-abc:'.strtolower(hash('sha256', $body)).':'.$timestamp,
                    'SK-TEST-SECRET-123456',
                    true
                ));
                $this->assertSame($expectedSig, $request->header('X-SIGNATURE')[0] ?? '');

                $payload = json_decode($body, true);
                $bankCode = $payload['additionalInfo']['beneficiaryBankCode'] ?? '';
                $accNum = $payload['beneficiaryAccountNumber'] ?? '';

                if ($bankCode === '014' && $accNum === '8377388292') {
                    return Http::response([
                        'responseCode' => '2004200',
                        'responseMessage' => 'Successful',
                        'beneficiaryAccountNumber' => '8377388292',
                        'beneficiaryAccountName' => 'Andi Wijaya',
                        'beneficiaryBankCode' => '014',
                    ], 200);
                }

                if ($bankCode === 'DANA' && $accNum === '081299887766') {
                    return Http::response([
                        'responseCode' => '2004200',
                        'responseMessage' => 'Successful',
                        'beneficiaryAccountNumber' => '081299887766',
                        'beneficiaryAccountName' => 'Siti Rahma',
                        'beneficiaryBankCode' => 'DANA',
                    ], 200);
                }

                if ($bankCode === '911' && $accNum === '081355667788') {
                    return Http::response([
                        'responseCode' => '2004200',
                        'responseMessage' => 'Successful',
                        'beneficiaryAccountNumber' => '081355667788',
                        'beneficiaryAccountName' => 'Bagus Pratama',
                        'beneficiaryBankCode' => '911',
                    ], 200);
                }

                return Http::response([
                    'responseCode' => '4044211',
                    'responseMessage' => 'Invalid Account / Account Not Found',
                ], 404);
            }

            return Http::response([], 404);
        });

        // 1. Customer inquiry Bank BCA
        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/customer/wallet/destinations/inquiry', [
            'destination_type' => 'bank',
            'channel_code' => 'ID_BCA',
            'account_number' => '8377388292',
        ])
            ->assertOk()
            ->assertJsonPath('data.supported', true)
            ->assertJsonPath('data.found', true)
            ->assertJsonPath('data.provider', 'doku_kirim')
            ->assertJsonPath('data.is_simulated', false)
            ->assertJsonPath('data.account_holder_name', 'ANDI WIJAYA');

        // 2. Courier inquiry E-Wallet DANA (628 normalized to 08, token cached across calls)
        Sanctum::actingAs($courier);
        $this->postJson('/api/v1/courier/wallet/destinations/inquiry', [
            'destination_type' => 'ewallet',
            'channel_code' => 'ID_DANA',
            'account_number' => '6281299887766',
        ])
            ->assertOk()
            ->assertJsonPath('data.supported', true)
            ->assertJsonPath('data.found', true)
            ->assertJsonPath('data.provider', 'doku_kirim')
            ->assertJsonPath('data.account_number', '081299887766')
            ->assertJsonPath('data.account_holder_name', 'SITI RAHMA');

        // 3. Courier inquiry LinkAja (mapped to DOKU code 911)
        $this->postJson('/api/v1/courier/wallet/destinations/inquiry', [
            'destination_type' => 'ewallet',
            'channel_code' => 'ID_LINKAJA',
            'account_number' => '081355667788',
        ])
            ->assertOk()
            ->assertJsonPath('data.found', true)
            ->assertJsonPath('data.account_holder_name', 'BAGUS PRATAMA');

        // 4. Unknown account rejected cleanly without random fabrication
        $this->postJson('/api/v1/courier/wallet/destinations/inquiry', [
            'destination_type' => 'bank',
            'channel_code' => 'ID_BSI',
            'account_number' => '7001234567',
        ])
            ->assertOk()
            ->assertJsonPath('data.supported', true)
            ->assertJsonPath('data.found', false)
            ->assertJsonPath('data.provider', 'doku_kirim')
            ->assertJsonPath('data.account_holder_name', null);

        // Verify token endpoint was called only ONCE (cached) across 4 inquiries
        $tokenCalls = array_filter($captured, fn ($c) => str_ends_with($c['url'], '/access-token/b2b'));
        $this->assertCount(1, $tokenCalls);
    }

    public function test_kirim_doku_refreshes_expired_token_on_401_and_supports_legacy_courier_endpoint(): void
    {
        $this->configureDokuKirim('sandbox');
        $courier = $this->createCourier();

        $tokenSeq = 0;
        Http::fake(function ($request) use (&$tokenSeq) {
            if (str_ends_with($request->url(), '/authorization/v1/access-token/b2b')) {
                $tokenSeq++;

                return Http::response([
                    'responseCode' => '2007300',
                    'accessToken' => 'token-v'.$tokenSeq,
                    'expiresIn' => 900,
                ], 200);
            }

            if (str_ends_with($request->url(), '/snap/v1.1/emoney/bank-account-inquiry')) {
                $auth = $request->header('Authorization')[0] ?? '';
                if ($auth === 'Bearer token-v1') {
                    return Http::response(['responseCode' => '4014201', 'responseMessage' => 'Invalid Token'], 401);
                }

                return Http::response([
                    'responseCode' => '2004200',
                    'responseMessage' => 'Successful',
                    'beneficiaryAccountName' => 'Dewa Kurir',
                ], 200);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs($courier);
        $this->getJson('/api/courier/withdrawals/destination-inquiry?phone=081299887766')
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.provider', 'doku_kirim')
            ->assertJsonPath('data.account_name', 'DEWA KURIR');

        $this->assertSame(2, $tokenSeq);
    }

    public function test_admin_can_generate_rsa_keypair_save_private_key_and_toggle_doku_off(): void
    {
        $admin = User::create([
            'name' => 'Admin DOKU',
            'email' => 'admin.doku@city.test',
            'role' => 'admin',
            'password' => Hash::make('secret123'),
        ]);
        $admin->assignRole('super-admin');

        $genRes = $this->actingAs($admin)
            ->postJson('/admin/settings/doku/generate-keys')
            ->assertOk()
            ->assertJsonPath('success', true);

        $priv = (string) $genRes->json('private_key');
        $pub = (string) $genRes->json('public_key');
        $this->assertStringContainsString('PRIVATE KEY', $priv);
        $this->assertStringContainsString('PUBLIC KEY', $pub);

        $this->actingAs($admin)
            ->post('/admin/settings/doku', [
                'doku_sandbox_url' => 'https://api-sandbox.doku.com',
                'doku_production_url' => 'https://api.doku.com',
                'doku_client_id' => 'MCH-NEW-1',
                'doku_secret_key' => 'SK-NEW-12345678',
                'doku_private_key' => $priv,
                'doku_kirim_channel_id' => 'H2H',
                'doku_customer_number' => '628111222333',
                'doku_kirim_inquiry_enabled' => '1',
            ])
            ->assertRedirect();

        $dokuCreds = app(GatewayCredentialsResolver::class)->doku();
        $this->assertTrue($dokuCreds['kirim_configured']);
        $this->assertTrue($dokuCreds['kirim_inquiry_enabled']);
        $this->assertSame(trim($pub), $dokuCreds['public_key_derived']);

        $providersHtml = $this->actingAs($admin)
            ->get('/admin/providers')
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('/admin/settings/doku/generate-keys', $providersHtml);
        $this->assertStringContainsString('crypto.subtle', $providersHtml);
        $this->assertStringContainsString(trim($pub), $providersHtml);

        // When DOKU is toggled OFF by Admin, Kirim DOKU Inquiry must halt immediately
        $this->actingAs($admin)
            ->postJson('/admin/providers/doku/toggle', ['enabled' => 0])
            ->assertOk()
            ->assertJsonPath('enabled', false);

        $this->assertFalse(app(DokuKirimAccountInquiryService::class)->isAvailable());
    }

    public function test_kirim_doku_unregistered_partner_id_403_is_caught_by_admin_test_and_friendly_on_mobile(): void
    {
        $this->configureDokuKirim('sandbox');
        Setting::set('doku_kirim_partner_id', 'BRN-KIRIM-ONLY-999', 'doku');

        $admin = User::create([
            'name' => 'Admin Probe DOKU',
            'email' => 'admin.probe.doku@city.test',
            'role' => 'admin',
            'password' => Hash::make('secret123'),
        ]);
        $admin->assignRole('super-admin');

        $courier = $this->createCourier();
        $capturedHeaders = [];

        Http::fake(function ($request) use (&$capturedHeaders) {
            $url = $request->url();
            $capturedHeaders[] = [
                'url' => $url,
                'client_id' => $request->header('Client-Id')[0] ?? null,
                'x_client_key' => $request->header('X-CLIENT-KEY')[0] ?? null,
                'x_partner_id' => $request->header('X-PARTNER-ID')[0] ?? null,
            ];

            if (str_contains($url, '/orders/v1/status/')) {
                return Http::response(['message' => 'Not found'], 404);
            }

            if (str_ends_with($url, '/authorization/v1/access-token/b2b')) {
                return Http::response([
                    'responseCode' => '2007300',
                    'accessToken' => 'snap-token-403-check',
                    'expiresIn' => 900,
                ], 200);
            }

            if (str_ends_with($url, '/snap/v1.1/emoney/bank-account-inquiry')) {
                return Http::response([
                    'responseCode' => '4034215',
                    'responseMessage' => 'Transaction Not Permitted. [Partner Id has not been registered]',
                ], 403);
            }

            return Http::response([], 404);
        });

        // Admin Test Koneksi immediately exposes HTTP 403 unregistered Partner ID diagnostic
        $testRes = $this->actingAs($admin)
            ->postJson('/admin/settings/doku/test')
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $adminMsg = (string) $testRes->json('message');
        $this->assertStringContainsString('HTTP 403', $adminMsg);
        $this->assertStringContainsString('Partner Id has not been registered', $adminMsg);
        $this->assertStringContainsString('BRN-KIRIM-ONLY-999', $adminMsg);

        // Mobile inquiry uses the clean Indonesian fallback message (without raw HTTP 403 stack)
        Sanctum::actingAs($courier);
        $mobileRes = $this->postJson('/api/v1/courier/wallet/destinations/inquiry', [
            'destination_type' => 'ewallet',
            'channel_code' => 'ID_DANA',
            'account_number' => '081343323155',
        ])
            ->assertOk()
            ->assertJsonPath('data.supported', false)
            ->assertJsonPath('data.found', false);

        $mobileMsg = (string) $mobileRes->json('data.message');
        $this->assertStringNotContainsString('HTTP 403', $mobileMsg);
        $this->assertStringNotContainsString('Transaction Not Permitted', $mobileMsg);
        $this->assertStringContainsString('manual', strtolower($mobileMsg));

        // Confirm optional doku_kirim_partner_id overrides X-CLIENT-KEY & X-PARTNER-ID cleanly
        $tokenCall = collect($capturedHeaders)->first(fn ($h) => str_ends_with($h['url'], '/access-token/b2b'));
        $inquiryCall = collect($capturedHeaders)->first(fn ($h) => str_ends_with($h['url'], '/bank-account-inquiry'));
        $this->assertSame('BRN-KIRIM-ONLY-999', $tokenCall['x_client_key'] ?? null);
        $this->assertSame('BRN-KIRIM-ONLY-999', $inquiryCall['x_partner_id'] ?? null);
    }
}
