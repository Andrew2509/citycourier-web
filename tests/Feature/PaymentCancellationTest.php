<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\IpaymuPaymentService;
use App\Services\iPaymuService;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pembatalan pembayaran iPaymu.
 *
 * Fakta yang dijaga oleh test ini: iPaymu TIDAK punya API cancel/void/refund.
 * Karena itu pembatalan tidak boleh mengaku "dibatalkan di iPaymu" kalau yang
 * terjadi hanya penutupan lokal, dan pembayaran yang sudah dibayar tidak boleh
 * dianggap pembatalan biasa.
 */
class PaymentCancellationTest extends TestCase
{
    protected FakeCancelIpaymuService $fakeIpaymu;
    protected FakeCancelPaymentService $fakePayment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropSchema();
        $this->createSchema();

        iPaymuService::flushCredentialsCache();

        $this->fakeIpaymu = new FakeCancelIpaymuService();
        $this->fakePayment = new FakeCancelPaymentService($this->fakeIpaymu);

        $this->app->bind(iPaymuService::class, fn () => $this->fakeIpaymu);
        $this->app->bind(IpaymuPaymentService::class, fn () => $this->fakePayment);
    }

    protected function tearDown(): void
    {
        iPaymuService::flushCredentialsCache();
        parent::tearDown();
    }

    private function dropSchema(): void
    {
        foreach (['payments', 'shipment_logs', 'orders', 'shipments', 'withdrawals', 'settings', 'roles', 'model_has_roles', 'users'] as $table) {
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

        // Dicek oleh ShipmentController::cancel untuk membedakan admin/pelanggan.
        Schema::create('roles', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function ($table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
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

        // Webhook juga memeriksa tabel withdrawals (callback payout kurir).
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

        Schema::create('shipments', function ($table) {
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

        Schema::create('shipment_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('shipment_id');
            $table->unsignedBigInteger('courier_id')->nullable();
            $table->string('status');
            $table->string('location')->nullable();
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
            'name'     => 'Tester',
            'email'    => 'tester-cancel@example.com',
            'password' => bcrypt('secret'),
            'phone'    => '08123456789',
        ]);
    }

    private function configure(): void
    {
        Setting::create(['key' => 'ipaymu_va', 'value' => '123456', 'group' => 'ipaymu']);
        Setting::create(['key' => 'ipaymu_api_key', 'value' => 'apikey-xyz', 'group' => 'ipaymu']);
        Setting::create(['key' => 'ipaymu_base_url', 'value' => 'https://sandbox.ipaymu.com', 'group' => 'ipaymu']);
        iPaymuService::flushCredentialsCache();
    }

    private function payment(User $user, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'user_id'      => $user->id,
            'payment_id'   => '99001',
            'payment_type' => 'bank_transfer',
            'channel_code' => 'BNI',
            'amount'       => 13300,
            'status'       => 'pending',
            'va_number'    => '8808123456799',
            'metadata'     => [
                'ipaymu_reference_id' => 'CC-94-20261030120000',
                'payment_mode'        => 'direct',
            ],
        ], $overrides));
    }

    private function cancel(Payment $payment)
    {
        Sanctum::actingAs(User::find($payment->user_id));

        return $this->postJson('/api/payment/cancel', ['payment_id' => $payment->payment_id]);
    }

    private function signatureFor(array $payload): string
    {
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

        return hash_hmac('sha256', json_encode($normalized), '123456');
    }

    // ─── 1. iPaymu tidak punya API pembatalan ──────────────────

    /**
     * Transaksi masih pending di iPaymu: pembayaran ditutup LOKAL dan dicatat
     * eksplisit bahwa iPaymu tidak menyediakan API pembatalan (kode bayar akan
     * kedaluwarsa sendiri). Kita tidak mengklaim sudah batal di iPaymu.
     */
    public function test_pending_payment_closed_locally_without_claiming_provider_cancel(): void
    {
        $this->configure();
        $user = $this->user();
        $payment = $this->payment($user);

        $this->fakePayment->syncResult = [
            'status' => 'pending', 'known' => true, 'error' => null,
            'provider_code' => '0', 'raw' => ['Data' => ['Status' => 0]],
        ];

        $response = $this->cancel($payment);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('payment.status', 'canceled')
            ->assertJsonPath('payment.provider_cancel_api', false)
            ->assertJsonPath('payment.amount', 13300)
            ->assertJsonPath('payment.reference_id', 'CC-94-20261030120000');

        $fresh = $payment->fresh();
        $this->assertSame('canceled', $fresh->status);
        $this->assertSame('provider_pending', $fresh->metadata['cancellation']['result']);
        $this->assertFalse($fresh->metadata['cancellation']['provider_cancel_api']);
        $this->assertFalse($fresh->metadata['cancellation']['refund_required']);
        $this->assertSame('Menunggu pembayaran', $fresh->metadata['cancellation']['provider_status_desc']);
    }

    /** Pembayaran sudah dibayar → bukan pembatalan biasa, tapi jalur refund. */
    public function test_paid_payment_requires_refund_and_is_not_cancelled(): void
    {
        $this->configure();
        $user = $this->user();
        $payment = $this->payment($user, ['order_id' => 5]);

        $this->fakePayment->syncResult = [
            'status' => 'paid', 'known' => true, 'error' => null,
            'provider_code' => '1', 'raw' => ['Data' => ['Status' => 1]],
        ];

        $response = $this->cancel($payment);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'payment_already_paid');

        $fresh = $payment->fresh();
        // Uang benar-benar masuk → status lokal wajib ikut paid.
        $this->assertSame('paid', $fresh->status);
        $this->assertNotNull($fresh->paid_at);
        $this->assertTrue($fresh->metadata['cancellation']['refund_required']);
        $this->assertSame('requires_refund', $fresh->metadata['cancellation']['result']);
    }

    public function test_expired_transaction_syncs_local_status(): void
    {
        $this->configure();
        $user = $this->user();
        $payment = $this->payment($user);

        $this->fakePayment->syncResult = [
            'status' => 'expired', 'known' => true, 'error' => null,
            'provider_code' => '-2', 'raw' => ['Data' => ['Status' => -2]],
        ];

        $this->cancel($payment)->assertOk()->assertJsonPath('payment.status', 'expired');
        $this->assertSame('expired', $payment->fresh()->status);
    }

    public function test_cancelled_transaction_at_provider_syncs_local_status(): void
    {
        $this->configure();
        $user = $this->user();
        $payment = $this->payment($user);

        $this->fakePayment->syncResult = [
            'status' => 'canceled', 'known' => true, 'error' => null,
            'provider_code' => '2', 'raw' => ['Data' => ['Status' => 2]],
        ];

        $this->cancel($payment)->assertOk();
        $this->assertSame('canceled', $payment->fresh()->status);
        $this->assertSame('Dibatalkan oleh iPaymu', $payment->fresh()->metadata['cancellation']['provider_status_desc']);
    }

    /** Status tidak bisa dipastikan → tidak ada yang diubah (bukan "berhasil"). */
    public function test_unknown_provider_status_changes_nothing(): void
    {
        $this->configure();
        $user = $this->user();
        $payment = $this->payment($user);

        $this->fakePayment->syncResult = [
            'status' => 'pending', 'known' => false, 'error' => 'timeout',
            'provider_code' => null, 'raw' => null,
        ];

        $this->cancel($payment)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'payment_status_unknown');

        $fresh = $payment->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertArrayNotHasKey('cancellation', (array) $fresh->metadata);
    }

    /** Tombol ditekan berkali-kali: tidak ada permintaan ulang ke iPaymu. */
    public function test_repeated_cancel_is_idempotent(): void
    {
        $this->configure();
        $user = $this->user();
        $payment = $this->payment($user);

        $this->fakePayment->syncResult = [
            'status' => 'pending', 'known' => true, 'error' => null,
            'provider_code' => '0', 'raw' => ['Data' => ['Status' => 0]],
        ];

        $this->cancel($payment)->assertOk();
        $firstInquiryCount = $this->fakePayment->syncCalls;

        $this->cancel($payment)
            ->assertOk()
            ->assertJsonPath('already_final', true);

        $this->assertSame($firstInquiryCount, $this->fakePayment->syncCalls, 'iPaymu tidak boleh dipanggil ulang');
        $this->assertSame('canceled', $payment->fresh()->status);
    }

    public function test_other_users_cannot_cancel_foreign_payment(): void
    {
        $this->configure();
        $owner = $this->user();
        $payment = $this->payment($owner);

        $intruder = User::create([
            'name' => 'Intruder', 'email' => 'intruder@example.com',
            'password' => bcrypt('secret'),
        ]);
        Sanctum::actingAs($intruder);

        $this->postJson('/api/payment/cancel', ['payment_id' => $payment->payment_id])
            ->assertStatus(404);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    /** Pembayaran redirect: SessionID tidak bisa di-inquiry, tetap ditutup lokal. */
    public function test_redirect_session_without_trx_is_closed_locally(): void
    {
        $this->configure();
        $user = $this->user();
        $payment = $this->payment($user, [
            'payment_id' => 'SESS-REDIRECT-1',
            'metadata'   => [
                'ipaymu_reference_id' => 'CC-95-20261030120000',
                'payment_mode'        => 'redirect',
            ],
        ]);

        $this->cancel($payment)->assertOk();

        $fresh = $payment->fresh();
        $this->assertSame('canceled', $fresh->status);
        $this->assertSame(0, $this->fakePayment->syncCalls, 'Sesi redirect tidak di-inquiry');
        $this->assertSame('session', $fresh->metadata['cancellation']['provider_status']);
        $this->assertSame('provider_pending', $fresh->metadata['cancellation']['result']);
    }

    // ─── 2. Pembatalan pesanan (endpoint shipment) ────────────

    private function shipmentFor(User $user): \App\Models\Shipment
    {
        return \App\Models\Shipment::create([
            'user_id'         => $user->id,
            'shipment_number' => 'SHIP-CANCEL-1',
            'tracking_number' => 'RESI-CANCEL-1',
            'customer_phone'  => '08123456789',
            'sender_phone'    => '08123456789',
            'status'          => 'pending',
        ]);
    }

    /** Pembayaran sudah diterima → pesanan TIDAK dibatalkan, arahkan ke refund. */
    public function test_shipment_cancel_blocked_when_payment_paid(): void
    {
        $this->configure();
        $user = $this->user();
        $shipment = $this->shipmentFor($user);

        $payment = $this->payment($user, [
            'shipment_id' => $shipment->id,
            'payment_id'  => '99101',
        ]);

        $this->fakePayment->syncResult = [
            'status' => 'paid', 'known' => true, 'error' => null,
            'provider_code' => '1', 'raw' => ['Data' => ['Status' => 1]],
        ];

        Sanctum::actingAs($user);

        $this->postJson('/api/shipments/RESI-CANCEL-1/cancel')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'payment_already_paid');

        $this->assertSame('pending', $shipment->fresh()->status, 'Pesanan tidak boleh dibatalkan');
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertTrue($payment->fresh()->metadata['cancellation']['refund_required']);
    }

    /** Status pembayaran tidak pasti → pesanan tidak diubah (bisa dicoba lagi). */
    public function test_shipment_cancel_blocked_when_status_unknown(): void
    {
        $this->configure();
        $user = $this->user();
        $shipment = $this->shipmentFor($user);

        $payment = $this->payment($user, [
            'shipment_id' => $shipment->id,
            'payment_id'  => '99102',
        ]);

        $this->fakePayment->syncResult = ['status' => 'pending', 'known' => false, 'error' => 'timeout'];

        Sanctum::actingAs($user);

        $this->postJson('/api/shipments/RESI-CANCEL-1/cancel')
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'payment_status_unknown');

        $this->assertSame('pending', $shipment->fresh()->status);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    /** Pembayaran pending → pesanan batal, pembayaran ditutup lokal. */
    public function test_shipment_cancel_closes_pending_payment(): void
    {
        $this->configure();
        $user = $this->user();
        $shipment = $this->shipmentFor($user);

        $payment = $this->payment($user, [
            'shipment_id' => $shipment->id,
            'payment_id'  => '99103',
        ]);

        $this->fakePayment->syncResult = [
            'status' => 'pending', 'known' => true, 'error' => null,
            'provider_code' => '0', 'raw' => ['Data' => ['Status' => 0]],
        ];

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/shipments/RESI-CANCEL-1/cancel');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('payment.status', 'canceled')
            ->assertJsonPath('payment.cancellation_result', 'provider_pending');

        $this->assertSame('cancelled', $shipment->fresh()->status);
        $this->assertSame('canceled', $payment->fresh()->status);
    }

    /** Order tanpa transaksi pembayaran tetap bisa dibatalkan seperti biasa. */
    public function test_shipment_cancel_without_payment_still_works(): void
    {
        $this->configure();
        $user = $this->user();
        $shipment = $this->shipmentFor($user);

        Sanctum::actingAs($user);

        $this->postJson('/api/shipments/RESI-CANCEL-1/cancel')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('payment', null);

        $this->assertSame('cancelled', $shipment->fresh()->status);
        $this->assertSame(0, $this->fakePayment->syncCalls, 'Tanpa pembayaran, iPaymu tidak dipanggil');
    }

    /**
     * Daftar "belum dibayar" memakai `payment_info` untuk membuka layar
     * pembayaran; tanpa payment_id tombol Cek Status / Batalkan Pesanan tidak
     * punya transaksi untuk ditanyakan ke backend.
     */
    public function test_payment_info_exposes_transaction_id_for_pending_payment(): void
    {
        $this->configure();
        $user = $this->user();
        $shipment = $this->shipmentFor($user);

        $this->payment($user, [
            'shipment_id' => $shipment->id,
            'payment_id'  => '99200',
        ]);

        $info = $shipment->fresh()->payment_info;

        $this->assertSame('99200', $info['payment_id']);
        $this->assertSame('pending', $info['status']);
        $this->assertSame('8808123456799', $info['va_number'], 'Data VA tetap dikirim seperti sebelumnya');
    }

    // ─── 3. Webhook vs pembatalan ──────────────────────────────

    /**
     * Callback terlambat: uang tetap dihormati (status paid) tetapi ditandai
     * wajib refund — tidak diabaikan sebagai "sudah final".
     */
    public function test_late_paid_callback_after_cancellation_marks_refund_required(): void
    {
        $this->configure();
        $user = $this->user();

        $payment = $this->payment($user, [
            'payment_id' => '99077',
            'order_id'   => 9,
            'status'     => 'canceled',
            'metadata'   => [
                'ipaymu_reference_id' => 'CC-96-20261030120000',
                'payment_mode'        => 'direct',
                'cancellation'        => [
                    'result'            => 'provider_pending',
                    'provider_cancel_api' => false,
                    'refund_required'   => false,
                ],
            ],
        ]);

        \App\Models\Order::create(['id' => 9, 'payment_status' => 'unpaid']);

        $this->fakePayment->webhookStatus = 'paid';

        $payload = [
            'trx_id'          => 99077,
            'status_code'     => 1,
            'status'          => 'berhasil',
            'additional_info' => [],
        ];

        $this->postJson('/api/ipaymu/callback', $payload, [
            'X-Signature' => $this->signatureFor($payload),
        ])->assertOk();

        $fresh = $payment->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertNotNull($fresh->paid_at);
        $this->assertTrue($fresh->metadata['cancellation']['refund_required']);
        $this->assertNotNull($fresh->metadata['cancellation']['payment_after_cancel'] ?? null);
    }

    /** Pembayaran yang sudah dibatalkan tidak diproses ulang oleh callback gagal. */
    public function test_failed_callback_does_not_revive_cancelled_payment(): void
    {
        $this->configure();
        $user = $this->user();

        $payment = $this->payment($user, [
            'payment_id' => '99078',
            'status'     => 'canceled',
        ]);

        $this->fakePayment->webhookStatus = 'failed';

        $payload = [
            'trx_id'          => 99078,
            'status_code'     => 5,
            'status'          => 'gagal',
            'additional_info' => [],
        ];

        $this->postJson('/api/ipaymu/callback', $payload, [
            'X-Signature' => $this->signatureFor($payload),
        ])->assertOk();

        $this->assertSame('canceled', $payment->fresh()->status);
    }

    /** Callback tanpa signature sah tidak boleh mengubah pembayaran. */
    public function test_unsigned_callback_is_rejected(): void
    {
        $this->configure();
        $user = $this->user();
        $payment = $this->payment($user, ['payment_id' => '99079']);

        $this->postJson('/api/ipaymu/callback', [
            'trx_id' => 99079, 'status_code' => 1, 'status' => 'berhasil', 'additional_info' => [],
        ], ['X-Signature' => 'deadbeef'])->assertStatus(401);

        $this->assertSame('pending', $payment->fresh()->status);
    }
}

// ──────────────────────────────────────────────────────────────
// Fakes
// ──────────────────────────────────────────────────────────────

class FakeCancelIpaymuService extends iPaymuService
{
    public function __construct() {}

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

class FakeCancelPaymentService extends IpaymuPaymentService
{
    public int $syncCalls = 0;
    public array $syncResult = ['status' => 'pending', 'known' => false, 'error' => null];
    public string $webhookStatus = 'paid';

    public function __construct(?iPaymuService $ipaymu = null)
    {
        parent::__construct($ipaymu);
    }

    public function syncStatus(Payment $payment): array
    {
        $this->syncCalls++;

        return $this->syncResult;
    }

    public function webhookFinalStatus(string $transactionId, string $webhookStatus): string
    {
        return $this->webhookStatus;
    }
}
