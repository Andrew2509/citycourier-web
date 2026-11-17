<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Shipment;
use App\Models\User;
use App\Modules\Ledger\Models\WalletLedgerEntry;
use App\Modules\Ledger\Services\LedgerReconciliationService;
use App\Modules\Payment\Services\OrderWalletPaymentService;
use App\Modules\Refund\Services\RefundService;
use App\Modules\Wallet\Services\WalletLedgerManager;
use Tests\TestCase;
use Tests\Traits\CreatesPaymentWalletSchema;

class AdminFinanceTest extends TestCase
{
    use CreatesPaymentWalletSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runPaymentWalletMigrations();
        $this->setUpSpatiePermissionsForAdminTests();
    }

    public function test_non_admin_user_cannot_access_admin_finance_pages(): void
    {
        $customer = User::create([
            'name' => 'Regular Customer',
            'email' => 'cust@city.test',
            'role' => 'customer',
        ]);

        $this->actingAs($customer)
            ->get('/admin/finance')
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($customer)
            ->getJson('/admin/finance')
            ->assertStatus(403);
    }

    public function test_admin_can_view_finance_pages_freeze_wallet_update_settings_and_approve_refund(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@city.test',
            'role' => 'admin',
        ]);
        $admin->assignRole('super-admin');

        $customer = User::create([
            'name' => 'Fajar Customer',
            'email' => 'fajar@city.test',
            'role' => 'customer',
        ]);

        $walletManager = app(WalletLedgerManager::class);
        $wallet = $walletManager->getOrCreateCustomerWallet($customer);
        $walletManager->creditAvailable(
            $wallet,
            100000,
            WalletLedgerEntry::ACCOUNT_SYSTEM_GATEWAY_CLEARING,
            'topup',
            'TOPUP-ADM-1',
            'idem-adm-1',
            'Initial topup'
        );

        $shipment = Shipment::create([
            'shipment_number' => 'SHP-ADM-01',
            'user_id' => $customer->id,
            'shipping_cost' => 40000,
            'total_cost' => 40000,
            'status' => 'pending',
        ]);

        app(OrderWalletPaymentService::class)->payWithWallet($customer, $shipment->id, null, 'idem-adm-pay');
        $refResult = app(RefundService::class)->requestRefund($customer, [
            'shipment_id' => $shipment->id,
            'reason' => 'Pelanggan meminta pembatalan pickup',
        ], 'idem-adm-ref');
        $refund = $refResult['refund'];

        $this->actingAs($admin);

        foreach ([
            '/admin/finance',
            '/admin/finance/wallets',
            '/admin/finance/topups',
            '/admin/finance/payments',
            '/admin/finance/withdrawals',
            '/admin/finance/earnings',
            '/admin/finance/payouts',
            '/admin/finance/refunds',
            '/admin/finance/destinations',
            '/admin/finance/reconciliation',
            '/admin/finance/reports',
            '/admin/finance/audit-logs',
            '/admin/finance/settings',
        ] as $url) {
            $this->get($url)->assertStatus(200);
        }

        // Freeze & unfreeze wallet
        $this->post("/admin/finance/wallets/{$wallet->id}/freeze", [
            'freeze_reason' => 'Investigasi anomali transaksi',
        ])->assertRedirect();

        $this->assertTrue($wallet->fresh()->isFrozen());

        $this->post("/admin/finance/wallets/{$wallet->id}/unfreeze")
            ->assertRedirect();

        $this->assertFalse($wallet->fresh()->isFrozen());

        // Approve refund -> returns 40k -> total balance back to 100k
        $this->post("/admin/finance/refunds/{$refund->id}/approve")
            ->assertRedirect();

        $this->assertSame(100000, (int) $wallet->fresh()->available_balance);

        // Update settings
        $this->post('/admin/finance/settings', [
            'payment_wallet_mode' => 'sandbox',
            'payment_active_provider' => 'xendit',
            'payout_active_provider' => 'xendit',
            'courier_commission_rate_percent' => 12.5,
            'courier_settlement_waiting_minutes' => 720,
            'courier_min_payout_idr' => 25000,
            'courier_auto_payout_enabled' => 1,
            'customer_withdrawal_enabled_in_sandbox' => 1,
        ])->assertRedirect();

        $this->assertSame('12.5', Setting::get('courier_commission_rate_percent'));
        $this->assertSame('25000', Setting::get('courier_min_payout_idr'));

        // CSV report download works
        $csvRes = $this->get('/admin/finance/reports/export?type=ledger')
            ->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $csvRes->headers->get('content-type'));

        // Manual reconciliation run
        $this->post('/admin/finance/reconciliation/run')->assertRedirect();
        $this->assertTrue(app(LedgerReconciliationService::class)->runReconciliation()['is_clean']);
    }
}
