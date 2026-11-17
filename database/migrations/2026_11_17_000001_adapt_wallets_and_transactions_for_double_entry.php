<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (Schema::hasTable('wallets')) {
            if ($driver === 'mysql') {
                foreach (Schema::getForeignKeys('wallets') as $fk) {
                    if (in_array('courier_id', $fk['columns'] ?? [], true) && ! empty($fk['name'])) {
                        DB::statement('ALTER TABLE `wallets` DROP FOREIGN KEY `'.$fk['name'].'`');
                    }
                }
            }

            Schema::table('wallets', function (Blueprint $table) use ($driver) {
                if (Schema::hasColumn('wallets', 'courier_id')) {
                    $table->unsignedBigInteger('courier_id')->nullable()->change();
                }
                if (! Schema::hasColumn('wallets', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable();
                }
                if (! Schema::hasColumn('wallets', 'wallet_type')) {
                    $table->string('wallet_type', 24)->default('courier');
                }
                if (! Schema::hasColumn('wallets', 'reserved_balance')) {
                    $table->bigInteger('reserved_balance')->default(0);
                }
                if (! Schema::hasColumn('wallets', 'frozen_at')) {
                    $table->timestamp('frozen_at')->nullable();
                }
                if (! Schema::hasColumn('wallets', 'frozen_by')) {
                    $table->unsignedBigInteger('frozen_by')->nullable();
                }
                if (! Schema::hasColumn('wallets', 'freeze_reason')) {
                    $table->string('freeze_reason', 500)->nullable();
                }
                if ($driver === 'mysql' && Schema::hasColumn('wallets', 'status')) {
                    $table->string('status', 32)->default('active')->change();
                }
            });

            if ($driver === 'mysql') {
                Schema::table('wallets', function (Blueprint $table) {
                    $table->foreign('courier_id')->references('id')->on('couriers')->nullOnDelete();
                });
            }

            $existingIndexes = array_column(Schema::getIndexes('wallets'), 'name');
            Schema::table('wallets', function (Blueprint $table) use ($existingIndexes) {
                if (! in_array('wallets_user_id_wallet_type_unique', $existingIndexes, true)) {
                    $table->unique(['user_id', 'wallet_type'], 'wallets_user_id_wallet_type_unique');
                }
                if (! in_array('wallets_wallet_type_status_index', $existingIndexes, true)) {
                    $table->index(['wallet_type', 'status'], 'wallets_wallet_type_status_index');
                }
            });
        }

        if (Schema::hasTable('wallet_transactions')) {
            if ($driver === 'mysql') {
                foreach (Schema::getForeignKeys('wallet_transactions') as $fk) {
                    if (in_array('courier_id', $fk['columns'] ?? [], true) && ! empty($fk['name'])) {
                        DB::statement('ALTER TABLE `wallet_transactions` DROP FOREIGN KEY `'.$fk['name'].'`');
                    }
                }
            }

            Schema::table('wallet_transactions', function (Blueprint $table) use ($driver) {
                if (Schema::hasColumn('wallet_transactions', 'courier_id')) {
                    $table->unsignedBigInteger('courier_id')->nullable()->change();
                }
                if (! Schema::hasColumn('wallet_transactions', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable();
                }
                if (! Schema::hasColumn('wallet_transactions', 'wallet_type')) {
                    $table->string('wallet_type', 24)->default('courier');
                }
                if (! Schema::hasColumn('wallet_transactions', 'direction')) {
                    $table->string('direction', 12)->nullable();
                }
                if (! Schema::hasColumn('wallet_transactions', 'amount_idr')) {
                    $table->bigInteger('amount_idr')->nullable();
                }
                if (! Schema::hasColumn('wallet_transactions', 'balance_after')) {
                    $table->bigInteger('balance_after')->nullable();
                }
                if (! Schema::hasColumn('wallet_transactions', 'idempotency_key')) {
                    $table->string('idempotency_key', 120)->nullable();
                }
                if (! Schema::hasColumn('wallet_transactions', 'mode')) {
                    $table->string('mode', 20)->default('sandbox');
                }
                if (! Schema::hasColumn('wallet_transactions', 'metadata')) {
                    $table->json('metadata')->nullable();
                }
                if ($driver === 'mysql') {
                    if (Schema::hasColumn('wallet_transactions', 'type')) {
                        $table->string('type', 48)->change();
                    }
                    if (Schema::hasColumn('wallet_transactions', 'status')) {
                        $table->string('status', 32)->default('completed')->change();
                    }
                    if (Schema::hasColumn('wallet_transactions', 'net_amount')) {
                        $table->decimal('net_amount', 15, 2)->default(0)->change();
                    }
                }
            });

            if ($driver === 'mysql') {
                Schema::table('wallet_transactions', function (Blueprint $table) {
                    $table->foreign('courier_id')->references('id')->on('couriers')->nullOnDelete();
                });
            }

            $txIndexes = array_column(Schema::getIndexes('wallet_transactions'), 'name');
            Schema::table('wallet_transactions', function (Blueprint $table) use ($txIndexes) {
                if (! in_array('wallet_transactions_idempotency_key_unique', $txIndexes, true)) {
                    $table->unique('idempotency_key', 'wallet_transactions_idempotency_key_unique');
                }
                if (! in_array('wallet_transactions_user_type_index', $txIndexes, true)) {
                    $table->index(['user_id', 'wallet_type', 'created_at'], 'wallet_transactions_user_type_index');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wallet_transactions')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                foreach (['wallet_transactions_idempotency_key_unique', 'wallet_transactions_user_type_index'] as $idx) {
                    try {
                        $table->dropIndex($idx);
                    } catch (\Throwable) {
                    }
                }
                foreach (['user_id', 'wallet_type', 'direction', 'amount_idr', 'balance_after', 'idempotency_key', 'mode', 'metadata'] as $col) {
                    if (Schema::hasColumn('wallet_transactions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('wallets')) {
            Schema::table('wallets', function (Blueprint $table) {
                foreach (['wallets_user_id_wallet_type_unique', 'wallets_wallet_type_status_index'] as $idx) {
                    try {
                        $table->dropIndex($idx);
                    } catch (\Throwable) {
                    }
                }
                foreach (['user_id', 'wallet_type', 'reserved_balance', 'frozen_at', 'frozen_by', 'freeze_reason'] as $col) {
                    if (Schema::hasColumn('wallets', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
