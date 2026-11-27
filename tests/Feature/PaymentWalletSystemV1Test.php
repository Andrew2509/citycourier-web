<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\User;
use App\Modules\Earnings\Jobs\CourierEarningJob;
use App\Modules\Earnings\Models\CourierEarning;
use App\Modules\Earnings\Services\CourierEarningService;
use App\Modules\Ledger\Services\LedgerReconciliationService;
use App\Modules\Payment\Models\PaymentWebhook;
use App\Modules\Payment\Providers\DokuPaymentProvider;
use App\Modules\Payment\Services\WebhookProcessorService;
use App\Modules\Payout\Models\PayoutTransaction;
use App\Modules\Payout\Services\PayoutService;
use App\Modules\Refund\Models\RefundTransaction;
use App\Modules\Refund\Services\RefundService;
use App\Modules\TopUp\Models\TopupTransaction;
use App\Modules\Wallet\Services\WalletFreezeService;
use App\Modules\Wallet\Services\WalletLedgerManager;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesPaymentWalletSchema;

class PaymentWalletSystemV1Test extends TestCase
{
    use CreatesPaymentWalletSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runPaymentWalletMigrations();
        Config::set('payment_wallet.mode', 'sandbox');
        Config::set('payment_wallet.xendit.callback_token', 'test-xendit-callback-secret');
        Config::set('payment_wallet.doku.client_id', 'BRN-TEST-001');
        Config::set('payment_wallet.doku.secret_key', 'SK-DOKU-SECRET-123');
    }

    public function test_customer_topup_idempotency_simulation_and_production_block(): void
    {
        $customer = User::create([
            'name' => 'Siti Customer',
            'email' => 'siti@example.com',
            'role' => 'customer',
        ]);
        Sanctum::actingAs($customer);

        // Invalid amount rejected
        $this->postJson('/api/v1/customer/wallet/topups', [
            'amount' => 1000,
            'payment_type' => 'qris',
            'channel_code' => 'QRIS',
        ])->assertStatus(422);

        // Create topup with explicit Idempotency-Key
        $res1 = $this->withHeaders(['Idempotency-Key' => 'idem-topup-100k'])
            ->postJson('/api/v1/customer/wallet/topups', [
                'amount' => 100000,
                'payment_type' => 'qris',
                'channel_code' => 'QRIS',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', 100000)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.is_simulated', true);

        $topupId = $res1->json('data.id');

        // Replay returns exact same transaction
        $this->withHeaders(['Idempotency-Key' => 'idem-topup-100k'])
            ->postJson('/api/v1/customer/wallet/topups', [
                'amount' => 100000,
                'payment_type' => 'qris',
                'channel_code' => 'QRIS',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $topupId)
            ->assertJsonPath('data.idempotent_replay', true);

        $this->assertSame(1, TopupTransaction::count());

        // Production mode MUST block simulate-paid
        Setting::set('payment_wallet_mode', 'production');
        $this->postJson("/api/v1/customer/wallet/topups/{$topupId}/simulate-paid")
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'FORBIDDEN_IN_PRODUCTION');

        // Restore sandbox & simulate paid -> credits balance cleanly
        Setting::set('payment_wallet_mode', 'sandbox');
        $this->postJson("/api/v1/customer/wallet/topups/{$topupId}/simulate-paid")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'settled');

        $this->getJson('/api/v1/customer/wallet')
            ->assertStatus(200)
            ->assertJsonPath('data.available_balance', 100000);

        $this->assertTrue(app(LedgerReconciliationService::class)->runReconciliation()['is_clean']);
    }

    public function test_customer_order_wallet_payment_freeze_isolation_and_refund(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@city.test', 'role' => 'admin']);
        $customer = User::create(['name' => 'Rina', 'email' => 'rina@city.test', 'role' => 'customer']);
        $otherUser = User::create(['name' => 'Other', 'email' => 'other@city.test', 'role' => 'customer']);

        $walletManager = app(WalletLedgerManager::class);
        $wallet = $walletManager->getOrCreateCustomerWallet($customer);

        // Credit 60.000
        $walletManager->creditAvailable(
            $wallet,
            60000,
            \App\Modules\Ledger\Models\WalletLedgerEntry::ACCOUNT_SYSTEM_GATEWAY_CLEARING,
            'topup',
            'TOPUP-RINA',
            'idem-rina-topup',
            'Topup test'
        );

        $shipment = Shipment::create([
            'shipment_number' => 'SHP-WALLET-001',
            'user_id' => $customer->id,
            'shipping_cost' => 45000,
            'total_cost' => 45000,
            'status' => 'pending',
        ]);
        Order::create([
            'order_number' => 'SHP-WALLET-001',
            'customer_name' => $customer->name,
            'customer_phone' => '081200000001',
            'price' => 45000,
            'status' => 'pending',
        ]);

        // Cross-user payment blocked
        Sanctum::actingAs($otherUser);
        $this->postJson('/api/v1/customer/wallet/payments', [
            'shipment_id' => $shipment->id,
        ])->assertStatus(403);

        // Owner pays via wallet (idempotent)
        Sanctum::actingAs($customer);
        $this->withHeaders(['Idempotency-Key' => 'pay-shp-001'])
            ->postJson('/api/v1/customer/wallet/payments', [
                'shipment_id' => $shipment->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.payment_transaction.amount', 45000)
            ->assertJsonPath('data.wallet_available_balance', 15000);

        $this->withHeaders(['Idempotency-Key' => 'pay-shp-001'])
            ->postJson('/api/v1/customer/wallet/payments', [
                'shipment_id' => $shipment->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.idempotent_replay', true);

        $this->assertSame('wallet', $shipment->fresh()->payment_method);
        $this->assertSame('confirmed', $shipment->fresh()->status);

        // Insufficient balance rejected
        $expensive = Shipment::create([
            'shipment_number' => 'SHP-WALLET-002',
            'user_id' => $customer->id,
            'shipping_cost' => 50000,
            'total_cost' => 50000,
            'status' => 'pending',
        ]);
        $this->withHeaders(['Idempotency-Key' => 'pay-shp-002'])
            ->postJson('/api/v1/customer/wallet/payments', [
                'shipment_id' => $expensive->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'INSUFFICIENT_BALANCE');

        // Frozen wallet blocks transactions
        app(WalletFreezeService::class)->freeze($wallet, $admin, 'Audit freeze');
        $this->withHeaders(['Idempotency-Key' => 'topup-fz-001'])
            ->postJson('/api/v1/customer/wallet/topups', [
                'amount' => 20000,
                'payment_type' => 'qris',
                'channel_code' => 'QRIS',
            ])->assertStatus(422);

        // Unfreeze -> Request Refund -> Admin approves -> Balance credited back to 60.000
        app(WalletFreezeService::class)->unfreeze($wallet, $admin, 'Cleared');
        $refRes = $this->withHeaders(['Idempotency-Key' => 'refund-shp-001'])
            ->postJson('/api/v1/customer/wallet/refunds', [
                'shipment_id' => $shipment->id,
                'reason' => 'Paket belum dijemput, pelanggan batal kirim',
            ])->assertStatus(201)->assertJsonPath('data.status', 'requested');

        $refund = RefundTransaction::findOrFail($refRes->json('data.id'));
        app(RefundService::class)->approveAndProcess($refund, $admin, 'Verified unpicked');

        $this->getJson('/api/v1/customer/wallet')
            ->assertStatus(200)
            ->assertJsonPath('data.available_balance', 60000);

        $this->assertTrue(app(LedgerReconciliationService::class)->runReconciliation()['is_clean']);
    }

    public function test_customer_withdrawal_blocked_in_production_and_works_in_sandbox(): void
    {
        $customer = User::create(['name' => 'Dina', 'email' => 'dina@city.test', 'role' => 'customer']);
        $walletManager = app(WalletLedgerManager::class);
        $wallet = $walletManager->getOrCreateCustomerWallet($customer);
        $walletManager->creditAvailable(
            $wallet,
            80000,
            \App\Modules\Ledger\Models\WalletLedgerEntry::ACCOUNT_SYSTEM_GATEWAY_CLEARING,
            'topup',
            'TOPUP-DINA',
            'idem-dina-topup',
            'Topup'
        );

        Sanctum::actingAs($customer);
        $destRes = $this->postJson('/api/v1/customer/wallet/destinations', [
            'destination_type' => 'bank',
            'channel_code' => 'ID_BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'Dina Lestari',
        ])->assertStatus(201);
        $destId = $destRes->json('data.id');

        // Production must block withdrawal with 403
        Setting::set('payment_wallet_mode', 'production');
        Config::set('payment_wallet.customer_withdrawal_regulatory_approved', false);

        $this->postJson('/api/v1/customer/wallet/withdrawals', [
            'amount' => 30000,
            'destination_id' => $destId,
        ])->assertStatus(403)->assertJsonPath('error_code', 'CUSTOMER_WITHDRAWAL_DISABLED_IN_PRODUCTION');

        // Sandbox succeeds cleanly
        Setting::set('payment_wallet_mode', 'sandbox');
        $this->postJson('/api/v1/customer/wallet/withdrawals', [
            'amount' => 30000,
            'destination_id' => $destId,
        ])->assertStatus(201);

        $this->getJson('/api/v1/customer/wallet')
            ->assertStatus(200)
            ->assertJsonPath('data.available_balance', 50000)
            ->assertJsonPath('data.reserved_balance', 0);

        $this->assertTrue(app(LedgerReconciliationService::class)->runReconciliation()['is_clean']);
    }

    public function test_courier_earning_settlement_and_auto_payout_prevent_duplicates_and_recover_failure(): void
    {
        Setting::set('courier_commission_rate_percent', '15');
        Setting::set('courier_settlement_waiting_minutes', '60');
        Setting::set('courier_min_payout_idr', '50000');

        $courierUser = User::create(['name' => 'Kurir Bagus', 'email' => 'bagus@city.test', 'role' => 'courier']);
        $courier = Courier::create(['user_id' => $courierUser->id, 'phone' => '081299998888', 'vehicle_type' => 'motor', 'is_verified' => true, 'is_active' => true]);

        $order = Order::create([
            'courier_id' => $courier->id,
            'order_number' => 'ORD-EARN-100',
            'price' => 100000,
            'status' => 'delivered',
            'delivered_at' => now()->subMinutes(10),
        ]);

        // Job computes: gross 100k, 15% fee = 15k, net = 85k pending
        CourierEarningJob::dispatchSync($order->id);
        // Dispatching again MUST NOT duplicate
        CourierEarningJob::dispatchSync($order->id);

        $this->assertSame(1, CourierEarning::count());
        $earning = CourierEarning::firstOrFail();
        $this->assertSame(100000, $earning->gross_amount);
        $this->assertSame(15000, $earning->commission_amount);
        $this->assertSame(85000, $earning->net_amount);
        $this->assertSame('pending', $earning->status);

        Sanctum::actingAs($courierUser);
        $this->getJson('/api/v1/courier/wallet')
            ->assertStatus(200)
            ->assertJsonPath('data.pending_balance', 85000)
            ->assertJsonPath('data.available_balance', 0);

        // Register failing destination ending with 999 to verify failure recovery first
        $this->postJson('/api/v1/courier/wallet/destinations', [
            'destination_type' => 'ewallet',
            'channel_code' => 'ID_DANA',
            'account_number' => '081234567999',
            'account_holder_name' => 'Bagus Kurir',
            'is_default' => true,
        ])->assertStatus(201);

        // Mature the earning
        $earning->available_at = now()->subMinute();
        $earning->save();

        $payoutService = app(PayoutService::class);
        $run1 = $payoutService->runScheduledCourierAutoPayouts();
        $this->assertSame(1, $run1['released_earnings']);
        $this->assertSame(1, $run1['scheduled_payouts']);

        // Because channel ended in 999 -> mock failed & released back to available_balance (85k) and earning status 'available'
        $failedPayout = PayoutTransaction::firstOrFail();
        $this->assertSame('failed', $failedPayout->status);
        $this->assertSame('available', $earning->fresh()->status);

        $this->getJson('/api/v1/courier/wallet')
            ->assertStatus(200)
            ->assertJsonPath('data.available_balance', 85000)
            ->assertJsonPath('data.reserved_balance', 0);

        // Switch to valid default destination
        $goodDest = $this->postJson('/api/v1/courier/wallet/destinations', [
            'destination_type' => 'bank',
            'channel_code' => 'ID_BCA',
            'account_number' => '8880001234',
            'account_holder_name' => 'Bagus Kurir',
            'is_default' => true,
        ])->assertStatus(201)->json('data.id');

        $this->patchJson("/api/v1/courier/wallet/destinations/{$goodDest}/default")
            ->assertStatus(200)
            ->assertJsonPath('data.is_default', true);

        // Run scheduler again -> completes payout -> available_balance 0, earning status 'paid'
        $run2 = $payoutService->runScheduledCourierAutoPayouts();
        $this->assertSame(1, $run2['scheduled_payouts']);

        $this->getJson('/api/v1/courier/wallet/payouts')
            ->assertStatus(200)
            ->assertJsonPath('data.auto_payout_status.available_balance', 0)
            ->assertJsonCount(2, 'data.items');

        $this->getJson('/api/v1/courier/wallet/notifications')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.items');

        $this->assertSame('paid', $earning->fresh()->status);

        // Running scheduler again produces 0 payouts (no duplicate payout)
        $run3 = $payoutService->runScheduledCourierAutoPayouts();
        $this->assertSame(0, $run3['scheduled_payouts']);

        $this->assertTrue(app(LedgerReconciliationService::class)->runReconciliation()['is_clean']);
    }

    public function test_webhooks_verify_signatures_and_enforce_idempotency(): void
    {
        $customer = User::create(['name' => 'Webhook User', 'email' => 'wh@city.test', 'role' => 'customer']);
        Sanctum::actingAs($customer);

        $topupRes = $this->postJson('/api/v1/customer/wallet/topups', [
            'amount' => 75000,
            'payment_type' => 'va',
            'channel_code' => 'BCA_VIRTUAL_ACCOUNT',
        ])->assertStatus(201);

        $ref = $topupRes->json('data.reference');
        $providerRef = $topupRes->json('data.provider_reference');

        $payload = [
            'event' => 'payment.succeeded',
            'data' => [
                'reference_id' => $ref,
                'payment_request_id' => $providerRef,
                'status' => 'SUCCEEDED',
                'request_amount' => 75000,
            ],
        ];

        // Invalid token -> 401
        $this->withHeaders(['x-callback-token' => 'wrong-token'])
            ->postJson('/api/v1/webhooks/xendit/payment', $payload)
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'INVALID_WEBHOOK_SIGNATURE');

        // Valid token -> credits wallet once
        $this->withHeaders(['x-callback-token' => 'test-xendit-callback-secret'])
            ->postJson('/api/v1/webhooks/xendit/payment', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.duplicate', false);

        // Exact duplicate webhook -> ignored cleanly without double credit!
        $this->withHeaders(['x-callback-token' => 'test-xendit-callback-secret'])
            ->postJson('/api/v1/webhooks/xendit/payment', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.duplicate', true);

        $wallet = app(WalletLedgerManager::class)->getOrCreateCustomerWallet($customer);
        $this->assertSame(75000, (int) $wallet->available_balance);
        $this->assertTrue(PaymentWebhook::where('provider', 'xendit')->count() >= 2);
        $this->assertTrue(app(LedgerReconciliationService::class)->runReconciliation()['is_clean']);
    }

    public function test_historical_and_newly_delivered_orders_sync_into_courier_wallet_without_queue_worker(): void
    {
        Setting::set('courier_commission_rate_percent', '10');
        Setting::set('courier_settlement_waiting_minutes', '1440');

        $courierUser = User::create([
            'name' => 'Kurir Sync',
            'email' => 'sync.kurir@city.test',
            'role' => 'courier',
        ]);
        $courier = Courier::create([
            'user_id' => $courierUser->id,
            'phone' => '081200001234',
            'vehicle_type' => 'motor',
            'is_verified' => true,
            'is_active' => true,
        ]);

        // Historical delivered order (gross 47.400 -> 90% net = 42.660) never processed by queue/cron
        Order::create([
            'courier_id' => $courier->id,
            'order_number' => 'SHP-HIST-42660',
            'price' => 47400,
            'status' => 'delivered',
            'delivered_at' => now()->subDays(2),
        ]);

        // Recent delivering order that courier marks delivered synchronously
        $activeOrder = Order::create([
            'courier_id' => $courier->id,
            'order_number' => 'SHP-LIVE-20000',
            'price' => 20000,
            'status' => 'delivering',
        ]);

        Sanctum::actingAs($courierUser);

        // Opening Dompet / V1 wallet immediately backfills and releases mature historical earnings
        $this->getJson('/api/v1/courier/wallet')
            ->assertOk()
            ->assertJsonPath('data.available_balance', 42660)
            ->assertJsonPath('data.pending_balance', 0)
            ->assertJsonPath('data.earnings_summary.available_earnings', 42660);

        // Repeated loads + legacy /api/courier/wallet + history remain strictly idempotent
        $this->getJson('/api/courier/wallet')
            ->assertOk()
            ->assertJsonPath('data.available_balance', 42660)
            ->assertJsonPath('data.total_balance', 42660);

        $this->getJson('/api/courier/wallet/history')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('wallet_ledger_entries', 'journal_reference'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('wallet_ledger_entries', 'reference'));

        // Marking new order delivered synchronously records pending earning immediately (no queue worker needed)
        $this->postJson('/api/update-status-order', [
            'order_id' => $activeOrder->id,
            'status' => 'delivered',
        ])->assertOk();

        $this->getJson('/api/v1/courier/wallet')
            ->assertOk()
            ->assertJsonPath('data.available_balance', 42660)
            ->assertJsonPath('data.pending_balance', 18000);

        $this->assertSame(2, CourierEarning::where('courier_id', $courier->id)->count());
        $this->assertTrue(app(LedgerReconciliationService::class)->runReconciliation()['is_clean']);
    }

    public function test_ewallet_withdrawal_and_xendit_webhook_execute_realtime_without_queue_worker(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('jobs')) {
            \Illuminate\Support\Facades\Schema::create('jobs', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        // Simulasikan .env production/shared hosting dengan QUEUE_CONNECTION=database tanpa queue:work berjalan
        Config::set('queue.default', 'database');
        Config::set('payment_wallet.mode', 'sandbox');
        Config::set('payment_wallet.xendit.secret_key', 'xnd_development_realtime_payout_key');
        Config::set('payment_wallet.xendit.callback_token', 'test-xendit-callback-secret');
        Setting::set('payment_wallet_mode', 'sandbox', 'finance');
        Setting::set('payout_active_provider', 'xendit', 'finance');

        $customer = User::create([
            'name' => 'Raka RealTime',
            'email' => 'raka.rt@city.test',
            'role' => 'customer',
        ]);

        $walletManager = app(WalletLedgerManager::class);
        $wallet = $walletManager->getOrCreateCustomerWallet($customer);
        $walletManager->creditAvailable(
            $wallet,
            100000,
            \App\Modules\Ledger\Models\WalletLedgerEntry::ACCOUNT_SYSTEM_GATEWAY_CLEARING,
            'topup',
            'TOPUP-RT-001',
            'idem-rt-topup',
            'Initial topup'
        );

        \Illuminate\Support\Facades\Http::fake([
            'https://api.xendit.co/v2/payouts' => \Illuminate\Support\Facades\Http::response([
                'id' => 'disb-rt-001',
                'reference_id' => 'WD-RT-001',
                'status' => 'ACCEPTED',
            ], 200),
        ]);

        Sanctum::actingAs($customer);

        $destId = $this->postJson('/api/v1/customer/wallet/destinations', [
            'destination_type' => 'ewallet',
            'channel_code' => 'ID_DANA',
            'account_number' => '081234567890',
            'account_holder_name' => 'Raka RealTime',
        ])->assertStatus(201)->json('data.id');

        // Saat user submit penarikan e-wallet, backend langsung kirim ke Xendit (status langsung `processing`, bukan nyangkut `pending`)
        $wdRes = $this->withHeaders(['Idempotency-Key' => 'idem-wd-rt-001'])
            ->postJson('/api/v1/customer/wallet/withdrawals', [
                'amount' => 40000,
                'destination_id' => $destId,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.provider', 'xendit')
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.provider_reference', 'disb-rt-001');

        $ref = $wdRes->json('data.reference');
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('jobs')->where('payload', 'like', '%PayoutProcessingJob%')->count());

        // Webhook payout Xendit masuk -> langsung diproses real-time ke `completed` tanpa menunggu queue worker
        $this->withHeaders(['x-callback-token' => 'test-xendit-callback-secret'])
            ->postJson('/api/v1/webhooks/xendit/payout', [
                'event' => 'payout.succeeded',
                'data' => [
                    'id' => 'disb-rt-001',
                    'reference_id' => $ref,
                    'status' => 'SUCCEEDED',
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'processed');

        $this->assertSame('completed', PayoutTransaction::firstOrFail()->status);
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('jobs')->where('payload', 'like', '%PaymentWebhookJob%')->count());

        $this->getJson('/api/v1/customer/wallet')
            ->assertOk()
            ->assertJsonPath('data.available_balance', 60000)
            ->assertJsonPath('data.reserved_balance', 0);

        $this->assertTrue(app(LedgerReconciliationService::class)->runReconciliation()['is_clean']);
    }

    public function test_courier_v1_withdrawals_appear_in_legacy_history_penarikan_filter_and_notifications(): void
    {
        $courierUser = User::create(['name' => 'Kurir V1 History', 'email' => 'v1hist@city.test', 'role' => 'courier']);
        $courier = Courier::create([
            'user_id' => $courierUser->id,
            'phone' => '081233334444',
            'vehicle_type' => 'motor',
            'is_verified' => true,
            'is_active' => true,
        ]);

        $walletManager = app(WalletLedgerManager::class);
        $wallet = $walletManager->getOrCreateCourierWallet($courier);
        $walletManager->creditPendingEarning(
            $wallet,
            100000,
            10000,
            90000,
            'EARN-HIST-1',
            'idem-earn-hist-1',
            'Pendapatan order #101'
        );
        $walletManager->releasePendingToAvailable(
            $wallet,
            90000,
            'EARN-HIST-REL-1',
            'idem-earn-hist-rel-1',
            'Pendapatan siap cair #101'
        );

        Sanctum::actingAs($courierUser);

        $destId = $this->postJson('/api/v1/courier/wallet/destinations', [
            'destination_type' => 'bank',
            'channel_code' => 'ID_BCA',
            'account_number' => '8880001234',
            'account_holder_name' => 'Kurir V1 History',
            'is_default' => true,
        ])->assertStatus(201)->json('data.id');

        $payoutRes = $this->withHeaders(['Idempotency-Key' => 'idem-cur-wd-hist-1'])
            ->postJson('/api/v1/courier/wallet/withdrawals', [
                'amount' => 50000,
                'destination_id' => $destId,
            ])
            ->assertStatus(201);

        $payoutId = (int) $payoutRes->json('data.id');

        // Filter "Semua" menampilkan earning dan penarikan v1
        $allHistory = $this->getJson('/api/courier/wallet/history')
            ->assertOk();
        $this->assertSame(2, $allHistory->json('data.total'));

        // Filter "Penarikan" (filter=withdrawal) wajib menampilkan penarikan v1
        $wdHistory = $this->getJson('/api/courier/wallet/history?filter=withdrawal')
            ->assertOk();
        $this->assertSame(1, $wdHistory->json('data.total'));
        $wdRow = $wdHistory->json('data.transactions.0');
        $this->assertSame('withdrawal', $wdRow['kind']);
        $this->assertSame($payoutId, $wdRow['payout_id']);
        $this->assertNull($wdRow['withdrawal_id']);
        $this->assertSame('Bank BCA', $wdRow['provider']);
        $this->assertSame(-50000.0, (float) $wdRow['amount']);

        // Filter "Pendapatan" tidak ikut menampilkan penarikan
        $earnHistory = $this->getJson('/api/courier/wallet/history?filter=earning')
            ->assertOk();
        $this->assertSame(1, $earnHistory->json('data.total'));
        $this->assertSame('earning', $earnHistory->json('data.transactions.0.kind'));

        // Notifikasi dan ringkasan saldo dompet juga mencakup penarikan v1
        $notifRes = $this->getJson('/api/courier/wallet/notifications')->assertOk();
        $this->assertNotEmpty($notifRes->json('data.notifications'));

        $walletSummary = $this->getJson('/api/courier/wallet')->assertOk();
        $this->assertSame(40000.0, (float) $walletSummary->json('data.available_balance'));
        $this->assertSame(50000.0, (float) $walletSummary->json('data.total_withdrawn'));
    }

    public function test_xendit_processing_payout_auto_syncs_on_detail_and_history_without_webhook_or_queue(): void
    {
        Config::set('queue.default', 'database');
        Config::set('payment_wallet.mode', 'sandbox');
        Config::set('payment_wallet.xendit.secret_key', 'xnd_development_sync_test_key');
        Setting::set('payment_wallet_mode', 'sandbox', 'finance');
        Setting::set('payout_active_provider', 'xendit', 'finance');

        $courierUser = User::create([
            'name' => 'Kurir Sync Xendit',
            'email' => 'kurir.sync@city.test',
            'role' => 'courier',
        ]);
        $courier = Courier::create([
            'user_id' => $courierUser->id,
            'phone' => '081355500011',
            'vehicle_type' => 'motor',
            'is_verified' => true,
            'is_active' => true,
        ]);

        $walletManager = app(WalletLedgerManager::class);
        $wallet = $walletManager->getOrCreateCourierWallet($courier);
        $walletManager->creditPendingEarning(
            $wallet,
            53822,
            5382,
            48440,
            'EARN-SYNC-1',
            'idem-earn-sync-1',
            'Pendapatan order kurir'
        );
        $walletManager->releasePendingToAvailable(
            $wallet,
            48440,
            'EARN-SYNC-REL-1',
            'idem-earn-sync-rel-1',
            'Pendapatan order kurir siap cair'
        );

        \Illuminate\Support\Facades\Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            $method = $request->method();

            if ($method === 'POST' && $url === 'https://api.xendit.co/v2/payouts') {
                return \Illuminate\Support\Facades\Http::response([
                    'id' => 'disb-sync-001',
                    'reference_id' => $request->data()['reference_id'] ?? 'WD-CUR',
                    'status' => 'ACCEPTED',
                ], 200);
            }

            if ($method === 'GET' && $url === 'https://api.xendit.co/v2/payouts/disb-sync-001') {
                return \Illuminate\Support\Facades\Http::response([
                    'id' => 'disb-sync-001',
                    'status' => 'SUCCEEDED',
                ], 200);
            }

            if ($method === 'GET' && $url === 'https://api.xendit.co/v2/payouts/disb-missing-direct') {
                return \Illuminate\Support\Facades\Http::response(['error_code' => 'NOT_FOUND'], 404);
            }

            if ($method === 'GET' && str_starts_with($url, 'https://api.xendit.co/v2/payouts') && ! empty($request->data()['reference_id'])) {
                return \Illuminate\Support\Facades\Http::response([
                    'data' => [
                        [
                            'id' => 'disb-sync-002',
                            'reference_id' => $request->data()['reference_id'] ?? '',
                            'status' => 'SUCCEEDED',
                        ],
                    ],
                ], 200);
            }

            return \Illuminate\Support\Facades\Http::response([], 404);
        });

        Sanctum::actingAs($courierUser);

        $destId = $this->postJson('/api/v1/courier/wallet/destinations', [
            'destination_type' => 'ewallet',
            'channel_code' => 'ID_DANA',
            'account_number' => '081234567890',
            'account_holder_name' => 'Kurir Sync Xendit',
            'is_default' => true,
        ])->assertStatus(201)->json('data.id');

        // 1) Penarikan pertama: ACCEPTED -> awalnya `processing`, lalu detail endpoint menyinkronkan ke `completed`
        $wd1 = $this->withHeaders(['Idempotency-Key' => 'idem-cur-sync-1'])
            ->postJson('/api/v1/courier/wallet/withdrawals', [
                'amount' => 20000,
                'destination_id' => $destId,
            ]);
        $this->assertSame(201, $wd1->status(), $wd1->content());
        $wd1->assertJsonPath('data.provider', 'xendit')
            ->assertJsonPath('data.status', 'processing');

        $payout1Id = (int) $wd1->json('data.id');
        $this->assertSame('processing', PayoutTransaction::findOrFail($payout1Id)->status);

        $this->getJson("/api/v1/courier/wallet/payouts/{$payout1Id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.provider_reference', 'disb-sync-001');

        $this->assertSame('completed', PayoutTransaction::findOrFail($payout1Id)->status);

        // 2) Penarikan kedua: provider_reference lookup 404 -> fallback `?reference_id=` saat GET history!
        $wd2 = $this->withHeaders(['Idempotency-Key' => 'idem-cur-sync-2'])
            ->postJson('/api/v1/courier/wallet/withdrawals', [
                'amount' => 20000,
                'destination_id' => $destId,
            ])
            ->assertStatus(201);

        $payout2Id = (int) $wd2->json('data.id');
        PayoutTransaction::where('id', $payout2Id)->update(['provider_reference' => 'disb-missing-direct']);

        $historyRes = $this->getJson('/api/courier/wallet/history?filter=withdrawal')
            ->assertOk();

        $this->assertSame('completed', PayoutTransaction::findOrFail($payout2Id)->status);
        $this->assertSame('disb-sync-002', PayoutTransaction::findOrFail($payout2Id)->provider_reference);
        $this->assertSame('success', $historyRes->json('data.transactions.0.status'));
        $this->assertSame('Berhasil', $historyRes->json('data.transactions.0.status_label'));

        $wallet->refresh();
        $this->assertSame(0, (int) $wallet->balanceRecord->reserved_balance);
        $this->assertSame(8440, (int) $wallet->balanceRecord->available_balance);
    }

    public function test_xendit_payout_webhook_supports_external_id_and_completed_status(): void
    {
        Config::set('payment_wallet.xendit.secret_key', 'xnd_development_wh_key');
        Config::set('payment_wallet.xendit.callback_token', 'test-xendit-callback-secret');
        Setting::set('payment_wallet_mode', 'sandbox', 'finance');
        Setting::set('payout_active_provider', 'xendit', 'finance');

        $customer = User::create([
            'name' => 'Pelanggan Webhook',
            'email' => 'customer.wh@city.test',
            'role' => 'customer',
        ]);

        $walletManager = app(WalletLedgerManager::class);
        $wallet = $walletManager->getOrCreateCustomerWallet($customer);
        $walletManager->creditAvailable(
            $wallet,
            80000,
            \App\Modules\Ledger\Models\WalletLedgerEntry::ACCOUNT_SYSTEM_GATEWAY_CLEARING,
            'topup',
            'TOPUP-WH-1',
            'idem-topup-wh-1',
            'Topup webhook test'
        );

        \Illuminate\Support\Facades\Http::fake([
            'https://api.xendit.co/v2/payouts' => \Illuminate\Support\Facades\Http::response([
                'id' => 'disb-wh-ext-1',
                'status' => 'ACCEPTED',
            ], 200),
        ]);

        Sanctum::actingAs($customer);
        $destId = $this->postJson('/api/v1/customer/wallet/destinations', [
            'destination_type' => 'ewallet',
            'channel_code' => 'ID_DANA',
            'account_number' => '081299998888',
            'account_holder_name' => 'Pelanggan Webhook',
        ])->assertStatus(201)->json('data.id');

        $wdRes = $this->withHeaders(['Idempotency-Key' => 'idem-wd-wh-ext-1'])
            ->postJson('/api/v1/customer/wallet/withdrawals', [
                'amount' => 30000,
                'destination_id' => $destId,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'processing');

        $ref = $wdRes->json('data.reference');
        $payoutId = (int) $wdRes->json('data.id');

        $this->withHeaders(['x-callback-token' => 'test-xendit-callback-secret'])
            ->postJson('/api/v1/webhooks/xendit/payout', [
                'id' => 'disb-wh-ext-1',
                'external_id' => $ref,
                'status' => 'COMPLETED',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'processed');

        $this->assertSame('completed', PayoutTransaction::findOrFail($payoutId)->status);
    }
}
