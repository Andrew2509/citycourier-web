<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_ledger_entries')) {
            return;
        }

        Schema::create('wallet_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('journal_id', 64)->index();
            $table->string('journal_reference', 80)->index();
            $table->string('dedupe_key', 140)->unique();
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('account_code', 64)->index();
            $table->string('entry_type', 12); // debit | credit
            $table->unsignedBigInteger('amount'); // IDR integer
            $table->string('currency', 8)->default('IDR');
            $table->string('transaction_type', 48)->index();
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key', 120)->nullable()->index();
            $table->string('mode', 20)->default('sandbox')->index();
            $table->string('description', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['reference_type', 'reference_id'], 'wle_reference_index');
            $table->index(['wallet_id', 'account_code'], 'wle_wallet_account_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_ledger_entries');
    }
};
