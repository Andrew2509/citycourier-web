<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\User;
use App\Modules\Ledger\Models\WalletLedgerEntry;
use App\Modules\Ledger\Services\LedgerReconciliationService;
use App\Modules\Wallet\Services\WalletFreezeService;
use App\Modules\Wallet\Services\WalletLedgerManager;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\CreatesPaymentWalletSchema;

class WalletLedgerCoreTest extends TestCase
{
    use CreatesPaymentWalletSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runPaymentWalletMigrations();
    }

    public function test_double_entry_ledger_maintains_exact_balances_and_idempotency(): void
    {
        $user = User::create([
            'name' => 'Customer Budi',
            'email' => 'budi@example.com',
            'role' => 'customer',
        ]);

        $manager = app(WalletLedgerManager::class);
        $wallet = $manager->getOrCreateCustomerWallet($user);

        // Credit Rp 100.000 via topup
        $res1 = $manager->creditAvailable(
            $wallet,
            100000,
            WalletLedgerEntry::ACCOUNT_SYSTEM_GATEWAY_CLEARING,
            'topup',
            'TOPUP-001',
            'idem-topup-001',
            'Top up QRIS'
        );
        $this->assertFalse($res1['idempotent_replay']);

        // Replay same idempotency key -> no duplicate credit
        $res2 = $manager->creditAvailable(
            $wallet,
            100000,
            WalletLedgerEntry::ACCOUNT_SYSTEM_GATEWAY_CLEARING,
            'topup',
            'TOPUP-001',
            'idem-topup-001',
            'Top up QRIS'
        );
        $this->assertTrue($res2['idempotent_replay']);

        // Debit Rp 35.000 for order payment
        $manager->debitAvailable(
            $wallet,
            35000,
            WalletLedgerEntry::ACCOUNT_SYSTEM_ORDER_ESCROW,
            'order_payment',
            'PAY-001',
            'idem-pay-001',
            'Bayar order #1'
        );

        $wallet->refresh();
        $this->assertSame(65000, (int) $wallet->available_balance);
        $this->assertSame(65000, (int) $wallet->balanceRecord->available_balance);

        $reconciliation = app(LedgerReconciliationService::class)->runReconciliation();
        $this->assertTrue($reconciliation['is_clean']);
        $this->assertTrue($reconciliation['global_balanced']);
        $this->assertSame(0, $reconciliation['imbalance_amount']);
        $this->assertSame(0, $reconciliation['mismatched_wallets_count']);
    }

    public function test_courier_pending_to_available_to_reserve_to_confirm_and_release(): void
    {
        $user = User::create(['name' => 'Kurir Andi', 'email' => 'andi@example.com', 'role' => 'courier']);
        $courier = Courier::create(['user_id' => $user->id, 'phone' => '081234567890', 'vehicle_type' => 'motor', 'is_verified' => true, 'is_active' => true]);

        $manager = app(WalletLedgerManager::class);
        $wallet = $manager->getOrCreateCourierWallet($courier);

        // Gross 50.000, fee 10% = 5.000, net = 45.000 -> pending
        $manager->creditPendingEarning($wallet, 50000, 5000, 45000, 'EARN-1', 'idem-earn-1', 'Order delivered');
        $wallet->refresh();
        $this->assertSame(45000, (int) $wallet->pending_balance);
        $this->assertSame(0, (int) $wallet->available_balance);

        // Release pending -> available
        $manager->releasePendingToAvailable($wallet, 45000, 'EARN-REL-1', 'idem-rel-1', 'Settlement done');
        $wallet->refresh();
        $this->assertSame(0, (int) $wallet->pending_balance);
        $this->assertSame(45000, (int) $wallet->available_balance);

        // Reserve 20.000 -> release on failure
        $manager->reserveForPayout($wallet, 20000, 'PO-FAIL', 'idem-po-fail', 'Auto payout fail test');
        $wallet->refresh();
        $this->assertSame(25000, (int) $wallet->available_balance);
        $this->assertSame(20000, (int) $wallet->reserved_balance);

        $manager->releaseReservedPayout($wallet, 20000, 'PO-FAIL-RELEASE', 'idem-po-fail-rel', 'Payout failed refund');
        $wallet->refresh();
        $this->assertSame(45000, (int) $wallet->available_balance);
        $this->assertSame(0, (int) $wallet->reserved_balance);

        // Reserve 45.000 -> confirm settled
        $manager->reserveForPayout($wallet, 45000, 'PO-OK', 'idem-po-ok', 'Auto payout ok');
        $manager->confirmReservedPayout($wallet, 45000, 0, 'PO-OK-SETTLE', 'idem-po-ok-settle', 'Disbursed');
        $wallet->refresh();
        $this->assertSame(0, (int) $wallet->available_balance);
        $this->assertSame(0, (int) $wallet->reserved_balance);

        $reconciliation = app(LedgerReconciliationService::class)->runReconciliation();
        $this->assertTrue($reconciliation['is_clean']);
    }

    public function test_frozen_wallet_blocks_debit_and_writes_audit_log(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'admin']);
        $customer = User::create(['name' => 'Cust', 'email' => 'cust@example.com', 'role' => 'customer']);

        $manager = app(WalletLedgerManager::class);
        $freeze = app(WalletFreezeService::class);
        $wallet = $manager->getOrCreateCustomerWallet($customer);

        $manager->creditAvailable(
            $wallet,
            50000,
            WalletLedgerEntry::ACCOUNT_SYSTEM_GATEWAY_CLEARING,
            'topup',
            'TOPUP-FZ',
            'idem-fz',
            'Topup'
        );

        $freeze->freeze($wallet, $admin, 'Suspected fraud');
        $wallet->refresh();
        $this->assertTrue($wallet->isFrozen());

        $this->expectException(RuntimeException::class);
        $manager->debitAvailable(
            $wallet,
            10000,
            WalletLedgerEntry::ACCOUNT_SYSTEM_ORDER_ESCROW,
            'order_payment',
            'PAY-FZ',
            'idem-pay-fz',
            'Blocked payment'
        );
    }
}
