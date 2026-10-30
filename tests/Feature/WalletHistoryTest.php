<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\DanaConnection;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Riwayat dompet, notifikasi, dan penjagaan penarikan DANA.
 *
 * Schema dibuat manual di SQLite in-memory mengikuti pola test feature lain
 * (migrasi lama memakai ALTER ... MODIFY khusus MySQL).
 */
class WalletHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->dropSchema();
        $this->createSchema();
    }

    private function dropSchema(): void
    {
        // SQLite does not support SET FOREIGN_KEY_CHECKS, use PRAGMA instead
        DB::statement('PRAGMA foreign_keys=0');

        foreach (
            [
                'wallet_transactions', 'withdrawals', 'wallets',
                'dana_connections', 'couriers', 'settings', 'users',
            ] as $table) {
            Schema::dropIfExists($table);
        }

        // Re-enable foreign key checks
        DB::statement('PRAGMA foreign_keys=1');
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
            $table->json('status_history')->nullable();
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

    private function actingCourier(string $name = 'Budi Santoso'): Courier
    {
        $user = User::create([
            'name'     => $name,
            'email'    => fake()->unique()->safeEmail(),
            'password' => Hash::make('secret'),
            'phone'    => '081234567890',
        ]);

        $courier = Courier::create([
            'user_id'     => $user->id,
            'phone'       => '081234567890',
            'is_verified' => true,
            'is_active'   => true,
        ]);

        Sanctum::actingAs($user);

        return $courier;
    }

    private function connectDana(Courier $courier): void
    {
        $this->postJson('/api/courier/dana/mock-connect')->assertOk();
    }

    /**
     * Wallet aktif dengan saldo awal. Memakai updateOrCreate karena alur
     * mock-connect sudah membuat wallet untuk kurir yang sama.
     */
    private function walletWithBalance(Courier $courier, float $available): Wallet
    {
        return Wallet::updateOrCreate(
            ['courier_id' => $courier->id],
            [
                'status'            => 'active',
                'available_balance' => $available,
                'pending_balance'   => 0,
                'currency'          => 'IDR',
            ]
        );
    }

    private function earning(Wallet $wallet, float $amount, string $description = 'Pengantaran #12'): WalletTransaction
    {
        return WalletTransaction::create([
            'wallet_id'   => $wallet->id,
            'courier_id'  => $wallet->courier_id,
            'order_id'    => 12,
            'type'        => 'earning',
            'amount'      => $amount,
            'fee'         => 0,
            'net_amount'  => $amount,
            'status'      => 'completed',
            'reference'   => 'ORD-12',
            'description' => $description,
        ]);
    }

    private function withdrawal(Courier $courier, Wallet $wallet, string $status, float $amount = 50000, string $provider = 'dana'): Withdrawal
    {
        return Withdrawal::create([
            'courier_id'      => $courier->id,
            'wallet_id'       => $wallet->id,
            'provider'        => $provider,
            'bank_name'       => strtoupper($provider),
            'account_number'  => '0812****7890',
            'account_name'    => 'BUDI SANTOSO',
            'amount'          => $amount,
            'fee'             => 2500,
            'net_amount'      => $amount - 2500,
            'status'          => $status,
            'idempotency_key' => 'WD-TEST-' . strtoupper($status) . '-' . $amount,
            'provider_reference' => 'DANA-TX-' . strtoupper($status),
        ]);
    }

    public function test_history_requires_authentication(): void
    {
        $this->getJson('/api/courier/wallet/history')->assertUnauthorized();
        $this->getJson('/api/courier/wallet/notifications')->assertUnauthorized();
    }

    public function test_history_combines_earnings_and_withdrawals_with_labels(): void
    {
        $courier = $this->actingCourier();
        $wallet  = $this->walletWithBalance($courier, 200000);

        $this->earning($wallet, 75000);
        $this->withdrawal($courier, $wallet, 'success', 50000);

        $response = $this->getJson('/api/courier/wallet/history')->assertOk();

        $items = $response->json('data.transactions');
        $this->assertCount(2, $items);
        $this->assertSame(2, $response->json('data.total'));

        $kinds = array_column($items, 'kind');
        $this->assertContains('earning', $kinds);
        $this->assertContains('withdrawal', $kinds);

        $withdrawal = collect($items)->firstWhere('kind', 'withdrawal');
        $this->assertSame(-50000.0, (float) $withdrawal['amount']);
        $this->assertSame('Berhasil', $withdrawal['status_label']);
        $this->assertSame('DANA', $withdrawal['provider']);
        $this->assertNotNull($withdrawal['withdrawal_id']);

        $earning = collect($items)->firstWhere('kind', 'earning');
        $this->assertSame(75000.0, (float) $earning['amount']);
        $this->assertSame('Berhasil', $earning['status_label']);
    }

    public function test_history_filters_by_kind_and_status(): void
    {
        $courier = $this->actingCourier();
        $wallet  = $this->walletWithBalance($courier, 200000);

        $this->earning($wallet, 75000);
        $this->withdrawal($courier, $wallet, 'success', 30000);
        $this->withdrawal($courier, $wallet, 'processing', 40000);

        $onlyWithdrawals = $this->getJson('/api/courier/wallet/history?filter=withdrawal')->assertOk();
        $this->assertCount(2, $onlyWithdrawals->json('data.transactions'));
        foreach ($onlyWithdrawals->json('data.transactions') as $item) {
            $this->assertSame('withdrawal', $item['kind']);
        }

        $onlyProcessing = $this->getJson('/api/courier/wallet/history?status=processing')->assertOk();
        $this->assertCount(1, $onlyProcessing->json('data.transactions'));
        $this->assertSame('Diproses', $onlyProcessing->json('data.transactions.0.status_label'));

        $onlyEarnings = $this->getJson('/api/courier/wallet/history?filter=earning')->assertOk();
        $this->assertCount(1, $onlyEarnings->json('data.transactions'));
        $this->assertSame('earning', $onlyEarnings->json('data.transactions.0.kind'));
    }

    public function test_history_keeps_legacy_ovo_withdrawal_records(): void
    {
        $courier = $this->actingCourier();
        $wallet  = $this->walletWithBalance($courier, 100000);

        $this->withdrawal($courier, $wallet, 'success', 60000, 'ovo');

        $response = $this->getJson('/api/courier/wallet/history?filter=withdrawal')->assertOk();

        $this->assertCount(1, $response->json('data.transactions'));
        $this->assertSame('OVO', $response->json('data.transactions.0.provider'));
        $this->assertSame('Penarikan ke OVO', $response->json('data.transactions.0.title'));
    }

    public function test_history_paginates(): void
    {
        $courier = $this->actingCourier();
        $wallet  = $this->walletWithBalance($courier, 200000);

        for ($i = 1; $i <= 7; $i++) {
            $this->earning($wallet, 10000, 'Pengantaran #' . $i);
        }

        $page1 = $this->getJson('/api/courier/wallet/history?per_page=5')->assertOk();
        $this->assertCount(5, $page1->json('data.transactions'));
        $this->assertTrue($page1->json('data.has_more'));

        $page2 = $this->getJson('/api/courier/wallet/history?per_page=5&page=2')->assertOk();
        $this->assertCount(2, $page2->json('data.transactions'));
        $this->assertFalse($page2->json('data.has_more'));
    }

    public function test_notifications_are_derived_from_connection_and_withdrawal_status(): void
    {
        $courier = $this->actingCourier();
        $wallet  = $this->walletWithBalance($courier, 200000);

        DanaConnection::create([
            'courier_id'   => $courier->id,
            'provider'     => 'dana',
            'status'       => 'connected',
            'masked_phone' => '0812****7890',
            'account_name' => 'BUDI SANTOSO',
            'bound_at'     => now(),
        ]);

        $this->withdrawal($courier, $wallet, 'success', 50000);

        $response = $this->getJson('/api/courier/wallet/notifications')->assertOk();
        $types    = array_column($response->json('data.notifications'), 'type');

        $this->assertContains('dana_connected', $types);
        $this->assertContains('withdrawal_success', $types);

        $titles = array_column($response->json('data.notifications'), 'title');
        $this->assertContains('Akun DANA terhubung', $titles);
        $this->assertContains('Dana berhasil dikirim', $titles);
    }

    public function test_withdrawal_with_phone_number_succeeds_without_dana_binding(): void
    {
        $courier = $this->actingCourier();
        $this->walletWithBalance($courier, 200000);

        $created = $this->postJson('/api/withdrawals', [
            'amount'          => 50000,
            'idempotency_key' => 'WD-NO-DANA-1',
            'phone_number'    => '081234567890',
        ])->assertStatus(201);

        $this->assertGreaterThan(0, Withdrawal::count());
    }

    public function test_withdrawal_rejects_amount_above_available_balance(): void
    {
        $courier = $this->actingCourier();
        $this->connectDana($courier);
        $this->walletWithBalance($courier, 20000);

        $this->postJson('/api/courier/withdrawals', [
            'amount'          => 50000,
            'idempotency_key' => 'WD-OVER-1',
        ])->assertStatus(400);

        $wallet = Wallet::where('courier_id', $courier->id)->first();
        $this->assertSame('20000.00', $wallet->available_balance);
        $this->assertSame('0.00', $wallet->pending_balance);
    }

    public function test_withdrawal_rejects_amount_below_minimum(): void
    {
        $courier = $this->actingCourier();
        $this->connectDana($courier);
        $this->walletWithBalance($courier, 200000);

        $this->postJson('/api/courier/withdrawals', [
            'amount'          => 5000,
            'idempotency_key' => 'WD-MIN-1',
        ])->assertStatus(400);

        $this->assertSame(0, Withdrawal::count());
    }

    public function test_withdrawal_holds_balance_then_finalises_on_status_sync(): void
    {
        $courier = $this->actingCourier();
        $this->connectDana($courier);
        $this->walletWithBalance($courier, 200000);

        $created = $this->postJson('/api/courier/withdrawals', [
            'amount'          => 50000,
            'idempotency_key' => 'WD-FLOW-1',
        ])->assertCreated()->assertJsonPath('success', true);

        $withdrawalId = $created->json('data.id');

        // Dana ditahan, tidak langsung dianggap terkirim.
        $wallet = Wallet::where('courier_id', $courier->id)->first();
        $this->assertSame('150000.00', $wallet->available_balance);
        $this->assertSame('50000.00', $wallet->pending_balance);

        $history = $this->getJson('/api/courier/wallet/history')->assertOk();
        $row = collect($history->json('data.transactions'))->firstWhere('kind', 'withdrawal');
        $this->assertNotNull($row);
        $this->assertSame('processing', $row['status']);

        // Sinkronisasi status: belum final tanpa konfirmasi provider.
        $sync = $this->getJson('/api/courier/withdrawals/' . $withdrawalId . '/status')->assertOk();
        $sync->assertJsonPath('data.status', 'processing');

        // Konfirmasi resmi provider (webhook/inquiry) memfinalkan status.
        (new \App\Services\WithdrawalService())->handleStatusUpdate('WD-FLOW-1', 'success');

        $wallet->refresh();
        $this->assertSame('150000.00', $wallet->available_balance);
        $this->assertSame('0.00', $wallet->pending_balance);

        $history = $this->getJson('/api/courier/wallet/history')->assertOk();
        $row = collect($history->json('data.transactions'))->firstWhere('kind', 'withdrawal');
        $this->assertSame('success', $row['status']);
        $this->assertSame('Berhasil', $row['status_label']);
    }

    public function test_failed_withdrawal_returns_held_balance_and_appears_in_history(): void
    {
        $courier = $this->actingCourier();
        $this->connectDana($courier);
        $this->walletWithBalance($courier, 200000);

        $created = $this->postJson('/api/courier/withdrawals', [
            'amount'          => 50000,
            'idempotency_key' => 'WD-FAIL-1',
        ])->assertCreated();

        // Provider belum kirim referensi (payout belum aktif) — gunakan idempotency key
        // untuk pencocokan konfirmasi resmi.
        $reference = $created->json('data.idempotency_key');
        $this->assertNotEmpty($reference);

        // Provider menyatakan transaksi gagal (konfirmasi resmi).
        (new \App\Services\WithdrawalService())->handleStatusUpdate($reference, 'failed', 'Nomor DANA tidak valid');

        $wallet = Wallet::where('courier_id', $courier->id)->first();
        $this->assertSame('200000.00', $wallet->available_balance);
        $this->assertSame('0.00', $wallet->pending_balance);

        $history = $this->getJson('/api/courier/wallet/history')->assertOk();
        $row = collect($history->json('data.transactions'))->firstWhere('kind', 'withdrawal');
        $this->assertSame('failed', $row['status']);
        $this->assertSame('Gagal', $row['status_label']);
        $this->assertSame('Nomor DANA tidak valid', $row['description']);

        $kinds = array_column($history->json('data.transactions'), 'kind');
        $this->assertContains('reversal', $kinds);

        $notifications = $this->getJson('/api/courier/wallet/notifications')->assertOk();
        $types = array_column($notifications->json('data.notifications'), 'type');
        $this->assertContains('withdrawal_failed', $types);
        $this->assertContains('balance_returned', $types);
    }

    public function test_withdrawal_is_idempotent_for_the_same_key(): void
    {
        $courier = $this->actingCourier();
        $this->connectDana($courier);
        $this->walletWithBalance($courier, 200000);

        $payload = ['amount' => 50000, 'idempotency_key' => 'WD-IDEMPOTENT-1'];

        $first = $this->postJson('/api/courier/withdrawals', $payload)->assertCreated();
        $second = $this->postJson('/api/courier/withdrawals', $payload)->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Withdrawal::count());

        $wallet = Wallet::where('courier_id', $courier->id)->first();
        $this->assertSame('150000.00', $wallet->available_balance);
    }

    public function test_second_withdrawal_is_blocked_while_one_is_processing(): void
    {
        $courier = $this->actingCourier();
        $this->connectDana($courier);
        $this->walletWithBalance($courier, 200000);

        $this->postJson('/api/courier/withdrawals', [
            'amount'          => 50000,
            'idempotency_key' => 'WD-BLOCK-1',
        ])->assertCreated();

        $this->postJson('/api/courier/withdrawals', [
            'amount'          => 20000,
            'idempotency_key' => 'WD-BLOCK-2',
        ])->assertStatus(400);

        $this->assertSame(1, Withdrawal::count());
    }

    public function test_notifications_and_history_are_scoped_to_the_authenticated_courier(): void
    {
        $courier = $this->actingCourier();
        $wallet  = $this->walletWithBalance($courier, 100000);
        $this->earning($wallet, 25000);

        $other = $this->actingCourier('Kurir Lain');
        $otherWallet = Wallet::create([
            'courier_id'        => $other->id,
            'status'            => 'active',
            'available_balance' => 999000,
            'pending_balance'   => 0,
            'currency'          => 'IDR',
        ]);
        $this->earning($otherWallet, 999000, 'Pendapatan kurir lain');

        // Kembali sebagai kurir pertama untuk memastikan data terisolasi.
        Sanctum::actingAs($courier->user);

        $response = $this->getJson('/api/courier/wallet/history')->assertOk();

        $items = $response->json('data.transactions');
        $this->assertCount(1, $items);
        $this->assertSame(25000.0, (float) $items[0]['amount']);

        Sanctum::actingAs($other->user);
        $otherItems = $this->getJson('/api/courier/wallet/history')->json('data.transactions');
        $this->assertCount(1, $otherItems);
        $this->assertSame(999000.0, (float) $otherItems[0]['amount']);
    }
}
