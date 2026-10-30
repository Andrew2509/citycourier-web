<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\iPaymuWebhookController;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\IpaymuPaymentService;
use App\Services\iPaymuService;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pengujian pembayaran checkout (VA & QRIS) via iPaymu API v2.
 *
 * Kontrak respons dipertahankan (Flutter tidak berubah), status 'paid' HANYA
 * setelah konfirmasi resmi iPaymu (callback ber-signature / inquiry) —
 * TIDAK ada simulasi sukses.
 */
class IpaymuCheckoutPaymentTest extends TestCase
{
    protected FakeIpaymuService $fakeIpaymu;
    protected FakeIpaymuPaymentService $fakePayment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropSchema();
        $this->createSchema();

        // Reset cache kredensial iPaymu agar baca dari settings baru.
        iPaymuService::flushCredentialsCache();

        $this->fakeIpaymu = new FakeIpaymuService();
        $this->fakePayment = new FakeIpaymuPaymentService($this->fakeIpaymu);

        $this->app->bind(iPaymuService::class, fn () => $this->fakeIpaymu);
        $this->app->bind(IpaymuPaymentService::class, fn () => $this->fakePayment);
    }

    private function dropSchema(): void
    {
        foreach (['payments', 'orders', 'shipments', 'settings', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function createSchema(): void
    {
        Schema::create('users', function ($table) {
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

        Schema::create('payments', function ($table) {
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

        Schema::create('withdrawals', function ($table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('orders', function ($table) {
            $table->id();
            $table->string('payment_status')->default('unpaid');
            $table->timestamps();
        });

        Schema::create('shipments', function ($table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_phone', 20)->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        iPaymuService::flushCredentialsCache();
        parent::tearDown();
    }

    private function user(): User
    {
        return User::create([
            'name'     => 'Tester',
            'email'    => 'tester@example.com',
            'password' => bcrypt('secret'),
            'phone'    => '08123456789',
        ]);
    }

    private function makeConfigured(): void
    {
        Setting::create(['key' => 'ipaymu_va', 'value' => '123456', 'group' => 'ipaymu']);
        Setting::create(['key' => 'ipaymu_api_key', 'value' => 'apikey-xyz', 'group' => 'ipaymu']);
        Setting::create(['key' => 'ipaymu_base_url', 'value' => 'https://sandbox.ipaymu.com', 'group' => 'ipaymu']);
        iPaymuService::flushCredentialsCache();
    }

    // ─── 1. methods() ───────────────────────────────────────────

    public function test_methods_requires_config(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);
        $this->fakePayment->configured = false;

        $response = $this->getJson('/api/payment/methods');
        $response->assertStatus(502)
            ->assertJson(['success' => false]);
    }

    public function test_methods_returns_va_and_qris(): void
    {
        $this->makeConfigured();
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->fakePayment->methods = [
            ['code' => 'bca', 'name' => 'BCA Virtual Account', 'group' => 'virtual_account', 'icon' => 'https://x/bca.png', 'fee' => 4000, 'is_available' => true],
            ['code' => 'QRIS', 'name' => 'QRIS', 'group' => 'qris', 'icon' => 'https://x/qris.png', 'fee' => 0, 'is_available' => true],
        ];

        $response = $this->getJson('/api/payment/methods');
        $response->assertOk()
            ->assertJson(['success' => true]);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertSame('bca', $data[0]['code']);
        $this->assertSame('QRIS', $data[1]['code']);
    }

    // ─── 2. create() ────────────────────────────────────────────

    public function test_create_validation_amount_min(): void
    {
        $this->makeConfigured();
        $user = $this->user();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payment/create', [
            'shipment_id' => 'SHIP-1',
            'payment_type' => 'virtual_account',
            'channel_code' => 'bca',
            'amount'      => 5000,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_va_success(): void
    {
        $this->makeConfigured();
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->fakePayment->createResult = [
            'success'     => true,
            'error'       => null,
            'payment_id'  => '99001',
            'va_number'   => '123456789012',
            'qr_string'   => null,
            'payment_url' => 'https://sandbox.ipaymu.com/payment/99001',
            'expired_at'  => now()->addHours(24)->toIso8601String(),
            'raw'         => null,
        ];

        $response = $this->postJson('/api/payment/create', [
            'shipment_id' => 'SHIP-1',
            'payment_type' => 'virtual_account',
            'channel_code' => 'bca',
            'amount'       => 13300,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
        $this->assertSame('123456789012', $response->json('data.va_number'));
        $this->assertSame('99001', $response->json('data.payment_id'));
        $this->assertSame('pending', $response->json('data.status'));

        $this->assertDatabaseHas('payments', [
            'payment_id'   => '99001',
            'channel_code' => 'BCA',
            'amount'       => 13300,
            'status'       => 'pending',
            'va_number'    => '123456789012',
        ]);
    }

    public function test_create_qris_success(): void
    {
        $this->makeConfigured();
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->fakePayment->createResult = [
            'success'     => true,
            'error'       => null,
            'payment_id'  => '99002',
            'va_number'   => null,
            'qr_string'   => 'QRIS-STRING-ABC',
            'payment_url' => 'https://sandbox.ipaymu.com/payment/99002',
            'expired_at'  => now()->addMinutes(60)->toIso8601String(),
            'raw'         => null,
        ];

        $response = $this->postJson('/api/payment/create', [
            'shipment_id' => 'SHIP-2',
            'payment_type' => 'qris',
            'channel_code' => 'qris',
            'amount'       => 13300,
        ]);

        $response->assertStatus(201);
        $this->assertSame('QRIS-STRING-ABC', $response->json('data.qr_string'));
        $this->assertDatabaseHas('payments', [
            'payment_id'   => '99002',
            'payment_type' => 'qris',
            'status'       => 'pending',
        ]);
    }

    // ─── 2a. Redirect Payment (cadangan saat Direct diblokir) ────

    private function setPaymentMode(string $mode): void
    {
        Setting::updateOrCreate(
            ['key' => 'ipaymu_payment_mode'],
            ['value' => $mode, 'group' => 'ipaymu']
        );
        iPaymuService::flushCredentialsCache();
    }

    private function redirectSuccess(): array
    {
        return [
            'success'     => true,
            'error'       => null,
            'session_id'  => 'SESS-ABC-123',
            'payment_url' => 'https://sandbox.ipaymu.com/payment/SESS-ABC-123',
            'expired_at'  => now()->addHours(24)->toIso8601String(),
            'http_code'   => 200,
            'raw'         => ['Status' => 200, 'Success' => true],
        ];
    }

    public function test_create_redirect_mode_returns_hosted_payment_url(): void
    {
        $this->makeConfigured();
        $this->setPaymentMode('redirect');
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->fakePayment->redirectResult = $this->redirectSuccess();

        $response = $this->postJson('/api/payment/create', [
            'shipment_id'  => 'SHIP-R1',
            'payment_type' => 'virtual_account',
            'channel_code' => 'bni',
            'amount'       => 13300,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'payment_mode' => 'redirect',
                    'payment_id'   => 'SESS-ABC-123',
                    'payment_url'  => 'https://sandbox.ipaymu.com/payment/SESS-ABC-123',
                    'va_number'    => null,
                ],
            ]);

        // Aplikasi perlu tahu URL kembali/batal agar bisa menutup WebView dan
        // membaca status resmi, bukan menebak dari pola URL iPaymu.
        $base = rtrim((string) config('app.url'), '/');
        $response->assertJsonPath('data.return_url', $base . '/api/ipaymu/return')
            ->assertJsonPath('data.cancel_url', $base . '/api/ipaymu/cancel');

        // Direct Payment tidak boleh dipanggil pada mode redirect.
        $this->assertSame([], $this->fakePayment->lastInput);
        $this->assertNotNull($this->fakePayment->lastRedirectInput);
        $this->assertSame('13300', (string) $this->fakePayment->lastRedirectInput['amount']);

        $payment = Payment::where('payment_id', 'SESS-ABC-123')->firstOrFail();
        $this->assertSame('redirect', $payment->metadata['payment_mode']);
        $this->assertNull($payment->va_number);
    }

    public function test_create_auto_falls_back_to_redirect_when_direct_blocked(): void
    {
        $this->makeConfigured();
        $this->setPaymentMode('auto');
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->fakePayment->createResult = [
            'success'     => false,
            'error'       => 'Suspicious buyer',
            'payment_id'  => null,
            'va_number'   => null,
            'qr_string'   => null,
            'payment_url' => null,
            'expired_at'  => null,
            'http_code'   => 503,
            'raw'         => ['Status' => 503, 'Success' => false, 'Message' => 'Suspicious buyer'],
        ];
        $this->fakePayment->redirectResult = $this->redirectSuccess();

        $response = $this->postJson('/api/payment/create', [
            'shipment_id'  => 'SHIP-R2',
            'payment_type' => 'qris',
            'channel_code' => 'qris',
            'amount'       => 13300,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => ['payment_mode' => 'redirect'],
            ]);

        // Direct dicoba lebih dulu, lalu redirect.
        $this->assertNotEmpty($this->fakePayment->lastInput);
        $this->assertNotNull($this->fakePayment->lastRedirectInput);
    }

    public function test_create_auto_does_not_fallback_on_validation_error(): void
    {
        $this->makeConfigured();
        $this->setPaymentMode('auto');
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->fakePayment->createResult = [
            'success'     => false,
            'error'       => "Saluran VA tidak dikenal: 'foo'.",
            'payment_id'  => null,
            'va_number'   => null,
            'qr_string'   => null,
            'payment_url' => null,
            'expired_at'  => null,
            'http_code'   => 400,
            'raw'         => ['Status' => 400, 'Success' => false],
        ];
        $this->fakePayment->redirectResult = $this->redirectSuccess();

        $response = $this->postJson('/api/payment/create', [
            'shipment_id'  => 'SHIP-R3',
            'payment_type' => 'virtual_account',
            'channel_code' => 'foo',
            'amount'       => 13300,
        ]);

        $response->assertStatus(502)
            ->assertJson(['success' => false, 'message' => "Saluran VA tidak dikenal: 'foo'."]);
        $this->assertNull($this->fakePayment->lastRedirectInput);
    }

    public function test_redirect_payment_status_stays_pending_without_trx_id(): void
    {
        $this->makeConfigured();
        $this->setPaymentMode('redirect');
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->fakePayment->redirectResult = $this->redirectSuccess();

        $this->postJson('/api/payment/create', [
            'shipment_id'  => 'SHIP-R4',
            'payment_type' => 'virtual_account',
            'channel_code' => 'bni',
            'amount'       => 13300,
        ])->assertStatus(201);

        // SessionID bukan TransactionId → inquiry belum bisa dipakai, jadi
        // status jujur tetap pending (tidak ada hasil karangan).
        $this->fakePayment->syncResult = ['status' => 'paid', 'known' => true, 'error' => null];

        $this->getJson('/api/payment/status?payment_id=SESS-ABC-123')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_mode', 'redirect')
            ->assertJsonPath(
                'data.return_url',
                rtrim((string) config('app.url'), '/') . '/api/ipaymu/return'
            );
    }

    // ─── 2b. Identitas buyer (anti "Suspicious buyer") ──────────

    /**
     * Akun hasil login OTP lama menyimpan nama "User 1234" dan email
     * `@citycourier.local`. Keduanya ditolak iPaymu di mode production.
     * Checkout harus memakai data pelanggan dari shipment + email valid.
     */
    public function test_create_uses_shipment_identity_instead_of_placeholder_account(): void
    {
        $this->makeConfigured();
        config(['ipaymu.buyer_email_domain' => 'neonflux.my.id']);

        $user = User::create([
            'name'     => 'User 1234',
            'email'    => '081234567890@citycourier.local',
            'password' => bcrypt('secret'),
            'phone'    => '+62 812-3456-7890',
        ]);
        Sanctum::actingAs($user);

        $shipmentId = \Illuminate\Support\Facades\DB::table('shipments')->insertGetId([
            'user_id'        => $user->id,
            'customer_name'  => 'Budi Santoso',
            'customer_phone' => '+62 812-9876-5432',
            'status'         => 'pending',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->fakePayment->createResult = [
            'success'     => true,
            'error'       => null,
            'payment_id'  => '99010',
            'va_number'   => '8808123456789',
            'qr_string'   => null,
            'payment_url' => null,
            'expired_at'  => now()->addHours(24)->toIso8601String(),
            'raw'         => null,
        ];

        $this->postJson('/api/payment/create', [
            'shipment_id'  => (string) $shipmentId,
            'payment_type' => 'virtual_account',
            'channel_code' => 'bni',
            'amount'       => 13300,
        ])->assertStatus(201);

        $buyer = $this->fakePayment->lastInput['customer'];

        $this->assertSame('Budi Santoso', $buyer['name']);
        $this->assertSame('081298765432', $buyer['phone']);
        // Email netral tanpa nomor telepon: local-part berisi digit panjang
        // (`pelanggan.0812…`) juga ditandai iPaymu sebagai buyer mencurigakan.
        $this->assertSame('pesanan@neonflux.my.id', $buyer['email']);
        $this->assertNotSame($user->email, $buyer['email']);
    }

    /** Email pengguna yang valid (mis. Gmail) dipertahankan apa adanya. */
    public function test_create_keeps_valid_user_email(): void
    {
        $this->makeConfigured();

        $user = User::create([
            'name'     => 'Budi Santoso',
            'email'    => 'budi.santoso@gmail.com',
            'password' => bcrypt('secret'),
            'phone'    => '081298765432',
        ]);
        Sanctum::actingAs($user);

        $this->fakePayment->createResult = [
            'success'     => true, 'error' => null, 'payment_id' => '99011',
            'va_number'   => '8808123456790', 'qr_string' => null,
            'payment_url' => null, 'expired_at' => now()->addHours(24)->toIso8601String(),
            'raw'         => null,
        ];

        $this->postJson('/api/payment/create', [
            'shipment_id'  => 'SHIP-VALID-EMAIL',
            'payment_type' => 'virtual_account',
            'channel_code' => 'bni',
            'amount'       => 13300,
        ])->assertStatus(201);

        $buyer = $this->fakePayment->lastInput['customer'];

        $this->assertSame('Budi Santoso', $buyer['name']);
        $this->assertSame('budi.santoso@gmail.com', $buyer['email']);
        $this->assertSame('081298765432', $buyer['phone']);
    }

    /** Override `customer` dari request (dipakai untuk uji curl) dihormati. */
    public function test_create_respects_explicit_customer_override(): void
    {
        $this->makeConfigured();

        $user = User::create([
            'name'     => 'User 1234',
            'email'    => '081234567890@citycourier.local',
            'password' => bcrypt('secret'),
            'phone'    => '081234567890',
        ]);
        Sanctum::actingAs($user);

        $this->fakePayment->createResult = [
            'success'     => true, 'error' => null, 'payment_id' => '99012',
            'va_number'   => '8808123456791', 'qr_string' => null,
            'payment_url' => null, 'expired_at' => now()->addHours(24)->toIso8601String(),
            'raw'         => null,
        ];

        $this->postJson('/api/payment/create', [
            'shipment_id'  => 'SHIP-OVERRIDE',
            'payment_type' => 'virtual_account',
            'channel_code' => 'bni',
            'amount'       => 13300,
            'customer'     => [
                'name'  => 'Siti Aminah',
                'email' => 'siti.aminah@yahoo.com',
                'phone' => '+62 813-1111-2222',
            ],
        ])->assertStatus(201);

        $buyer = $this->fakePayment->lastInput['customer'];

        $this->assertSame('Siti Aminah', $buyer['name']);
        $this->assertSame('siti.aminah@yahoo.com', $buyer['email']);
        $this->assertSame('081311112222', $buyer['phone']);
    }

    /**
     * Email buyer fallback boleh diatur admin (Admin > Provider & Integrasi),
     * bukan hanya lewat .env, dan dipakai bila email pembeli tidak layak.
     */
    public function test_buyer_email_from_admin_setting_is_used(): void
    {
        $this->makeConfigured();
        Setting::create(['key' => 'ipaymu_buyer_email', 'value' => 'cs@citycourier.id', 'group' => 'ipaymu']);

        $user = $this->user(); // email "tester@example.com" = domain reserved
        Sanctum::actingAs($user);

        $this->fakePayment->createResult = [
            'success' => true, 'error' => null, 'payment_id' => '99099',
            'va_number' => '8808123456799', 'qr_string' => null,
            'payment_url' => null, 'expired_at' => now()->addHours(24)->toIso8601String(),
            'raw' => null,
        ];

        $this->postJson('/api/payment/create', [
            'payment_type' => 'virtual_account',
            'channel_code' => 'bni',
            'amount'       => 13300,
        ])->assertStatus(201);

        $this->assertSame('cs@citycourier.id', $this->fakePayment->lastInput['customer']['email']);
    }

    /**
     * Penolakan provider (mis. "Suspicious buyer") harus mengembalikan data
     * diagnostik: identitas yang dikirim dan jawaban mentah iPaymu.
     */
    public function test_create_failure_returns_buyer_diagnostics(): void
    {
        $this->makeConfigured();
        Sanctum::actingAs($this->user());

        $this->fakePayment->createResult = [
            'success'   => false,
            'error'     => 'Suspicious buyer',
            'http_code' => 503,
            'raw'       => ['Status' => 503, 'Message' => 'Suspicious buyer'],
        ];

        $this->postJson('/api/payment/create', [
            'payment_type' => 'virtual_account',
            'channel_code' => 'bni',
            'amount'       => 13300,
        ])->assertStatus(502)
            ->assertJsonPath('message', 'Suspicious buyer')
            ->assertJsonPath('debug.http_code', 503)
            ->assertJsonPath('debug.provider.Message', 'Suspicious buyer')
            ->assertJsonPath('debug.buyer.email', fn ($value) => is_string($value) && $value !== '')
            ->assertJsonPath('debug.buyer.phone', fn ($value) => is_string($value) && $value !== '')
            // Endpoint yang benar-benar dipanggil harus terlihat, supaya bisa
            // dibedakan masalah akun dari salah host (sandbox vs production).
            ->assertJsonPath(
                'debug.endpoint.direct',
                fn ($value) => is_string($value) && str_ends_with($value, '/api/v2/payment/direct')
            );
    }

    // ─── 3. status() ────────────────────────────────────────────

    public function test_status_terminal_no_api(): void
    {
        $this->makeConfigured();
        $user = $this->user();
        Sanctum::actingAs($user);

        Payment::create([
            'user_id' => $user->id, 'payment_id' => 'P-1', 'payment_type' => 'bank_transfer',
            'amount' => 10000, 'status' => 'paid', 'va_number' => '123',
        ]);

        $response = $this->getJson('/api/payment/status?payment_id=P-1');
        $response->assertOk()
            ->assertJson(['data' => ['status' => 'paid']]);
    }

    public function test_status_inquiry_updates_paid(): void
    {
        $this->makeConfigured();
        $user = $this->user();
        Sanctum::actingAs($user);

        $order = \DB::table('orders')->insertGetId(['payment_status' => 'unpaid', 'created_at' => now(), 'updated_at' => now()]);
        $shipment = \DB::table('shipments')->insertGetId(['status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        Payment::create([
            'user_id' => $user->id, 'order_id' => $order, 'shipment_id' => $shipment,
            'payment_id' => 'P-2', 'payment_type' => 'bank_transfer', 'amount' => 10000,
            'status' => 'pending', 'va_number' => '123',
        ]);

        $this->fakePayment->syncResult = ['status' => 'paid', 'known' => true, 'error' => null];

        $response = $this->getJson('/api/payment/status?payment_id=P-2');
        $response->assertOk()
            ->assertJson(['data' => ['status' => 'paid']]);

        $this->assertDatabaseHas('orders', ['id' => $order, 'payment_status' => 'paid']);
        $this->assertDatabaseHas('shipments', ['id' => $shipment, 'status' => 'confirmed']);
    }

    // ─── 4. cancel() ────────────────────────────────────────────

    public function test_cancel_marks_canceled(): void
    {
        $this->makeConfigured();
        $user = $this->user();
        Sanctum::actingAs($user);

        Payment::create([
            'user_id' => $user->id, 'payment_id' => 'P-3', 'payment_type' => 'bank_transfer',
            'amount' => 10000, 'status' => 'pending', 'va_number' => '123',
        ]);

        $response = $this->postJson('/api/payment/cancel', ['payment_id' => 'P-3']);
        $response->assertOk()
            ->assertJson(['success' => true]);
        $this->assertDatabaseHas('payments', ['payment_id' => 'P-3', 'status' => 'canceled']);
    }

    // ─── 5. webhook() ───────────────────────────────────────────

    private function signatureFor(array $payload): string
    {
        // Replikasi normalisasi controller (int fields, additional_info).
        $intFields = ['trx_id', 'status_code', 'transaction_status_code', 'paid_off'];
        $normalized = [];
        foreach ($payload as $k => $v) {
            if ($k === 'signature') continue;
            if (in_array($k, $intFields, true)) {
                $normalized[$k] = is_numeric($v) ? (int) $v : $v;
            } elseif ($k === 'is_escrow') {
                $normalized[$k] = in_array($v, [true, 1, '1', 'true'], true);
            } elseif ($k === 'additional_info') {
                $normalized[$k] = ($v === '[]') ? [] : $v;
            } else {
                $normalized[$k] = is_array($v) ? $v : (string) $v;
            }
        }
        if (!array_key_exists('additional_info', $normalized)) {
            $normalized['additional_info'] = [];
        }
        ksort($normalized, SORT_STRING);
        $json = json_encode($normalized);
        return hash_hmac('sha256', $json, '123456');
    }

    public function test_webhook_invalid_signature_401(): void
    {
        $this->makeConfigured();
        $payload = [
            'trx_id' => 99001, 'status_code' => 1, 'status' => 'berhasil',
            'additional_info' => [],
        ];
        $response = $this->postJson('/api/ipaymu/callback', $payload, [
            'X-Signature' => 'deadbeef',
        ]);
        $response->assertStatus(401);
    }

    public function test_webhook_paid_callback_updates_payment(): void
    {
        $this->makeConfigured();
        $user = $this->user();

        $order = \DB::table('orders')->insertGetId(['payment_status' => 'unpaid', 'created_at' => now(), 'updated_at' => now()]);
        $shipment = \DB::table('shipments')->insertGetId(['status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        Payment::create([
            'user_id' => $user->id, 'order_id' => $order, 'shipment_id' => $shipment,
            'payment_id' => '99001', 'payment_type' => 'bank_transfer', 'amount' => 10000,
            'status' => 'pending', 'va_number' => '123',
        ]);

        $this->fakePayment->webhookStatus = 'paid';

        $payload = [
            'trx_id' => 99001,
            'status_code' => 1,
            'status' => 'berhasil',
            'amount' => '10000',
            'total' => '10000',
            'additional_info' => [],
        ];
        $sig = $this->signatureFor($payload);

        $response = $this->postJson('/api/ipaymu/callback', $payload, [
            'X-Signature' => $sig,
        ]);
        $response->assertOk();

        $this->assertDatabaseHas('payments', ['payment_id' => '99001', 'status' => 'paid']);
        $this->assertDatabaseHas('orders', ['id' => $order, 'payment_status' => 'paid']);
        $this->assertDatabaseHas('shipments', ['id' => $shipment, 'status' => 'confirmed']);
    }

    public function test_webhook_unknown_reference_ignored(): void
    {
        $this->makeConfigured();

        $payload = [
            'trx_id' => 777777,
            'status_code' => 1,
            'status' => 'berhasil',
            'additional_info' => [],
        ];
        $sig = $this->signatureFor($payload);

        $response = $this->postJson('/api/ipaymu/callback', $payload, [
            'X-Signature' => $sig,
        ]);
        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_webhook_idempotent_terminal(): void
    {
        $this->makeConfigured();
        $user = $this->user();

        Payment::create([
            'user_id' => $user->id, 'payment_id' => '99003', 'payment_type' => 'bank_transfer',
            'amount' => 10000, 'status' => 'paid', 'va_number' => '123',
        ]);

        $payload = [
            'trx_id' => 99003,
            'status_code' => 1,
            'status' => 'berhasil',
            'additional_info' => [],
        ];
        $sig = $this->signatureFor($payload);

        $response = $this->postJson('/api/ipaymu/callback', $payload, [
            'X-Signature' => $sig,
        ]);
        $response->assertOk();
        $this->assertDatabaseHas('payments', ['payment_id' => '99003', 'status' => 'paid']);
    }

    /**
     * Redirect Payment menyimpan SessionID sebagai payment_id, sedangkan
     * callback membawa trx_id + reference_id kita. Payment harus dikenali
     * lewat referenceId, lalu trx_id resmi disimpan untuk inquiry berikutnya.
     */
    public function test_webhook_matches_redirect_payment_by_reference(): void
    {
        $this->makeConfigured();
        $user = $this->user();

        Payment::create([
            'user_id' => $user->id, 'payment_id' => 'SESS-ABC-123',
            'payment_type' => 'bank_transfer', 'amount' => 13300, 'status' => 'pending',
            'payment_url' => 'https://sandbox.ipaymu.com/payment/SESS-ABC-123',
            'metadata' => [
                'ipaymu_reference_id' => 'CC-SHIP-R5-20261030120000',
                'payment_mode'        => 'redirect',
            ],
        ]);

        $this->fakePayment->webhookStatus = 'paid';

        $payload = [
            'trx_id' => 555111,
            'reference_id' => 'CC-SHIP-R5-20261030120000',
            'status_code' => 1,
            'status' => 'berhasil',
            'additional_info' => [],
        ];
        $sig = $this->signatureFor($payload);

        $this->postJson('/api/ipaymu/callback', $payload, ['X-Signature' => $sig])->assertOk();

        $payment = Payment::where('metadata->ipaymu_reference_id', 'CC-SHIP-R5-20261030120000')->firstOrFail();
        $this->assertSame('paid', $payment->status);
        // trx_id resmi menggantikan SessionID supaya status bisa di-inquiry.
        $this->assertSame('555111', (string) $payment->payment_id);
        $this->assertSame('555111', (string) $payment->metadata['ipaymu_trx_id']);
    }

    public function test_return_pages_render(): void
    {
        $this->get('/api/ipaymu/return')
            ->assertOk()
            ->assertSee('City Courier');

        $this->get('/api/ipaymu/cancel')
            ->assertOk()
            ->assertSee('Pembayaran Dibatalkan');
    }

    // ─── 6. Signature GET (regresi: payment-channels) ───────────

    /**
     * Sample resmi iPaymu (ipaymu-payment-v2-sample-php/payment_channel.php):
     *   GET /api/v2/payment-channels
     *   $requestBody = hash('sha256', '{}')
     * Sebelumnya kode memakai body mentah '' → "unauthorized signature".
     */
    public function test_get_signature_uses_sha256_of_empty_json_object(): void
    {
        $va = '1179000899';
        $apiKey = 'QbGcoO0Qds9sQFDmY0MWg1Tq.xtuh1';

        $svc = new iPaymuService([
            'base_url' => 'https://sandbox.ipaymu.com',
            'va'       => $va,
            'api_key'  => $apiKey,
            'timeout'  => 10,
        ]);

        $ref = new \ReflectionMethod(iPaymuService::class, 'buildHeaders');
        $ref->setAccessible(true);
        $headers = $ref->invoke($svc, 'GET', '');

        $signature = null;
        foreach ($headers as $header) {
            if (str_starts_with($header, 'signature: ')) {
                $signature = substr($header, strlen('signature: '));
            }
        }

        $expected = hash_hmac(
            'sha256',
            'GET:' . $va . ':' . hash('sha256', '{}') . ':' . $apiKey,
            $apiKey
        );

        $this->assertSame($expected, $signature);
    }

    /**
     * Body POST harus byte-identik dengan yang di-hash. Sample resmi memakai
     * json_encode($body, JSON_UNESCAPED_SLASHES) — URL tidak boleh menjadi
     * `https:\/\/...`, jika tidak iPaymu menolak "unauthorized signature".
     */
    public function test_encode_body_keeps_urls_unescaped(): void
    {
        $svc = new iPaymuService([
            'base_url' => 'https://sandbox.ipaymu.com',
            'va'       => '1179000899',
            'api_key'  => 'QbGcoO0Qds9sQFDmY0MWg1Tq.xtuh1',
            'timeout'  => 10,
        ]);

        $ref = new \ReflectionMethod(iPaymuService::class, 'encodeBody');
        $ref->setAccessible(true);

        $body = $ref->invoke($svc, [
            'amount'    => 13300,
            'notifyUrl' => 'https://www.neonflux.my.id/api/ipaymu/callback',
            'referenceId' => 'REF-1',
        ]);

        $this->assertStringContainsString('https://www.neonflux.my.id/api/ipaymu/callback', $body);
        $this->assertStringNotContainsString('\\/', $body);
        $this->assertNotFalse(json_decode($body, true));
    }
}

// ──────────────────────────────────────────────────────────────
// Fakes
// ──────────────────────────────────────────────────────────────

class FakeIpaymuService extends iPaymuService
{
    public function __construct() {}

    public function inquire(string $transactionId): array
    {
        return [
            'status'  => 'success',
            'verdict' => 'known',
            'error'   => null,
            'raw'     => ['Data' => ['Status' => 1]],
            'http_code' => 200,
        ];
    }

    public static function isConfigured(): bool
    {
        return true;
    }

    public static function credentials(): array
    {
        return [
            'base_url' => 'https://sandbox.ipaymu.com',
            'merchant_id' => '123456',
            'va' => '123456',
            'api_key' => 'apikey-xyz',
            'secret_key' => '',
            'payout_enabled' => false,
            'webhook_secret' => '',
            'timeout' => 30,
        ];
    }
}

class FakeIpaymuPaymentService extends IpaymuPaymentService
{
    public array $methods = [];
    public bool $configured = true;
    public ?array $createResult = null;
    public ?array $redirectResult = null;
    public array $lastInput = [];
    public ?array $lastRedirectInput = null;
    public array $syncResult = ['status' => 'pending', 'known' => false, 'error' => null];
    public string $webhookStatus = 'paid';

    public function __construct(?iPaymuService $ipaymu = null)
    {
        parent::__construct($ipaymu);
    }

    public function getPaymentMethods(): array
    {
        if (!$this->configured) {
            return ['success' => false, 'methods' => [], 'message' => 'iPaymu belum terkonfigurasi.'];
        }
        return ['success' => true, 'methods' => $this->methods, 'message' => null];
    }

    public function createPayment(array $input): array
    {
        $this->lastInput = $input;

        return $this->createResult ?? [
            'success' => false, 'error' => 'not set', 'payment_id' => null,
            'va_number' => null, 'qr_string' => null, 'payment_url' => null,
            'expired_at' => null, 'raw' => null,
        ];
    }

    public function createRedirectPayment(array $input): array
    {
        $this->lastRedirectInput = $input;

        return $this->redirectResult ?? [
            'success' => false, 'error' => 'redirect not set', 'session_id' => null,
            'payment_url' => null, 'expired_at' => null, 'http_code' => null, 'raw' => null,
        ];
    }

    public function syncStatus(\App\Models\Payment $payment): array
    {
        return $this->syncResult;
    }

    public function webhookFinalStatus(string $transactionId, string $webhookStatus): string
    {
        return $this->webhookStatus;
    }
}
