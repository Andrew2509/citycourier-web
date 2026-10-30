<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\DanaConnection;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pengujian Lengkap Alur Pencairan DANA (metode aktif) — City Courier (12 Skenario Wajib).
 *
 * DANA adalah satu-satunya metode pencairan aktif; riwayat transaksi lama
 * (DANA maupun OVO) tetap utuh dan tidak diubah oleh alur baru ini.
 */
class DanaWithdrawalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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
            $table->string('provider', 20)->default('dana');
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
            $table->string('provider', 20)->default('dana');
            $table->string('bank_name')->default('DANA');
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

    private function connectDana(Courier $courier): void
    {
        $this->postJson('/api/courier/dana/mock-connect')->assertOk();
    }

    /**
     * Skenario 1: Kurir menghubungkan akun DANA (metode pencairan aktif)
     */
    public function test_01_courier_connects_dana_successfully(): void
    {
        $courier = $this->actingCourier();

        $response = $this->postJson('/api/courier/dana/mock-connect')->assertOk();

        $response->assertJson([
            'success' => true,
            'data'    => [
                'status' => 'connected',
            ],
        ]);

        $conn = DanaConnection::where('courier_id', $courier->id)->first();
        $this->assertNotNull($conn);
        $this->assertSame('dana', $conn->provider);
        $this->assertSame('connected', $conn->status);

        // Wallet otomatis aktif setelah terhubung
        $wallet = Wallet::where('courier_id', $courier->id)->first();
        $this->assertNotNull($wallet);
        $this->assertSame('active', $wallet->status);

        // Status endpoint mengembalikan CONNECTED untuk DANA
        $this->getJson('/api/courier/dana/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'CONNECTED')
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.provider', 'DANA');
    }

    /**
     * Skenario 2: Format nomor HP DANA tidak valid (ditolak endpoint verifikasi)
     */
    public function test_02_invalid_dana_phone_format_rejected(): void
    {
        $courier = $this->actingCourier();

        // Terlalu pendek — validasi backend menolak sebelum menyimpan
        $this->postJson('/api/courier/dana/verify', [
            'phone_number' => '0812',
        ])->assertStatus(422);

        // Tidak ada koneksi tersimpan
        $conn = DanaConnection::where('courier_id', $courier->id)->first();
        $this->assertNull($conn);

        // Status tetap NOT_CONNECTED
        $this->getJson('/api/courier/dana/status')
            ->assertOk()
            ->assertJsonPath('data.connected', false);
    }

    /**
     * Skenario 3: Penarikan ditolak ketika akun DANA belum terhubung
     */
    public function test_03_withdrawal_rejected_when_dana_not_connected(): void
    {
        $courier = $this->actingCourier();
        $walletService = new WalletService();
        $walletService->credit($courier->id, 100000, 'earning', null, 'Saldo awal');

        $this->postJson('/api/courier/withdrawals', [
            'amount'          => 50000,
            'idempotency_key' => 'IDEMP-NOT-LINKED-001',
        ])->assertStatus(400)
          ->assertJsonPath('message', 'Metode pencairan DANA belum terhubung.');

        // Saldo tidak berkurang
        $wallet = Wallet::where('courier_id', $courier->id)->first();
        $this->assertEquals(100000, (float) $wallet->available_balance);
    }

    /**
     * Skenario 4: Penarikan saldo berhasil (diproses lalu diverifikasi via inquiry DANA)
     */
    public function test_04_withdrawal_succeeds_with_sufficient_balance(): void
    {
        $courier = $this->actingCourier();
        $walletService = new WalletService();

        $this->connectDana($courier);

        // Isi saldo wallet kurir Rp 100.000
        $walletService->credit($courier->id, 100000, 'earning', null, 'Saldo awal');

        $response = $this->postJson('/api/courier/withdrawals', [
            'amount'          => 50000,
            'idempotency_key' => 'IDEMP-WD-001',
        ])->assertCreated();

        $response->assertJson([
            'success' => true,
            'data'    => [
                'amount'      => 50000,
                'status'      => 'processing',
                'provider'    => 'DANA',
                'destination' => 'DANA',
            ],
        ]);

        // Saldo ditahan selama pencairan diproses
        $wallet = Wallet::where('courier_id', $courier->id)->first();
        $this->assertEquals(50000, (float) $wallet->available_balance);
        $this->assertEquals(50000, (float) $wallet->pending_balance);

        // Verifikasi status via inquiry DANA → success, dana dilepas dari pending
        $this->getJson('/api/courier/withdrawals/IDEMP-WD-001/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'success');

        $wallet->refresh();
        $this->assertEquals(50000, (float) $wallet->available_balance);
        $this->assertEquals(0, (float) $wallet->pending_balance);

        // Tercatat di riwayat penarikan dengan provider DANA
        $this->assertDatabaseHas('withdrawals', [
            'courier_id'      => $courier->id,
            'idempotency_key' => 'IDEMP-WD-001',
            'provider'        => 'dana',
            'status'          => 'success',
        ]);
    }

    /**
     * Skenario 5: Penarikan saldo ditolak karena saldo tidak mencukupi
     */
    public function test_05_withdrawal_rejected_when_balance_insufficient(): void
    {
        $courier = $this->actingCourier();
        $walletService = new WalletService();

        $this->connectDana($courier);

        // Isi saldo hanya Rp 20.000
        $walletService->credit($courier->id, 20000, 'earning', null, 'Saldo awal');

        $this->postJson('/api/courier/withdrawals', [
            'amount'          => 50000,
            'idempotency_key' => 'IDEMP-WD-INSUF',
        ])->assertStatus(400)
          ->assertJsonPath('message', 'Saldo tidak mencukupi.');

        // Saldo tidak berkurang
        $wallet = Wallet::where('courier_id', $courier->id)->first();
        $this->assertEquals(20000, (float) $wallet->available_balance);
        $this->assertEquals(0, (float) $wallet->pending_balance);
    }

    /**
     * Skenario 6: Transaksi berhasil (saldo berkurang dari pending, tercatat di mutasi ledger)
     */
    public function test_06_successful_transaction_records_ledger_entries(): void
    {
        $courier = $this->actingCourier();
        $walletService = new WalletService();

        $this->connectDana($courier);

        $walletService->credit($courier->id, 100000, 'earning', null, 'Pemasukan order');

        $this->postJson('/api/courier/withdrawals', [
            'amount'          => 30000,
            'idempotency_key' => 'IDEMP-LEDGER-01',
        ])->assertCreated();

        // Saat dana ditahan: mutasi hold tercatat (status pending sampai provider mengonfirmasi)
        $holdTxn = WalletTransaction::where('courier_id', $courier->id)
            ->where('type', 'withdrawal_hold')
            ->first();
        $this->assertNotNull($holdTxn);
        $this->assertSame('pending', $holdTxn->status);

        // Verifikasi inquiry → success, mutasi debit pencairan tercatat
        $this->getJson('/api/courier/withdrawals/IDEMP-LEDGER-01/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'success');

        // Hold diselesaikan setelah provider mengonfirmasi sukses
        $holdTxn->refresh();
        $this->assertSame('completed', $holdTxn->status);

        $withdrawalTxn = WalletTransaction::where('courier_id', $courier->id)
            ->where('type', 'withdrawal')
            ->first();
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
        $withdrawalService = new WithdrawalService();

        $this->connectDana($courier);

        $walletService->credit($courier->id, 75000, 'earning', null, 'Saldo awal');

        $this->postJson('/api/courier/withdrawals', [
            'amount'          => 50000,
            'idempotency_key' => 'FAIL-WD-TEST-001',
        ])->assertCreated();

        // Dana tertahan selama proses
        $wallet = Wallet::where('courier_id', $courier->id)->first();
        $this->assertEquals(25000, (float) $wallet->available_balance);
        $this->assertEquals(50000, (float) $wallet->pending_balance);

        // Hasil gagal dari DANA (callback/inquiry) → dana dikembalikan
        $withdrawalService->handleStatusUpdate('FAIL-WD-TEST-001', 'failed');

        $this->assertDatabaseHas('withdrawals', [
            'courier_id'      => $courier->id,
            'idempotency_key' => 'FAIL-WD-TEST-001',
            'status'          => 'failed',
        ]);

        // Saldo pending kembali 0, saldo aktif tetap 75.000 (tidak hilang/terpotong)
        $wallet->refresh();
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

        $this->connectDana($courier);

        $walletService->credit($courier->id, 100000, 'earning', null, 'Saldo awal');

        // Pencairan DANA berstatus pending pada provider → status 'processing'
        $res = $this->postJson('/api/courier/withdrawals', [
            'amount'          => 40000,
            'idempotency_key' => 'TIMEOUT-WD-001',
        ])->assertCreated();

        $this->assertSame('processing', $res->json('data.status'));

        // Saldo terpotong dari available dan tertahan di pending
        $wallet = Wallet::where('courier_id', $courier->id)->first();
        $this->assertEquals(60000, (float) $wallet->available_balance);
        $this->assertEquals(40000, (float) $wallet->pending_balance);

        // Rekonsiliasi via sync status API (inquiry DANA) — bukan kirim ulang
        $syncRes = $this->getJson('/api/courier/withdrawals/TIMEOUT-WD-001/status')->assertOk();
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

        $this->connectDana($courier);

        $walletService->credit($courier->id, 100000, 'earning', null, 'Saldo awal');

        $this->postJson('/api/courier/withdrawals', [
            'amount'          => 50000,
            'idempotency_key' => 'TIMEOUT-DOUBLE-CB',
        ])->assertCreated();

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

        $this->connectDana($courier);

        $walletService->credit($courier->id, 200000, 'earning', null, 'Saldo');

        // Buat satu transaksi yang statusnya masih 'processing'
        $this->postJson('/api/courier/withdrawals', [
            'amount'          => 50000,
            'idempotency_key' => 'TIMEOUT-CONCURRENT-1',
        ])->assertCreated();

        // Percobaan penarikan kedua ditolak karena transaksi sebelumnya masih berjalan
        $this->postJson('/api/courier/withdrawals', [
            'amount'          => 30000,
            'idempotency_key' => 'CONCURRENT-2',
        ])->assertStatus(400)
          ->assertJsonPath('message', 'Penarikan sedang diproses. Mohon tunggu.');
    }

    /**
     * Skenario 11: Data transaksi lama DANA tetap terbaca dan historis utuh
     * (termasuk riwayat pencairan OVO yang pernah tercatat — tidak dihapus)
     */
    public function test_11_legacy_transaction_history_remains_intact(): void
    {
        $courier = $this->actingCourier();
        $wallet = Wallet::create([
            'courier_id'        => $courier->id,
            'status'            => 'active',
            'available_balance' => 150000,
            'pending_balance'   => 0,
        ]);

        // Rekam riwayat DANA lama
        Withdrawal::create([
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

        // Rekam riwayat OVO yang pernah tercatat (tidak boleh hilang)
        Withdrawal::create([
            'courier_id'         => $courier->id,
            'wallet_id'          => $wallet->id,
            'provider'           => 'ovo',
            'bank_name'          => 'OVO',
            'account_number'     => '0812****5678',
            'account_name'       => 'Akun OVO Lama',
            'amount'             => 30000,
            'fee'                => 2500,
            'net_amount'         => 27500,
            'status'             => 'success',
            'idempotency_key'    => 'OVO-LEGACY-001',
            'provider_reference' => 'OVO-TX-LEGACY-555',
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

        // Cek API daftar penarikan: kedua riwayat tetap terbaca utuh
        $response = $this->getJson('/api/courier/withdrawals')->assertOk();
        $items = $response->json('data.withdrawals');

        $this->assertCount(2, $items);
        $providers = array_column($items, 'provider');
        $this->assertContains('DANA', $providers);
        $this->assertContains('OVO', $providers);

        $danaItem = collect($items)->firstWhere('provider', 'DANA');
        $this->assertSame('0812****9999', $danaItem['account_number']);

        // Cek API mutasi dompet
        $txResponse = $this->getJson('/api/courier/wallet/transactions')->assertOk();
        $txItems = $txResponse->json('data.transactions');
        $this->assertCount(1, $txItems);
        $this->assertSame('Penarikan ke DANA', $txItems[0]['description']);
    }

    /**
     * Skenario 12: Saldo dompet kurir tetap akurat setelah alur DANA aktif
     */
    public function test_12_courier_wallet_balance_accurate_with_dana_flow(): void
    {
        $courier = $this->actingCourier();
        $walletService = new WalletService();

        // 1. Saldo awal
        $walletService->credit($courier->id, 500000, 'earning', null, 'Order delivered');

        $balanceBefore = $walletService->getBalance($courier->id);
        $this->assertEquals(500000, $balanceBefore['available_balance']);
        $this->assertEquals(0, $balanceBefore['pending_balance']);
        $this->assertEquals(500000, $balanceBefore['total_balance']);

        // 2. Hubungkan DANA
        $this->postJson('/api/courier/dana/mock-connect')->assertOk();

        // Saldo tidak boleh berubah karena sekadar menghubungkan DANA
        $balanceAfterLink = $walletService->getBalance($courier->id);
        $this->assertEquals(500000, $balanceAfterLink['available_balance']);
        $this->assertEquals(500000, $balanceAfterLink['total_balance']);

        // 3. Tarik saldo Rp 100.000 ke DANA
        $this->postJson('/api/courier/withdrawals', [
            'amount'          => 100000,
            'idempotency_key' => 'ACCURATE-TEST-001',
        ])->assertCreated();

        // Saat diproses: dana ditahan di pending, available berkurang tepat 100.000
        $balanceHeld = $walletService->getBalance($courier->id);
        $this->assertEquals(400000, $balanceHeld['available_balance']);
        $this->assertEquals(100000, $balanceHeld['pending_balance']);

        // 4. Verifikasi status via inquiry DANA → success, pending dilepas
        $this->getJson('/api/courier/withdrawals/ACCURATE-TEST-001/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'success');

        $balanceFinal = $walletService->getBalance($courier->id);
        $this->assertEquals(400000, $balanceFinal['available_balance']);
        $this->assertEquals(0, $balanceFinal['pending_balance']);
        $this->assertEquals(400000, $balanceFinal['total_balance']);
        $this->assertEquals(500000, $balanceFinal['total_earnings']);
    }
}
