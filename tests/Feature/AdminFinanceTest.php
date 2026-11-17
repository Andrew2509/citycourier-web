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

    public function test_admin_can_configure_xendit_and_doku_credentials_from_providers_page_without_env(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'provider-admin@city.test',
            'role' => 'admin',
        ]);
        $admin->assignRole('super-admin');

        config([
            'payment_wallet.xendit.secret_key' => '',
            'payment_wallet.xendit.callback_token' => '',
            'payment_wallet.doku.client_id' => '',
            'payment_wallet.doku.secret_key' => '',
        ]);

        $this->actingAs($admin);

        // Render unified Providers page
        $this->get('/admin/providers')
            ->assertStatus(200)
            ->assertSee('Xendit Gateway (Money-In &amp; Payouts)', false)
            ->assertSee('DOKU Jokul Gateway (Money-In)');

        // Save Xendit keys via Admin UI
        $xenditSecret = 'xnd_development_manual_secret_987654321';
        $xenditToken = 'cb_token_manual_123456';

        $this->post('/admin/settings/xendit', [
            'xendit_base_url' => 'https://api.xendit.co',
            'xendit_public_key' => 'xnd_public_development_abc',
            'xendit_secret_key' => $xenditSecret,
            'xendit_callback_token' => $xenditToken,
            'activate_xendit' => 1,
        ])->assertRedirect();

        // Stored encrypted at rest (not plaintext) and NEVER echoed raw into HTML
        $rawStored = Setting::get('xendit_secret_key');
        $this->assertStringStartsWith('enc:', (string) $rawStored);
        $this->assertStringNotContainsString($xenditSecret, (string) $rawStored);

        $providersHtml = $this->get('/admin/providers')->assertStatus(200)->getContent();
        $this->assertStringNotContainsString($xenditSecret, $providersHtml);
        $this->assertStringNotContainsString($xenditToken, $providersHtml);

        // Saving again with blank secret preserves existing encrypted secret
        $this->post('/admin/settings/xendit', [
            'xendit_base_url' => 'https://api.xendit.co',
            'xendit_public_key' => 'xnd_public_development_updated',
        ])->assertRedirect();

        $xenditProvider = app(\App\Modules\Payment\Providers\XenditPaymentProvider::class);
        $this->assertTrue($xenditProvider->isConfigured());
        $this->assertTrue($xenditProvider->verifyWebhookSignature(['x-callback-token' => $xenditToken], '{}', []));

        // Save DOKU keys via Admin UI
        $dokuSecret = 'SK-DOKU-MANUAL-SECRET-999';
        $this->post('/admin/settings/doku', [
            'doku_sandbox_url' => 'https://api-sandbox.doku.com',
            'doku_production_url' => 'https://api.doku.com',
            'doku_client_id' => 'BRN-MANUAL-001',
            'doku_secret_key' => $dokuSecret,
            'activate_doku' => 1,
        ])->assertRedirect();

        $this->assertStringStartsWith('enc:', (string) Setting::get('doku_secret_key'));
        $dokuHtml = $this->get('/admin/providers')->assertStatus(200)->getContent();
        $this->assertStringNotContainsString($dokuSecret, $dokuHtml);

        $dokuProvider = app(\App\Modules\Payment\Providers\DokuPaymentProvider::class);
        $this->assertTrue($dokuProvider->isConfigured());

        // Test connection endpoints with mocked HTTP responses
        \Illuminate\Support\Facades\Http::fake([
            'https://api.xendit.co/balance' => \Illuminate\Support\Facades\Http::response(['balance' => 1500000], 200),
            'https://api-sandbox.doku.com/*' => \Illuminate\Support\Facades\Http::response(['message' => 'Not found'], 404),
        ]);

        $this->postJson('/admin/settings/xendit/test')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->postJson('/admin/settings/doku/test')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_toggle_every_provider_on_and_off_with_real_runtime_gating(): void
    {
        $admin = User::create([
            'name' => 'Toggle Admin',
            'email' => 'toggle-admin@city.test',
            'role' => 'admin',
        ]);
        $admin->assignRole('super-admin');

        $customer = User::create([
            'name' => 'Non Admin',
            'email' => 'not-admin@city.test',
            'role' => 'customer',
        ]);

        // Non-admin blocked
        $this->actingAs($customer)
            ->postJson('/admin/providers/xendit/toggle', ['enabled' => 0])
            ->assertStatus(403);

        $this->actingAs($admin);

        // Save Xendit key first
        $this->post('/admin/settings/xendit', [
            'xendit_base_url' => 'https://api.xendit.co',
            'xendit_secret_key' => 'xnd_development_toggle_test_12345',
        ])->assertRedirect();

        $xenditPayment = app(\App\Modules\Payment\Providers\XenditPaymentProvider::class);
        $xenditPayout = app(\App\Modules\Payment\Providers\XenditPayoutProvider::class);
        $this->assertTrue($xenditPayment->isConfigured());
        $this->assertTrue($xenditPayout->isConfigured());

        // Turn Xendit OFF -> immediately disabled at runtime without erasing saved keys
        $this->post('/admin/providers/xendit/toggle', ['enabled' => 0])->assertRedirect();
        $this->assertFalse($xenditPayment->isConfigured());
        $this->assertFalse($xenditPayout->isConfigured());

        // Turn Xendit back ON -> saved encrypted keys still intact and active
        $this->post('/admin/providers/xendit/toggle', ['enabled' => 1])->assertRedirect();
        $this->assertTrue($xenditPayment->isConfigured());

        // Turn WhatsApp, Map, and RajaOngkir OFF -> runtime services report disabled cleanly
        foreach (['whatsapp', 'map', 'rajaongkir', 'doku', 'ipaymu', 'dana', 'ovo'] as $provider) {
            $this->postJson("/admin/providers/{$provider}/toggle", ['enabled' => 0])
                ->assertStatus(200)
                ->assertJsonPath('enabled', false);
        }

        $waResult = app(\App\Services\WhatsAppManager::class)->sendMessage('081234567890', 'Halo');
        $this->assertFalse($waResult['success']);
        $this->assertTrue($waResult['disabled'] ?? false);

        $mapResult = app(\App\Services\MapService::class)->getRoute('106.8,-6.2', '106.9,-6.3');
        $this->assertFalse($mapResult['success']);
        $this->assertTrue($mapResult['disabled'] ?? false);

        Setting::set('rajaongkir_api_key', 'test-key-123');
        $this->assertFalse((new \App\Services\RajaOngkirService())->hasValidKey());

        // Unknown provider returns 404
        $this->post('/admin/providers/unknown_gw/toggle')->assertStatus(404);

        // Providers view renders toggle controls for all 8 providers
        $html = $this->get('/admin/providers')->assertStatus(200)->getContent();
        foreach (['whatsapp', 'map', 'dana', 'ovo', 'ipaymu', 'rajaongkir', 'xendit', 'doku'] as $provider) {
            $this->assertStringContainsString("/admin/providers/{$provider}/toggle", $html);
        }
    }
}
