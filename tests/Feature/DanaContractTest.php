<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\DanaConnection;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\TestCase as BaseTestBase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Kontrak API DANA (PRD §34, §35).
 *
 * Berjalan di SQLite in-memory dengan schema subset (tidak RefreshDatabase,
 * karena migrasi lama memakai ALTER ... MODIFY khusus MySQL).
 * Provider aktif = MockDanaProvider (Setting dana_mode default 'mock').
 * Standar respons: { success, message, data } + error_code.
 */
class DanaContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->dropSchema();
        $this->createSchema();
    }

    private function dropSchema(): void
    {
        foreach (['withdrawals', 'wallets', 'dana_connections', 'couriers', 'settings', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function createSchema(): void
    {
        \Illuminate\Support\Facades\Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone', 20)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('settings', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });

        Schema::create('couriers', function ($table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('vehicle_type')->default('motor');
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('dana_connections', function ($table) {
            $table->id();
            $table->foreignId('courier_id')->unique();
            $table->string('status')->default('not_connected');
            $table->string('external_id')->nullable();
            $table->string('state_hash')->nullable();
            $table->string('masked_phone')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('dana_user_reference')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamp('session_expires_at')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('bound_at')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('access_token_encrypted')->nullable();
            $table->text('refresh_token_encrypted')->nullable();
            $table->timestamps();
        });

        Schema::create('wallets', function ($table) {
            $table->id();
            $table->foreignId('courier_id')->unique();
            $table->string('status')->default('not_active');
            $table->decimal('available_balance', 15, 2)->default(0);
            $table->decimal('pending_balance', 15, 2)->default(0);
            $table->string('currency', 10)->default('IDR');
            $table->timestamps();
        });

        Schema::create('withdrawals', function ($table) {
            $table->id();
            $table->foreignId('courier_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    private function makeCourier(): Courier
    {
        $user = User::create([
            'name'     => 'Test Courier',
            'email'    => fake()->unique()->safeEmail(),
            'password' => \Illuminate\Support\Facades\Hash::make('secret'),
            'phone'    => '081234567890',
        ]);

        return Courier::create([
            'user_id'     => $user->id,
            'phone'       => '081234567890',
            'is_verified' => true,
            'is_active'   => true,
        ]);
    }

    private function actingCourier(): Courier
    {
        $courier = $this->makeCourier();
        Sanctum::actingAs($courier->user);

        return $courier;
    }

    public function test_dana_routes_require_auth(): void
    {
        $this->getJson('/api/courier/dana/status')->assertUnauthorized();
        $this->postJson('/api/courier/dana/binding')->assertUnauthorized();
        $this->postJson('/api/courier/dana/unbind')->assertUnauthorized();
        $this->postJson('/api/courier/dana/rebind')->assertUnauthorized();
    }

    public function test_status_defaults_to_not_connected(): void
    {
        $this->actingCourier();

        $this->getJson('/api/courier/dana/status')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'OK',
            ])
            ->assertJsonPath('data.status', 'NOT_CONNECTED')
            ->assertJsonPath('data.provider', 'DANA')
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.masked_account', null)
            ->assertJsonPath('data.bound_at', null);
    }

    public function test_binding_returns_prd_contract_shape(): void
    {
        $this->actingCourier();

        $response = $this->postJson('/api/courier/dana/binding')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'DANA binding URL generated',
            ]);

        $data = $response->json('data');

        $this->assertNotEmpty($data['binding_url']);
        $this->assertSame($data['binding_url'], $data['redirect_url']);
        $this->assertNotEmpty($data['session_id']);
        $this->assertNotNull($data['expires_at']);

        // Backward-compat legacy fields tetap ada (konsumen lama connect()).
        $this->assertSame($response->json('bindingUrl'), $data['binding_url']);
        $this->assertSame($response->json('sessionId'), $data['session_id']);

        // Sesi tersimpan: status pending, state_hash terisi, raw state TIDAK tersimpan.
        $conn = DanaConnection::first();
        $this->assertNotNull($conn);
        $this->assertSame('pending', $conn->status);
        $this->assertNotEmpty($conn->state_hash);
        $this->assertNotSame($conn->state_hash, $conn->session_id);
    }

    public function test_binding_rejects_when_already_connected(): void
    {
        $courier = $this->actingCourier();

        DanaConnection::create([
            'courier_id' => $courier->id,
            'status'     => 'connected',
        ]);

        $this->postJson('/api/courier/dana/binding')
            ->assertStatus(400)
            ->assertJsonPath('error_code', 'DANA_ALREADY_CONNECTED');
    }

    public function test_callback_flow_connects_and_encrypts_tokens(): void
    {
        $courier = $this->actingCourier();

        // 1) Binding → dapat state (session_id) untuk callback publik.
        $state = $this->postJson('/api/courier/dana/binding')->json('data.session_id');
        $this->assertNotEmpty($state);

        // Status di tengah proses: PENDING.
        $this->getJson('/api/courier/dana/status')
            ->assertJsonPath('data.status', 'PENDING');

        // 2) Callback publik (tanpa bearer) — DANA me-redirect browser (PRD §34).
        $html = $this->get('/api/dana/callback?auth_code=TEST-AUTH&state=' . urlencode($state))
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('citycourier://dana/binding/success', $html);

        // 3) Status sekarang CONNECTED dengan detail akun.
        $this->getJson('/api/courier/dana/status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'CONNECTED')
            ->assertJsonPath('data.provider', 'DANA')
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.masked_account', '081234******7890')
            ->assertJsonPath('data.masked_phone', '081234******7890');
        $boundAt = $this->getJson('/api/courier/dana/status')->json('data.bound_at');
        $this->assertNotNull($boundAt);

        // 4) Token disimpan TERENKRIPSI at rest; tidak bocor ke JSON.
        $conn = DanaConnection::first();
        $row = $conn->getAttributes();
        $this->assertNotNull($row['access_token_encrypted']);
        $this->assertTrue(str_starts_with($row['access_token_encrypted'], 'eyJ'));
        $this->assertSame($conn->access_token, Crypt::decryptString($row['access_token_encrypted']));
        $json = json_decode($conn->toJson(), true);
        $this->assertArrayNotHasKey('access_token', $json);
        $this->assertArrayNotHasKey('access_token_encrypted', $json);
        $this->assertArrayNotHasKey('state_hash', $json);

        // 5) Wallet otomatis aktif setelah terhubung (PRD §10).
        $this->assertSame('active', Wallet::where('courier_id', $courier->id)->first()?->status);

        // 6) dana_user_reference dari provider tersimpan.
        $this->assertSame('MOCK-USR-REF-2026', $conn->dana_user_reference);
    }

    public function test_callback_rejects_invalid_state(): void
    {
        $html = $this->get('/api/dana/callback?auth_code=TEST-AUTH&state=invalid-state')
            ->assertStatus(400)
            ->getContent();

        $this->assertStringContainsString('citycourier://dana/binding/failed', $html);
    }

    public function test_callback_rejects_missing_auth_code(): void
    {
        $html = $this->get('/api/dana/callback')
            ->assertStatus(400)
            ->getContent();

        $this->assertStringContainsString('citycourier://dana/binding/failed', $html);
    }

    public function test_unbind_when_not_connected_returns_error_code(): void
    {
        $this->actingCourier();

        $this->postJson('/api/courier/dana/unbind')
            ->assertStatus(400)
            ->assertJsonPath('error_code', 'DANA_NOT_CONNECTED');
    }

    public function test_unbind_when_connected_revokes_and_deactivates_wallet(): void
    {
        $courier = $this->actingCourier();

        // Hubungkan penuh lewat alur binding.
        $state = $this->postJson('/api/courier/dana/binding')->json('data.session_id');
        $this->get('/api/dana/callback?auth_code=TEST-AUTH&state=' . urlencode($state))
            ->assertStatus(200);

        $this->postJson('/api/courier/dana/unbind')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'DANA berhasil diputuskan.');

        $this->assertSame('revoked', DanaConnection::first()?->status);
        $this->assertSame('not_active', Wallet::where('courier_id', $courier->id)->first()?->status);

        // Status setelah revoke: bukan CONNECTED.
        $this->getJson('/api/courier/dana/status')
            ->assertJsonPath('data.connected', false);
    }

    public function test_legacy_connect_still_returns_binding_url(): void
    {
        $this->actingCourier();

        $response = $this->postJson('/api/courier/dana/connect')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('data.binding_url'));
        $this->assertNotEmpty($response->json('data.session_id'));
    }
}