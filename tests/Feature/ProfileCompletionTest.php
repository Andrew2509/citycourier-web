<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AuthController;
use App\Models\User;
use App\Services\WhatsAppManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Registrasi Google otomatis + kelengkapan profil + verifikasi OTP nomor
 * telepon pada akun yang sedang login.
 *
 * Schema dibuat manual di SQLite in-memory mengikuti pola test feature lain.
 */
class ProfileCompletionTest extends TestCase
{
    private object $whatsapp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dropSchema();
        $this->createSchema();

        $this->whatsapp = new class extends WhatsAppManager {
            public ?string $lastOtp = null;

            public ?string $lastPhone = null;

            public function sendOtp(string $phone, string $otp): array
            {
                $this->lastPhone = $phone;
                $this->lastOtp = $otp;

                return ['success' => true];
            }
        };

        $this->app->instance(WhatsAppManager::class, $this->whatsapp);
        Cache::flush();
    }

    private function dropSchema(): void
    {
        DB::statement('PRAGMA foreign_keys=0');

        foreach ([
            'model_has_roles',
            'roles',
            'personal_access_tokens',
            'couriers',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        DB::statement('PRAGMA foreign_keys=1');
    }

    private function createSchema(): void
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('firebase_uid')->nullable()->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('address')->nullable();
            $table->string('avatar')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('customer');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function ($table) {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tokenable_type', 'tokenable_id']);
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

        Schema::create('roles', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_roles', function ($table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->primary(['role_id', 'model_id', 'model_type']);
        });
    }

    private function googleUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'firebase_uid' => 'google-' . Str::random(10),
            'name' => 'Andrew Pratama',
            'email' => Str::lower(Str::random(10)) . '@gmail.com',
            'phone' => null,
            'phone_verified_at' => null,
            'password' => Hash::make('secret'),
            'role' => 'customer',
        ], $overrides));
    }

    private function bindFirebaseClaims(array $claims): void
    {
        $verifier = new class($claims) {
            public function __construct(private array $claims)
            {
            }

            public function verifyIdToken(string $token)
            {
                return new class($this->claims) {
                    public function __construct(private array $claims)
                    {
                    }

                    public function claims()
                    {
                        return collect($this->claims);
                    }
                };
            }
        };

        $this->app->instance('firebase.auth', $verifier);
    }

    public function test_new_google_account_is_created_with_google_data(): void
    {
        DB::table('roles')->insert([
            'name' => 'customer',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->bindFirebaseClaims([
            'sub' => 'google-uid-new',
            'email' => 'New.Customer@Gmail.com',
            'name' => 'New Customer',
            'picture' => 'https://example.test/avatar.png',
        ]);

        $response = $this->postJson('/api/auth/google', ['id_token' => 'valid-token']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'New Customer')
            ->assertJsonPath('data.user.email', 'new.customer@gmail.com')
            ->assertJsonPath('data.user.photo_url', 'https://example.test/avatar.png')
            ->assertJsonPath('data.requires_profile_completion', true)
            ->assertJsonPath('data.profile_completed', false)
            ->assertJsonPath('data.phone_verified', false);

        $this->assertNotEmpty($response->json('data.token'));

        $user = User::where('email', 'new.customer@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-uid-new', $user->firebase_uid);
        $this->assertSame('New Customer', $user->name);
        $this->assertSame('https://example.test/avatar.png', $user->avatar);
        $this->assertSame('customer', $user->role);
    }

    public function test_returning_google_user_is_not_duplicated(): void
    {
        $user = $this->googleUser([
            'firebase_uid' => 'google-uid-returning',
            'email' => 'returning@gmail.com',
        ]);

        $this->bindFirebaseClaims([
            'sub' => 'google-uid-returning',
            'email' => 'returning@gmail.com',
            'name' => 'Returning User',
        ]);

        $this->postJson('/api/auth/google', ['id_token' => 'valid-token'])
            ->assertStatus(200)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.requires_profile_completion', true);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_existing_email_account_is_linked_without_duplicate(): void
    {
        $user = $this->googleUser([
            'firebase_uid' => null,
            'email' => 'linked@gmail.com',
            'name' => 'Linked User',
        ]);

        $this->bindFirebaseClaims([
            'sub' => 'google-uid-linked',
            'email' => 'linked@gmail.com',
            'name' => 'Linked User',
        ]);

        $this->postJson('/api/auth/google', ['id_token' => 'valid-token'])
            ->assertStatus(200)
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('google-uid-linked', $user->fresh()->firebase_uid);
    }

    public function test_complete_google_user_can_login_without_completion(): void
    {
        $user = $this->googleUser([
            'firebase_uid' => 'google-uid-complete',
            'email' => 'complete@gmail.com',
            'phone' => '081234567890',
            'phone_verified_at' => now(),
        ]);

        $this->bindFirebaseClaims([
            'sub' => 'google-uid-complete',
            'email' => 'complete@gmail.com',
            'name' => 'Complete User',
        ]);

        $this->postJson('/api/auth/google', ['id_token' => 'valid-token'])
            ->assertStatus(200)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.profile_completed', true)
            ->assertJsonPath('data.requires_profile_completion', false)
            ->assertJsonPath('data.phone_verified', true);
    }

    public function test_phone_otp_verifies_number_and_saves_account(): void
    {
        $user = $this->googleUser([
            'email' => 'verify@gmail.com',
            'phone' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/profile/phone/request-otp', ['phone' => '081298765432'])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', AuthController::OTP_SENT_MESSAGE)
            ->assertJsonPath('retry_after', 60);

        $otp = $this->whatsapp->lastOtp;
        $this->assertNotNull($otp);
        $this->assertSame('081298765432', $this->whatsapp->lastPhone);

        $this->postJson('/api/profile/phone/verify-otp', [
            'phone' => '081298765432',
            'otp' => $otp,
        ])->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', AuthController::OTP_VERIFIED_MESSAGE)
            ->assertJsonPath('data.phone_verified', true)
            ->assertJsonPath('data.profile_completed', true)
            ->assertJsonPath('data.user.phone', '081298765432');

        $user->refresh();
        $this->assertSame('081298765432', $user->phone);
        $this->assertNotNull($user->phone_verified_at);
    }

    public function test_wrong_phone_otp_returns_clear_message(): void
    {
        $user = $this->googleUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/profile/phone/request-otp', ['phone' => '081298765432'])
            ->assertStatus(200);

        $this->postJson('/api/profile/phone/verify-otp', [
            'phone' => '081298765432',
            'otp' => '000000',
        ])->assertStatus(422)
            ->assertJsonPath('code', AuthController::OTP_INVALID_CODE)
            ->assertJsonPath('message', AuthController::OTP_INVALID_MESSAGE);

        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_expired_phone_otp_returns_clear_message(): void
    {
        $user = $this->googleUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/profile/phone/request-otp', ['phone' => '081298765432'])
            ->assertStatus(200);

        $otp = $this->whatsapp->lastOtp;

        $this->travel(6)->minutes();

        $this->postJson('/api/profile/phone/verify-otp', [
            'phone' => '081298765432',
            'otp' => $otp,
        ])->assertStatus(422)
            ->assertJsonPath('code', AuthController::OTP_EXPIRED_CODE)
            ->assertJsonPath('message', AuthController::OTP_EXPIRED_MESSAGE);

        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_phone_otp_can_only_be_used_once(): void
    {
        $user = $this->googleUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/profile/phone/request-otp', ['phone' => '081298765432'])
            ->assertStatus(200);

        $otp = $this->whatsapp->lastOtp;

        $this->postJson('/api/profile/phone/verify-otp', [
            'phone' => '081298765432',
            'otp' => $otp,
        ])->assertStatus(200);

        $this->postJson('/api/profile/phone/verify-otp', [
            'phone' => '081298765432',
            'otp' => $otp,
        ])->assertStatus(422)
            ->assertJsonPath('code', AuthController::OTP_INVALID_CODE);
    }

    public function test_too_many_wrong_attempts_locks_the_otp(): void
    {
        $user = $this->googleUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/profile/phone/request-otp', ['phone' => '081298765432'])
            ->assertStatus(200);

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/profile/phone/verify-otp', [
                'phone' => '081298765432',
                'otp' => '000000',
            ])->assertStatus(422)
                ->assertJsonPath('code', AuthController::OTP_INVALID_CODE);
        }

        $this->postJson('/api/profile/phone/verify-otp', [
            'phone' => '081298765432',
            'otp' => '000000',
        ])->assertStatus(422)
            ->assertJsonPath('code', AuthController::OTP_TOO_MANY_CODE)
            ->assertJsonPath('message', AuthController::OTP_TOO_MANY_MESSAGE);
    }

    public function test_resend_phone_otp_has_cooldown(): void
    {
        $user = $this->googleUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/profile/phone/request-otp', ['phone' => '081298765432'])
            ->assertStatus(200);

        $this->postJson('/api/profile/phone/request-otp', ['phone' => '081298765432'])
            ->assertStatus(429)
            ->assertJsonPath('code', AuthController::OTP_COOLDOWN_CODE);

        // Setelah cooldown habis, kode baru boleh diminta lagi.
        $this->travel(61)->seconds();

        $this->postJson('/api/profile/phone/request-otp', ['phone' => '081298765432'])
            ->assertStatus(200);
    }

    public function test_phone_owned_by_another_account_is_rejected(): void
    {
        $this->googleUser([
            'email' => 'owner@gmail.com',
            'phone' => '081211112222',
            'phone_verified_at' => now(),
        ]);

        $user = $this->googleUser(['email' => 'other@gmail.com']);
        Sanctum::actingAs($user);

        $this->postJson('/api/profile/phone/request-otp', ['phone' => '081211112222'])
            ->assertStatus(422)
            ->assertJsonPath('code', AuthController::PHONE_TAKEN_CODE)
            ->assertJsonPath('message', AuthController::PHONE_TAKEN_MESSAGE);

        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_profile_update_cannot_change_phone_without_otp(): void
    {
        $user = $this->googleUser([
            'email' => 'phone-update@gmail.com',
            'phone' => '081200000001',
            'phone_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/profile/update', [
            'name' => 'Nama Baru',
            'email' => 'phone-update@gmail.com',
            'phone' => '081399999999',
        ])->assertStatus(200);

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('081200000001', $user->phone);
        $this->assertNotNull($user->phone_verified_at);
    }

    public function test_google_email_cannot_be_changed_from_app(): void
    {
        $user = $this->googleUser(['email' => 'locked@gmail.com']);

        Sanctum::actingAs($user);

        $this->postJson('/api/profile/update', [
            'name' => 'Locked User',
            'email' => 'attacker@gmail.com',
        ])->assertStatus(422)
            ->assertJsonPath('code', AuthController::EMAIL_MANAGED_CODE)
            ->assertJsonPath('message', AuthController::EMAIL_MANAGED_MESSAGE);

        $this->assertSame('locked@gmail.com', $user->fresh()->email);
    }

    public function test_profile_endpoint_reports_completion_status(): void
    {
        $user = $this->googleUser([
            'email' => 'status@gmail.com',
            'phone' => '081234567890',
            'phone_verified_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.profile_completed', false)
            ->assertJsonPath('data.requires_profile_completion', true)
            ->assertJsonPath('data.phone_verified', false)
            ->assertJsonPath('data.missing_profile_fields.0', 'phone_verification');
    }

    public function test_profile_phone_endpoints_require_authentication(): void
    {
        $this->postJson('/api/profile/phone/request-otp', ['phone' => '081298765432'])
            ->assertStatus(401);

        $this->postJson('/api/profile/phone/verify-otp', [
            'phone' => '081298765432',
            'otp' => '123456',
        ])->assertStatus(401);
    }

    public function test_google_user_data_and_status_sync_with_admin_panel(): void
    {
        $this->bindFirebaseClaims([
            'sub' => 'google-uid-admin-sync',
            'email' => 'admin.sync@gmail.com',
            'name' => 'Siti Rahma',
            'picture' => 'https://example.test/siti.png',
        ]);

        // 1. Pengguna baru login menggunakan Google.
        $this->postJson('/api/auth/google', ['id_token' => 'valid-token'])
            ->assertStatus(200)
            ->assertJsonPath('data.user.registration_method', 'Google')
            ->assertJsonPath('data.user.account_status', 'Belum Lengkap');

        $user = User::where('email', 'admin.sync@gmail.com')->firstOrFail();
        $this->assertSame('Google', $user->registrationMethod());
        $this->assertSame('Belum Lengkap', $user->accountStatus());
        $this->assertFalse($user->hasVerifiedPhone());
        $this->assertSame('https://example.test/siti.png', $user->photo_url);

        // 2. Pengguna melengkapi nomor telepon dan memverifikasi OTP.
        Sanctum::actingAs($user);

        $this->postJson('/api/profile/phone/request-otp', ['phone' => '081355556666'])
            ->assertStatus(200);

        $this->postJson('/api/profile/phone/verify-otp', [
            'phone' => '081355556666',
            'otp' => $this->whatsapp->lastOtp,
            'name' => 'Siti Rahma Putri',
            'address' => 'Jl. Sudirman No. 10, Jakarta',
        ])->assertStatus(200)
            ->assertJsonPath('data.profile_completed', true)
            ->assertJsonPath('data.user.account_status', 'Aktif');

        $user->refresh();
        $this->assertSame('Siti Rahma Putri', $user->name);
        $this->assertSame('081355556666', $user->phone);
        $this->assertTrue($user->hasVerifiedPhone());
        $this->assertSame('Google', $user->registrationMethod());
        $this->assertSame('Aktif', $user->accountStatus());
        $this->assertDatabaseCount('users', 1);
    }
}
