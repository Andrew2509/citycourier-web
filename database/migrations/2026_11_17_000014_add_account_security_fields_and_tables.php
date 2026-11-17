<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'transaction_pin')) {
                    $table->string('transaction_pin', 255)->nullable();
                }
                if (! Schema::hasColumn('users', 'transaction_pin_updated_at')) {
                    $table->timestamp('transaction_pin_updated_at')->nullable();
                }
                if (! Schema::hasColumn('users', 'biometric_enabled')) {
                    $table->boolean('biometric_enabled')->default(false);
                }
                if (! Schema::hasColumn('users', 'security_alerts_enabled')) {
                    $table->boolean('security_alerts_enabled')->default(true);
                }
            });
        }

        if (Schema::hasTable('personal_access_tokens')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                if (! Schema::hasColumn('personal_access_tokens', 'device_name')) {
                    $table->string('device_name', 120)->nullable();
                }
                if (! Schema::hasColumn('personal_access_tokens', 'device_id')) {
                    $table->string('device_id', 120)->nullable();
                }
                if (! Schema::hasColumn('personal_access_tokens', 'platform')) {
                    $table->string('platform', 40)->nullable();
                }
                if (! Schema::hasColumn('personal_access_tokens', 'app_version')) {
                    $table->string('app_version', 40)->nullable();
                }
                if (! Schema::hasColumn('personal_access_tokens', 'ip_address')) {
                    $table->string('ip_address', 45)->nullable();
                }
                if (! Schema::hasColumn('personal_access_tokens', 'location_label')) {
                    $table->string('location_label', 120)->nullable();
                }
                if (! Schema::hasColumn('personal_access_tokens', 'user_agent')) {
                    $table->string('user_agent', 255)->nullable();
                }
                if (! Schema::hasColumn('personal_access_tokens', 'is_trusted')) {
                    $table->boolean('is_trusted')->default(true);
                }
            });

            $patIndexes = array_column(Schema::getIndexes('personal_access_tokens'), 'name');
            if (! in_array('pat_tokenable_device_index', $patIndexes, true)) {
                Schema::table('personal_access_tokens', function (Blueprint $table) {
                    $table->index(['tokenable_id', 'device_id'], 'pat_tokenable_device_index');
                });
            }
        }

        if (! Schema::hasTable('account_security_logs')) {
            Schema::create('account_security_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->unsignedBigInteger('token_id')->nullable()->index();
                $table->string('event_type', 60)->index();
                $table->string('title', 140);
                $table->string('description', 255)->nullable();
                $table->string('device_name', 120)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('status', 24)->default('success');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at'], 'asl_user_created_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_security_logs');

        if (Schema::hasTable('personal_access_tokens')) {
            $patIndexes = array_column(Schema::getIndexes('personal_access_tokens'), 'name');
            Schema::table('personal_access_tokens', function (Blueprint $table) use ($patIndexes) {
                if (in_array('pat_tokenable_device_index', $patIndexes, true)) {
                    $table->dropIndex('pat_tokenable_device_index');
                }
                foreach (['device_name', 'device_id', 'platform', 'app_version', 'ip_address', 'location_label', 'user_agent', 'is_trusted'] as $col) {
                    if (Schema::hasColumn('personal_access_tokens', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                foreach (['transaction_pin', 'transaction_pin_updated_at', 'biometric_enabled', 'security_alerts_enabled'] as $col) {
                    if (Schema::hasColumn('users', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
