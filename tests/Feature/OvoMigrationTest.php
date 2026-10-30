<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\DanaConnection;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\MockOvoProvider;
use App\Services\OvoService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pengujian Lengkap Migrasi DANA ke OVO — City Courier (12 Skenario Wajib).
 */
class OvoMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        MockOvoProvider::resetOverrides();
        $this->dropSchema();
        $this->createSchema();
    }

    private function dropSchema(): void
    {
        foreach (['wallet_transactions', 'withdrawals', 'wallets', 'dana_connections', 'couriers', 'settings', 'users'] as $table) {
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
            $table->string('provider', 20)->default('ovo');
            $table->string('account_name', 150)->nullable();
            $table->text('phone_encrypted')->nullable();
            $table->timestamp('consent_at')->nullable();
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
            $table->foreignId('wallet_id')->nullable();
            $table->foreignId('dana_connection_id')->nullable();
            $table->string('provider', 20)->default('ovo');
            $table->string('bank_name')->default('OVO');
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('fee', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->string('idempotency_key', 50)->nullable();
            $table->string('reference')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function ($table) {
            $table->id();
            $table->foreignId('wallet_id');
            $table->foreignId('courier_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('type');
            $table->decimal('amount', 15, 2);
            $table->decimal('fee', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->string('status')->default('completed');
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    private function makeCourier(string $name = 'Budi Santoso', string $phone = '081234567890'): Courier
    {
        $user = User::create([
            'name'     => $name,
            'email'    => fake()->unique()->safeEmail(),
            'password' => Hash::make('secret'),
            'phone'    => $phone,
        ]);

        return Courier::create([
            'user_id'     => $user->id,
            'phone'       => $phone,
            'is_verified' => true,
            'is_active'   => true,
        ]);
    }

    private function actingCourier(string $name = 'Budi Santoso', string $phone = '081234567890'): Courier
    {
        $courier = $this->makeCourier($name, $phone);
        Sanctum::actingAs($courier->user);

        return $courier;
    }

    /**
     * Skenario 1: Kurir menghubungkan nomor OVO (berhasil verifikasi & binding)
     */
    public function test_01_courier_connects_ovo_successfully(): void
    {
        $courier = $this->actingCourier();

        $response = $this->postJson('/api/courier/ovo/connect', [
            'phone_number' => '081234567890',
            'account_name' => 'Budi Santoso',
            'consent'      => true,
        ])->assertOk();

        $response->assertJson([
            'success' => true,
            'data'    => [
                'status'       => 'CONNECTED',
                'provider'     => 'OVO',
                'connected'    => true,
                'masked_phone' => '0812****7890',
            ],
        ]);

        $conn = DanaConnection::where('courier_id', $courier->id)->first();
        $this->assertNotNull($conn);
        $this->assertSame('ovo', $conn->provider);
        $this->assertSame('connected', $conn->status);
        $this->assertSame('0812****7890', $conn->masked_phone);
        $this->assertNotNull($conn->consent_at);

        // Nomor tersimpan terenkripsi aman
        $this->assertNotNull($conn->phone_encrypted);
        $this->assertSame('081234567890', Crypt::decryptString($conn->phone_encrypted));

        // Wallet otomatis aktif setelah terhubung
        $wallet = Wallet::where('courier_id', $courier->id)->first();
        $this->assertNotNull($wallet);
        $this->assertSame('active', $wallet->status);

        // Status endpoint mengembalikan CONNECTED
        $this->getJson('/api/courier/ovo/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'CONNECTED')
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.provider', 'OVO');
    }

    /**
     * Skenario 2: Format nomor HP OVO tidak valid (ditolak)
     */
    public function test_02_invalid_ovo_phone_format_rejected(): void
    {
        $this->actingCourier();

        // Terlalu pendek
        $this->postJson('/api/courier/ovo/connect', [
            'phone_number' => '0812',
            'account_name' => 'Budi',
            'consent'      => true,
        ])->assertStatus(400)
          ->assertJsonPath('error_code', 'INVALID_PHONE_FORMAT');

        // Karakter non-angka / bukan nomor seluler Indonesia
        $this->postJson('/api/courier/ovo/connect', [
            'phone_number' => '0217654321', // PSTN / telepon rumah
            'account_name' => 'Budi',
            'consent'      => true,
        ])->assertStatus(400)
          ->assertJsonPath('error_code', 'INVALID_PHONE_FORMAT');
    }

    /**
     * Skenario 3: Nomor HP OVO tidak terdaftar (ditolak oleh Account Inquiry OVO)
     */
    public function test_03_unregistered_ovo_number_rejected(): void
    {
        $this->actingCourier();

        // 080000000000 disimulasikan sebagai nomor tidak terdaftar di MockOvoProvider
        $this->postJson('/api/courier/ovo/connect', [
            'phone_number' => '080000000000',
            'account_name' => 'Budi Santoso',
            'consent'      => true,
        ])->assertStatus(400)
          ->assertJsonPath('error_code', 'OVO_ACCOUNT_NOT_FOUND');

        // Status tetap NOT_CONNECTED
        $this->getJson('/api/courier/ovo/status')
            ->assertOk()
            ->assertJsonPath('data.connected', false);
    }

    /**
     * Skenario 4: Penarikan saldo berhasil dengan saldo mencukupi
     */
    public function test_04_withdrawal_succeeds_with_sufficient_balance(): void
    {
        $courier = $this->actingCourier();
        $walletService = new WalletService();

        // Hubungkan OVO
        $this->postJson('/api/courier/ovo/connect', [
            'phone_number' => '081234567890',
            'account_name' => 'Budi Santoso',
            'consent'      => true,
        ]);

        // Isi saldo wallet kurir Rp 100.000
        $walletService->credit($courier->id, 100000, 'earning', null, 'Saldo awal');

        $response = $this->postJson('/api/courier/ovo/withdraw', [
            'amount'          => 50000,
            'idempotency_key' => 'IDEMP-WD-001',
        ])->assertCreated();

        $response->assertJson([
            'success' => true,
            'data'    => [
                'amount'     => 50000,
                'status'     => 'success',
                'provider'   => 'OVO',
                'destination'=> 'OVO',
            ],
        ]);

        $wallet = Wallet::where('courier_id', $courier->id)->first();
        $this->assertEquals(50000, (float) $wallet->available_balance);
        $this->assertEquals(0, (float) $wallet->pending_balance);
    }

    /**
     * Skenario 5: Penarikan saldo ditolak karena saldo tidak mencukupi
     */
    public function test_05_withdrawal_rejected_when_balance_insufficient(): void
    {
        $courier = $this->actingCourier();
        $walletService = new WalletService();

        $this->postJson('/api/courier/ovo/connect', [
            'phone_number' => '081234567890',
            'account_name' => 'Budi Santoso',
            'consent'      => true,
        ]);

        // Isi saldo hanya Rp 20.000
        $walletService->credit($courier->id, 20000, 'earning', null, 'Saldo awal');

        $this->postJson('/api/courier/ovo/withdraw', [
            'amount'          => 50000,
            'idempotency_key' => 'IDEMP-WD-INSUF',
        ])->assertStatus(400)
          ->assertJsonPath('message', 'Saldo tidak mencukupi.');

        // Saldo tidak berkurang
        $wallet = Wallet::where('courier_id', $courier->id)->first();
        $this->assertEquals(20000, (float) $wallet->available_balance);
    }

    /**
     * Skenario 6: Transaksi berhasil (saldo berkurang dari pending, tercatat di mutasi ledger)
     */
    public function test_06_successful_transaction_records_ledger_entries(): void
    {
        $courier = $this->actingCourier();
        $walletService = new WalletService();

        $this->postJson('/api/courier/ovo/connect', [
            'phone_number' => '081234567890',
            'account_name' => 'Budi Santoso',
            'consent'      => true,
        ]);

        $walletService->credit($courier->id, 100000, 'earning', null, 'Pemasukan order');

        $this->postJson('/api/courier/ovo/withdraw', [
            'amount'          => 30000,
            'idempotency_key' => 'IDEMP-LEDGER-01',
        ])->assertCreated();

        // Cek riwayat mutasi di wallet_transactions
        $transactions = WalletTransaction::where('courier_id', $courier->id)->get();

        $holdTxn = $transactions->where('type', 'withdrawal_hold')->first();
        $this->assertNotNull($holdTxn);
        $this->assertSame('completed', $holdTxn->status);

        $withdrawalTxn = $transactions->where('type', 'withdrawal')->first();
        $this->assertNotNull($withdrawalTxn);
        $this->assertEquals(-30000, (float) $withdrawalTxn->amount);
    }

    /**
     * Skenario 7: Transaksi gagal (saldo dikembalikan ke saldo aktif)
     */
    public function test_07_failed_transaction_releases_reserved_balance_to_available(): void
    {
        $courier = $this->actingCourier();
        $walletService = new WalletService();

        $this->postJson('/api/courier/ovo/connect', [
            'phone_number' => '081234567890',
            'account_name' => 'Budi Santoso',
            'consent'      => true,
        ]);

        $walletService->credit($courier->id, 75000, 'earning', null, 'Saldo awal');

        // Gunakan idempotency key berisi FAIL agar MockOvoProvider menyimulasikan kegagalan
        $response = $this->postJson('/api/courier/ovo/withdraw', [
            'amount'          => 50000,
            'idempotency_key' => 'FAIL-WD-TEST-001',
        ]);

        $this->assertDatabaseHas('withdrawals', [
            'courier_id'      => $courier->id,
            'idempotency_key' => 'FAIL-WD-TEST-001',
            'status'          => 'failed',
        ]);

        // Saldo pending kembali 0, saldo aktif tetap 75.000 (tidak hilang/terpotong)
        $wallet = Wallet::where('courier_id', $courier->id)->first();
        $this->assertEquals(75000, (float) $wallet->available_balance);
        $this->assertEquals(0, (float) $wallet->pending_balance);

        // Tercatat mutasi release
        $this->assertDatabaseHas('wallet_transactions', [
            'courier_id' => $courier->id,
            'type'       => 'withdrawal_release',
            'amount'     => 50000,
        ]);
    }

    /**
     * Skenario 8: Transaksi timeout (status processing, saldo tetap tertahan di pending, dapat direkonsiliasi)
     */
    public function test_08_transaction_timeout_holds_funds_and_reconciles(): void
    {
        $courier = $this->actingCourier();
        $walletService = new WalletService();
        $withdrawalService = new WithdrawalService();

        $this->postJson('/api/courier/ovo/connect', [
            'phone_number' => '081234567890',
            'account_name' => 'Budi Santoso',
            'consent'      => true,
        ]);

        $walletService->credit($courier->id, 100000, 'earning', null, 'Saldo awal');

        // Reference berisi TIMEOUT untuk menyimulasikan status pending/processing
        $res = $this->postJson('/api/courier/ovo/withdraw', [
            'amount'          => 40000,
            'idempotency_key' => 'TIMEOUT-WD-001',
        ])->assertCreated();

        $this->assertSame('processing', $res->json('data.status'));

        // Saldo terpotong dari available dan tertahan di pending
        $wallet = Wallet::where('courier_id', $courier->id)->first();
        $this->assertEquals(60000, (float) $wallet->available_balance);
        $this->assertEquals(40000, (float) $wallet->pending_balance);

        // Rekonsiliasi via sync status API
        $syncRes = $this->getJson('/api/courier/ovo/withdrawals/TIMEOUT-WD-001/status')->assertOk();
        $this->assertSame('success', $syncRes->json('data.status'));

        // Setelah rekonsiliasi sukses, pending balance berkurang dan debit selesai
        $wallet->refresh();
        $this->assertEquals(60000, (float) $wallet->available_balance);
        $this->assertEquals(0, (float) $wallet->pending_balance);
    }

    /**
     * Skenario 9: Callback diterima dua kali (idempotent, tidak double credit/debit)
     */
    public function test_09_duplicate_callback_is_idempotent(): void
    {
        $courier = $this->actingCourier();
        $walletService = new WalletService();
        $withdrawalService = new WithdrawalService();

        $this->postJson('/api/courier/ovo/connect', [
            'phone_number' => '081234567890',
            'account_name' => 'Budi Santoso',
            'consent'      => true,
        ]);

        $walletService->credit($courier->id, 100000, 'earning', null, 'Saldo awal');

        $this->postJson('/api/courier/ovo/withdraw', [
            'amount'          => 50000,
            'idempotency_key' => 'TIMEOUT-DOUBLE-CB',
        ]);

        $wd = Withdrawal::where('idempotency_key', 'TIMEOUT-DOUBLE-CB')->first();
        $this->assertNotNull($wd);

        // Callback 1
        $withdrawalService->handleStatusUpdate('TIMEOUT-DOUBLE-CB', 'success');
        $wallet = Wallet::where('courier_id', $courier->id)->first();
        $this->assertEquals(50000, (float) $wallet->available_balance);
        $this->assertEquals(0, (float) $wallet->pending_balance);

        $debitCount1 = WalletTransaction::where('courier_id', $courier->id)
            ->where('type', 'withdrawal')
            ->count();
        $this->assertSame(1, $debitCount1);

        // Callback 2 (duplikat)
        $withdrawalService->handleStatusUpdate('TIMEOUT-DOUBLE-CB', 'success');
        $wallet->refresh();
        $this->assertEquals(50000, (float) $wallet->available_balance);
        $this->assertEquals(0, (float) $wallet->pending_balance);

        $debitCount2 = WalletTransaction::where('courier_id', $courier->id)
            ->where('type', 'withdrawal')
            ->count();
        $this->assertSame(1, $debitCount2); // Tidak terjadi double debit!
    }

    /**
     * Skenario 10: Kurir mencoba tarik saldo dua kali bersamaan (dicegah, status pending menahan)
     */
    public function test_10_concurrent_withdrawal_attempt_is_blocked(): void
    {
        $courier = $this->actingCourier();
        $walletService = new WalletService();

        $this->postJson('/api/courier/ovo/connect', [
            'phone_number' => '081234567890',
            'account_name' => 'Budi Santoso',
            'consent'      => true,
        ]);

        $walletService->credit($courier->id, 200000, 'earning', null, 'Saldo');

        // Buat satu transaksi yang statusnya masih 'processing'
        $this->postJson('/api/courier/ovo/withdraw', [
            'amount'          => 50000,
            'idempotency_key' => 'TIMEOUT-CONCURRENT-1',
        ])->assertCreated();

        // Percobaan penarikan kedua ditolak karena transaksi sebelumnya masih berjalan
        $this->postJson('/api/courier/ovo/withdraw', [
            'amount'          => 30000,
            'idempotency_key' => 'CONCURRENT-2',
        ])->assertStatus(400)
          ->assertJsonPath('message', 'Penarikan sedang diproses. Mohon tunggu.');
    }

    /**
     * Skenario 11: Data transaksi lama DANA tetap terbaca dan historis utuh
     */
    public function test_11_legacy_dana_transaction_history_remains_intact(): void
    {
        $courier = $this->actingCourier();
        $wallet = Wallet::create([
            'courier_id'        => $courier->id,
            'status'            => 'active',
            'available_balance' => 150000,
            'pending_balance'   => 0,
        ]);

        // Rekam riwayat DANA lama
        $legacyWd = Withdrawal::create([
            'courier_id'         => $courier->id,
            'wallet_id'          => $wallet->id,
            'provider'           => 'dana',
            'bank_name'          => 'DANA',
            'account_number'     => '0812****9999',
            'account_name'       => 'Akun DANA Lama',
            'amount'             => 50000,
            'fee'                => 2500,
            'net_amount'         => 47500,
            'status'             => 'success',
            'idempotency_key'    => 'DANA-LEGACY-001',
            'provider_reference' => 'DANA-TX-LEGACY-777',
        ]);

        WalletTransaction::create([
            'wallet_id'   => $wallet->id,
            'courier_id'  => $courier->id,
            'type'        => 'withdrawal',
            'amount'      => -50000,
            'fee'         => 2500,
            'net_amount'  => -50000,
            'status'      => 'completed',
            'reference'   => 'DANA-LEGACY-001',
            'description' => 'Penarikan ke DANA',
        ]);

        // Cek API daftar penarikan
        $response = $this->getJson('/api/courier/withdrawals')->assertOk();
        $items = $response->json('data.withdrawals');

        $this->assertCount(1, $items);
        $this->assertSame('DANA', $items[0]['provider']);
        $this->assertSame('DANA', $items[0]['destination']);
        $this->assertSame('0812****9999', $items[0]['account_number']);

        // Cek API mutasi dompet
        $txResponse = $this->getJson('/api/courier/wallet/transactions')->assertOk();
        $txItems = $txResponse->json('data.transactions');
        $this->assertCount(1, $txItems);
        $this->assertSame('Penarikan ke DANA', $txItems[0]['description']);
    }

    /**
     * Skenario 12: Saldo dompet kurir tetap akurat setelah migrasi
     */
    public function test_12_courier_wallet_balance_accurate_after_migration(): void
    {
        $courier = $this->actingCourier();
        $walletService = new WalletService();

        // 1. Saldo awal sebelum migrasi
        $walletService->credit($courier->id, 500000, 'earning', null, 'Order delivered');

        $balanceBefore = $walletService->getBalance($courier->id);
        $this->assertEquals(500000, $balanceBefore['available_balance']);
        $this->assertEquals(0, $balanceBefore['pending_balance']);
        $this->assertEquals(500000, $balanceBefore['total_balance']);

        // 2. Hubungkan OVO
        $this->postJson('/api/courier/ovo/connect', [
            'phone_number' => '081234567890',
            'account_name' => 'Budi Santoso',
            'consent'      => true,
        ])->assertOk();

        // Saldo tidak boleh berubah karena sekadar menghubungkan OVO
        $balanceAfterLink = $walletService->getBalance($courier->id);
        $this->assertEquals(500000, $balanceAfterLink['available_balance']);
        $this->assertEquals(500000, $balanceAfterLink['total_balance']);

        // 3. Tarik saldo Rp 100.000 ke OVO
        $this->postJson('/api/courier/ovo/withdraw', [
            'amount'          => 100000,
            'idempotency_key' => 'ACCURATE-TEST-001',
        ])->assertCreated();

        // Saldo berkurang tepat Rp 100.000
        $balanceFinal = $walletService->getBalance($courier->id);
        $this->assertEquals(400000, $balanceFinal['available_balance']);
        $this->assertEquals(0, $balanceFinal['pending_balance']);
        $this->assertEquals(400000, $balanceFinal['total_balance']);
        $this->assertEquals(500000, $balanceFinal['total_earnings']);
    }
}

