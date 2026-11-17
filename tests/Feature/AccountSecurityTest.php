<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use App\Services\WhatsAppManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Traits\CreatesPaymentWalletSchema;

class AccountSecurityTest extends TestCase
{
    use CreatesPaymentWalletSchema;

    private object $fakeWhatsapp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runPaymentWalletMigrations();
        Cache::flush();

        $this->fakeWhatsapp = new class extends WhatsAppManager {
            public ?string $lastOtp = null;

            public ?string $lastPhone = null;

            public function sendOtp(string $phone, string $otp): array
            {
                $this->lastPhone = $phone;
                $this->lastOtp = $otp;

                return ['success' => true];
            }
        };

        $this->app->instance(WhatsAppManager::class, $this->fakeWhatsapp);
    }

    public function test_security_overview_calculates_real_layers_and_preferences(): void
    {
        $user = User::create([
            'name' => 'Pelanggan Uji',
            'email' => 'pelanggan@example.com',
            'phone' => null,
            'role' => 'customer',
            'password' => Hash::make('secret123'),
        ]);

        $token = $user->createToken('google-auth')->plainTextToken;

        $overview1 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-Device-Name', 'Pixel 8 Pro')
            ->withHeader('X-Device-Platform', 'Android')
            ->getJson('/api/security/overview')
            ->assertOk();

        // Hanya security_alerts_enabled default true -> 1/4 lapisan aktif
        $overview1->assertJsonPath('data.score.active_layers', 1)
            ->assertJsonPath('data.score.total_layers', 4)
            ->assertJsonPath('data.score.level', 'weak')
            ->assertJsonPath('data.trusted_device_count', 1)
            ->assertJsonPath('data.devices.0.device_name', 'Pixel 8 Pro')
            ->assertJsonPath('data.devices.0.is_current', true);

        // Aktifkan biometrik & update nomor HP + PIN -> jadi 4/4 (Kuat)
        $user->forceFill([
            'phone' => '081234567890',
            'phone_verified_at' => now(),
        ])->save();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/security/preferences', ['biometric_enabled' => true])
            ->assertOk()
            ->assertJsonPath('data.preferences.biometric_enabled', true);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/security/pin', [
                'pin' => '482910',
                'pin_confirmation' => '482910',
            ])
            ->assertOk()
            ->assertJsonPath('data.score.active_layers', 4)
            ->assertJsonPath('data.score.label', 'Kuat')
            ->assertJsonPath('data.pin.has_pin', true);

        $this->assertTrue(Hash::check('482910', (string) $user->fresh()->transaction_pin));
    }

    public function test_pin_creation_change_lockout_and_otp_reset(): void
    {
        $user = User::create([
            'name' => 'Rina Customer',
            'email' => 'rina@example.com',
            'phone' => '081298443210',
            'phone_verified_at' => now(),
            'role' => 'customer',
            'password' => Hash::make('secret123'),
        ]);

        $token = $user->createToken('google-auth')->plainTextToken;
        $headers = ['Authorization' => 'Bearer ' . $token];

        // PIN lemah ditolak
        $this->withHeaders($headers)
            ->postJson('/api/security/pin', [
                'pin' => '123456',
                'pin_confirmation' => '123456',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PIN_WEAK');

        $this->withHeaders($headers)
            ->postJson('/api/security/pin', [
                'pin' => '222222',
                'pin_confirmation' => '222222',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PIN_WEAK');

        // Buat PIN kuat
        $this->withHeaders($headers)
            ->postJson('/api/security/pin', [
                'pin' => '739150',
                'pin_confirmation' => '739150',
            ])
            ->assertOk();

        // Ubah PIN tanpa current_pin ditolak
        $this->withHeaders($headers)
            ->postJson('/api/security/pin', [
                'pin' => '846205',
                'pin_confirmation' => '846205',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'CURRENT_PIN_REQUIRED');

        // 5x salah verifyPin memicu lockout 423
        for ($i = 0; $i < 4; $i++) {
            $this->withHeaders($headers)
                ->postJson('/api/security/pin/verify', ['pin' => '000001'])
                ->assertStatus(422)
                ->assertJsonPath('code', 'PIN_INVALID');
        }

        $this->withHeaders($headers)
            ->postJson('/api/security/pin/verify', ['pin' => '000001'])
            ->assertStatus(423)
            ->assertJsonPath('code', 'PIN_LOCKED');

        // Reset PIN lewat OTP tetap bisa dipakai saat lupa PIN / terkunci
        $this->withHeaders($headers)
            ->postJson('/api/security/pin/request-otp')
            ->assertOk();

        $this->assertNotNull($this->fakeWhatsapp->lastOtp);

        $this->withHeaders($headers)
            ->postJson('/api/security/pin', [
                'otp' => $this->fakeWhatsapp->lastOtp,
                'pin' => '951753',
                'pin_confirmation' => '951753',
            ])
            ->assertOk();

        $this->withHeaders($headers)
            ->postJson('/api/security/pin/verify', ['pin' => '951753'])
            ->assertOk()
            ->assertJsonPath('data.valid', true);
    }

    public function test_trusted_devices_list_single_revoke_and_revoke_all_others(): void
    {
        $user = User::create([
            'name' => 'Budi Multi Device',
            'email' => 'budi@example.com',
            'phone' => '081311223344',
            'phone_verified_at' => now(),
            'role' => 'customer',
            'password' => Hash::make('secret123'),
        ]);

        $token1 = $user->createToken('google-auth');
        $token1->accessToken->forceFill(['device_name' => 'Samsung Galaxy A54', 'platform' => 'Android'])->save();
        $plain1 = $token1->plainTextToken;

        $token2 = $user->createToken('phone-auth');
        $token2->accessToken->forceFill(['device_name' => 'Xiaomi Pad 6', 'platform' => 'Android'])->save();
        $token2Id = $token2->accessToken->id;

        $token3 = $user->createToken('google-auth');
        $token3->accessToken->forceFill(['device_name' => 'OPPO Reno 11', 'platform' => 'Android'])->save();

        // Tidak boleh hapus perangkat saat ini lewat endpoint delete device
        $this->withHeader('Authorization', 'Bearer ' . $plain1)
            ->deleteJson('/api/security/devices/' . $token1->accessToken->id)
            ->assertStatus(422)
            ->assertJsonPath('code', 'CANNOT_REVOKE_CURRENT_DEVICE');

        // Hapus token2 (Xiaomi Pad 6)
        $this->withHeader('Authorization', 'Bearer ' . $plain1)
            ->deleteJson('/api/security/devices/' . $token2Id)
            ->assertOk()
            ->assertJsonPath('data.trusted_device_count', 2);

        // Pastikan token2 benar-benar hilang dari DB (tidak bisa dipakai lagi)
        $this->assertNull($user->tokens()->whereKey($token2Id)->first());

        // Keluar dari semua perangkat lain -> hanya token1 yang tersisa
        $this->withHeader('Authorization', 'Bearer ' . $plain1)
            ->postJson('/api/security/devices/revoke-others')
            ->assertOk()
            ->assertJsonPath('revoked_count', 1)
            ->assertJsonPath('data.trusted_device_count', 1);

        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame($token1->accessToken->id, $user->tokens()->first()->id);
    }

    public function test_transaction_pin_protects_wallet_payment_when_enabled(): void
    {
        $user = User::create([
            'name' => 'Siti Wallet',
            'email' => 'siti@example.com',
            'phone' => '081200009999',
            'phone_verified_at' => now(),
            'role' => 'customer',
            'password' => Hash::make('secret123'),
        ]);

        Config::set('payment_wallet.mode', 'sandbox');

        $shipment = Shipment::create([
            'shipment_number' => 'SHP-SEC-1',
            'tracking_number' => 'TRK-SEC-1',
            'user_id' => $user->id,
            'total_cost' => 25000,
            'shipping_cost' => 25000,
            'status' => 'pending',
        ]);

        $token = $user->createToken('google-auth')->plainTextToken;
        $headers = ['Authorization' => 'Bearer ' . $token];

        // Top-up sandbox agar saldo mencukupi
        $topupId = $this->withHeaders($headers)
            ->postJson('/api/v1/customer/wallet/topups', [
                'amount' => 100000,
                'payment_type' => 'qris',
                'channel_code' => 'QRIS',
            ])
            ->assertStatus(201)
            ->json('data.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/customer/wallet/topups/{$topupId}/simulate-paid")
            ->assertOk();

        // Set PIN transaksi
        $this->withHeaders($headers)
            ->postJson('/api/security/pin', [
                'pin' => '592837',
                'pin_confirmation' => '592837',
            ])
            ->assertOk();

        // Wallet API melaporkan has_transaction_pin = true
        $this->withHeaders($headers)
            ->getJson('/api/v1/customer/wallet')
            ->assertOk()
            ->assertJsonPath('data.security.has_transaction_pin', true);

        // Tanpa PIN ditolak
        $this->withHeaders($headers)
            ->postJson('/api/v1/customer/wallet/payments', [
                'shipment_id' => $shipment->id,
                'idempotency_key' => 'pay-sec-1',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'TRANSACTION_PIN_REQUIRED');

        // PIN salah ditolak
        $this->withHeaders($headers)
            ->postJson('/api/v1/customer/wallet/payments', [
                'shipment_id' => $shipment->id,
                'idempotency_key' => 'pay-sec-1',
                'transaction_pin' => '111222',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'PIN_INVALID');

        // PIN benar berhasil bayar
        $this->withHeaders($headers)
            ->postJson('/api/v1/customer/wallet/payments', [
                'shipment_id' => $shipment->id,
                'idempotency_key' => 'pay-sec-1',
                'transaction_pin' => '592837',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
