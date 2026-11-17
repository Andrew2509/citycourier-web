<?php

namespace Tests\Traits;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CreatesPaymentWalletSchema
{
    protected function setUpBaseTablesForWalletTests(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique()->nullable();
                $table->string('phone')->nullable();
                $table->string('role')->default('customer');
                $table->string('password')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('couriers')) {
            Schema::create('couriers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('phone', 20)->nullable();
                $table->string('vehicle_type')->default('motor');
                $table->string('vehicle_plate', 20)->nullable();
                $table->boolean('is_verified')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('shipments')) {
            Schema::create('shipments', function (Blueprint $table) {
                $table->id();
                $table->string('shipment_number')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('customer_name')->default('Customer');
                $table->string('customer_phone')->default('081234567890');
                $table->string('sender_name')->default('Sender');
                $table->string('sender_phone')->default('081234567890');
                $table->text('sender_address')->nullable();
                $table->string('receiver_name')->default('Receiver');
                $table->string('receiver_phone')->default('081234567891');
                $table->text('receiver_address')->nullable();
                $table->string('package_description')->nullable();
                $table->decimal('package_weight', 8, 2)->nullable()->default(1);
                $table->boolean('scheduled_pickup')->default(false);
                $table->string('pickup_time')->nullable();
                $table->timestamp('pickup_scheduled_at')->nullable();
                $table->string('tracking_number')->unique()->nullable();
                $table->string('status')->default('pending');
                $table->string('payment_method')->nullable();
                $table->bigInteger('shipping_cost')->default(0);
                $table->bigInteger('total_cost')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
                $table->foreignId('courier_id')->nullable()->constrained('couriers')->nullOnDelete();
                $table->string('order_number')->unique()->nullable();
                $table->string('customer_name')->nullable();
                $table->string('customer_phone')->nullable();
                $table->text('pickup_address')->nullable();
                $table->decimal('pickup_latitude', 10, 7)->nullable();
                $table->decimal('pickup_longitude', 10, 7)->nullable();
                $table->text('delivery_address')->nullable();
                $table->decimal('delivery_latitude', 10, 7)->nullable();
                $table->decimal('delivery_longitude', 10, 7)->nullable();
                $table->string('package_description')->nullable();
                $table->decimal('package_weight', 8, 2)->nullable()->default(1);
                $table->string('vehicle')->default('motor');
                $table->decimal('price', 15, 2)->default(0);
                $table->boolean('scheduled_pickup')->default(false);
                $table->string('pickup_time')->nullable();
                $table->timestamp('pickup_scheduled_at')->nullable();
                $table->text('notes')->nullable();
                $table->string('status')->default('pending');
                $table->string('delivery_photo')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
                $table->string('payment_id')->unique();
                $table->string('payment_type')->default('wallet');
                $table->string('channel_code')->nullable();
                $table->bigInteger('amount')->default(0);
                $table->string('status')->default('pending');
                $table->string('va_number')->nullable();
                $table->text('qr_string')->nullable();
                $table->text('payment_url')->nullable();
                $table->timestamp('expired_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->json('callback_data')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('withdrawals')) {
            Schema::create('withdrawals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('courier_id')->constrained('couriers')->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->decimal('admin_fee', 15, 2)->default(0);
                $table->decimal('net_amount', 15, 2)->default(0);
                $table->string('status')->default('pending');
                $table->string('provider')->nullable();
                $table->string('idempotency_key')->nullable();
                $table->json('status_history')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wallets')) {
            Schema::create('wallets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('courier_id')->constrained('couriers')->onDelete('cascade');
                $table->string('status')->default('not_active');
                $table->decimal('available_balance', 15, 2)->default(0);
                $table->decimal('pending_balance', 15, 2)->default(0);
                $table->string('currency', 10)->default('IDR');
                $table->timestamps();
                $table->unique('courier_id');
            });
        }

        if (! Schema::hasTable('wallet_transactions')) {
            Schema::create('wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
                $table->foreignId('courier_id')->constrained('couriers')->onDelete('cascade');
                $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
                $table->string('type', 48);
                $table->decimal('amount', 15, 2);
                $table->decimal('fee', 15, 2)->default(0);
                $table->decimal('net_amount', 15, 2);
                $table->string('status', 32)->default('completed');
                $table->string('reference')->nullable();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('group')->default('general');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('app_downloads')) {
            Schema::create('app_downloads', function (Blueprint $table) {
                $table->id();
                $table->string('version')->default('1.0.0');
                $table->boolean('is_active')->default(true);
                $table->string('file_path')->nullable();
                $table->string('download_url')->nullable();
                $table->string('google_drive_url')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function runPaymentWalletMigrations(): void
    {
        $this->setUpBaseTablesForWalletTests();

        $files = glob(database_path('migrations/2026_11_17_*.php'));
        sort($files);

        foreach ($files as $file) {
            $migration = require $file;
            $migration->up();
        }
    }

    protected function setUpSpatiePermissionsForAdminTests(): void
    {
        if (! Schema::hasTable('roles')) {
            $permMigration = require database_path('migrations/2026_04_17_071227_create_permission_tables.php');
            $permMigration->up();
        }

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'settings.view', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'settings.edit', 'guard_name' => 'web']);

        $permMigration = require database_path('migrations/2026_11_17_000013_seed_finance_menu_permissions.php');
        $permMigration->up();
    }
}
