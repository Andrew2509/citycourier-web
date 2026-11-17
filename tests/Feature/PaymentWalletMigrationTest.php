<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\CreatesPaymentWalletSchema;

class PaymentWalletMigrationTest extends TestCase
{
    use CreatesPaymentWalletSchema;

    public function test_new_payment_and_wallet_migrations_create_expected_tables_and_columns(): void
    {
        $this->runPaymentWalletMigrations();

        $expectedTables = [
            'wallets',
            'wallet_transactions',
            'wallet_balances',
            'wallet_ledger_entries',
            'topup_transactions',
            'payment_transactions',
            'payout_destinations',
            'payout_transactions',
            'courier_earnings',
            'refund_transactions',
            'payment_webhooks',
            'payment_provider_configs',
            'wallet_audit_logs',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing expected table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('wallets', [
            'user_id',
            'wallet_type',
            'reserved_balance',
            'frozen_at',
            'frozen_by',
            'freeze_reason',
        ]));

        $this->assertTrue(Schema::hasColumns('wallet_transactions', [
            'reference',
            'fee',
            'net_amount',
            'user_id',
            'wallet_type',
            'direction',
            'amount_idr',
            'balance_after',
            'idempotency_key',
            'mode',
            'metadata',
        ]));

        $this->assertFalse(Schema::hasColumn('wallet_transactions', 'reference_id'));

        $this->assertTrue(Schema::hasColumns('wallet_ledger_entries', [
            'journal_id',
            'journal_reference',
            'dedupe_key',
            'wallet_id',
            'account_code',
            'entry_type',
            'amount',
            'mode',
        ]));
    }

    public function test_payment_and_wallet_migrations_are_idempotent_on_rerun(): void
    {
        $this->runPaymentWalletMigrations();
        $this->runPaymentWalletMigrations();

        $this->assertTrue(Schema::hasTable('wallet_transactions'));
        $this->assertTrue(Schema::hasColumn('wallet_transactions', 'idempotency_key'));
        $this->assertTrue(Schema::hasColumn('wallet_transactions', 'reference'));
        $this->assertTrue(Schema::hasColumn('wallets', 'user_id'));
    }
}
