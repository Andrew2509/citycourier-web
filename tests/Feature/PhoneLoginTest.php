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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Login nomor telepon hanya boleh untuk akun yang sudah terdaftar via Google.
 *
 * Schema dibuat manual di SQLite in-memory mengikuti pola test feature lain
 * (migrasi lama memakai ALTER ... MODIFY khusus MySQL).
 */
class PhoneLoginTest extends TestCase
{
    private const NOT_REGISTERED_MESSAGE = 'Nomor telepon belum terdaftar. Silakan gunakan nomor telepon yang sudah terdaftar melalui Google. Jika belum memiliki akun, silakan daftar terlebih dahulu menggunakan Google.';

    private object $whatsapp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dropSchema();
        $this->createSchema();

        // Jangan memanggil provider WhatsApp sungguhan saat test; simpan kode
        // OTP yang "dikirim" agar bisa dipakai untuk verifikasi.
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

        // Tabel minimal Spatie Permission untuk alur registrasi Google.
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
            'phone' => '081234567890',
            'password' => Hash::make('secret'),
            'role' => 'customer',
        ], $overrides));
    }

    public function test_registered_google_phone_can_login_with_otp(): void
    {
        $user = $this->googleUser(['phone' => '081234567890']);

        $this->postJson('/api/request-otp', ['phone' => '081234567890'])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', AuthController::OTP_SENT_MESSAGE);

        $otp = $this->whatsapp->lastOtp;
        $this->assertNotNull($otp);

        $response = $this->postJson('/api/verify-otp', [
            'phone' => '081234567890',
            'otp' => $otp,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.phone', '081234567890')
            ->assertJsonPath('data.phone_verified', true);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_wrong_otp_returns_clear_message(): void
    {
        $this->googleUser(['phone' => '081234567890']);

        $this->postJson('/api/request-otp', ['phone' => '081234567890'])
            ->assertStatus(200);

        $this->postJson('/api/verify-otp', [
            'phone' => '081234567890',
            'otp' => '0000',
        ])->assertStatus(422)
            ->assertJsonPath('code', AuthController::OTP_INVALID_CODE)
            ->assertJsonPath('message', AuthController::OTP_INVALID_MESSAGE);
    }

    public function test_resend_otp_is_rate_limited(): void
    {
        $this->googleUser(['phone' => '081234567890']);

        $this->postJson('/api/request-otp', ['phone' => '081234567890'])
            ->assertStatus(200);

        $this->postJson('/api/request-otp', ['phone' => '081234567890'])
            ->assertStatus(429)
            ->assertJsonPath('code', AuthController::OTP_COOLDOWN_CODE);
    }

    public function test_unregistered_phone_is_rejected_with_clear_message(): void
    {
        $response = $this->postJson('/api/request-otp', [
            'phone' => '081298765432',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', AuthController::PHONE_NOT_REGISTERED_CODE)
            ->assertJsonPath('message', self::NOT_REGISTERED_MESSAGE);

        // Tidak ada OTP yang dikirim / disimpan untuk nomor tak terdaftar.
        $this->assertNull(Cache::get('otp_081298765432'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_phone_formats_are_normalized_for_lookup(): void
    {
        $cases = [
            ['stored' => '081211112222', 'input' => '6281211112222'],
            ['stored' => '+6281233334444', 'input' => '0812-3333-4444'],
            ['stored' => '0812 5555 6666', 'input' => '81255556666'],
        ];

        foreach ($cases as $case) {
            $user = $this->googleUser(['phone' => $case['stored']]);

            $this->postJson('/api/request-otp', ['phone' => $case['input']])
                ->assertStatus(200)
                ->assertJsonPath('success', true);

            $normalized = match ($case['stored']) {
                '081211112222' => '081211112222',
                '+6281233334444' => '081233334444',
                default => '081255556666',
            };

            $this->assertSame($normalized, $this->whatsapp->lastPhone);

            // Pastikan OTP memang bisa diverifikasi dengan format berbeda.
            $this->postJson('/api/verify-otp', [
                'phone' => $case['input'],
                'otp' => $this->whatsapp->lastOtp,
            ])->assertStatus(200)
                ->assertJsonPath('data.user.id', $user->id);
        }
    }

    #[DataProvider('invalidPhoneProvider')]
    public function test_invalid_phone_is_rejected(string $phone): void
    {
        $this->postJson('/api/request-otp', ['phone' => $phone])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', AuthController::INVALID_PHONE_CODE);
    }

    public static function invalidPhoneProvider(): array
    {
        return [
            'teks' => ['bukan-nomor'],
            'terlalu pendek' => ['0812'],
            'tanpa awalan seluler' => ['1234567890'],
        ];
    }

    public function test_phone_owned_by_non_google_account_is_rejected(): void
    {
        // Nomor ada di data pengguna, tetapi akun bukan akun Google.
        $this->googleUser([
            'firebase_uid' => null,
            'email' => 'bukan.google@gmail.com',
            'phone' => '081277778888',
        ]);

        $this->postJson('/api/request-otp', ['phone' => '081277778888'])
            ->assertStatus(404)
            ->assertJsonPath('code', AuthController::PHONE_NOT_REGISTERED_CODE);
    }

    public function test_legacy_otp_account_with_generated_email_is_rejected(): void
    {
        // Akun lama hasil auto-register OTP: punya firebase_uid, tetapi email
        // dibuat sistem (bukan email Google).
        $this->googleUser([
            'firebase_uid' => 'legacy-phone-uid',
            'email' => 'pelanggan081299990000@neonflux.my.id',
            'phone' => '081299990000',
        ]);

        $this->postJson('/api/request-otp', ['phone' => '081299990000'])
            ->assertStatus(404)
            ->assertJsonPath('code', AuthController::PHONE_NOT_REGISTERED_CODE);
    }

    public function test_verify_otp_never_creates_account_for_unregistered_phone(): void
    {
        $user = $this->googleUser(['phone' => '081298765432']);

        $this->postJson('/api/request-otp', ['phone' => '081298765432'])
            ->assertStatus(200);

        $otp = $this->whatsapp->lastOtp;

        // Akun kehilangan identitas Google setelah OTP dikirim.
        $user->forceFill(['firebase_uid' => null])->save();

        $this->postJson('/api/verify-otp', [
            'phone' => '081298765432',
            'otp' => $otp,
        ])->assertStatus(404)
            ->assertJsonPath('code', AuthController::PHONE_NOT_REGISTERED_CODE);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_verify_otp_rejects_non_google_account_without_issuing_token(): void
    {
        $user = $this->googleUser([
            'email' => 'non.google@gmail.com',
            'phone' => '081234000111',
        ]);

        $this->postJson('/api/request-otp', ['phone' => '081234000111'])
            ->assertStatus(200);

        $otp = $this->whatsapp->lastOtp;

        // Email diubah menjadi email generate sistem (akun OTP lama).
        $user->forceFill(['email' => 'pelanggan081234000111@neonflux.my.id'])->save();

        $this->postJson('/api/verify-otp', [
            'phone' => '081234000111',
            'otp' => $otp,
        ])->assertStatus(404)
            ->assertJsonPath('code', AuthController::PHONE_NOT_REGISTERED_CODE);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_firebase_phone_login_does_not_create_account(): void
    {
        $this->bindFirebaseClaims([
            'sub' => 'phone-auth-uid',
            'phone_number' => '+6281298765432',
        ]);

        $this->postJson('/api/auth/phone', ['id_token' => 'valid-token'])
            ->assertStatus(404)
            ->assertJsonPath('code', AuthController::PHONE_NOT_REGISTERED_CODE);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_firebase_phone_login_keeps_google_identity_unchanged(): void
    {
        $user = $this->googleUser([
            'firebase_uid' => 'google-uid-123',
            'phone' => '081234567890',
        ]);

        // Token Firebase phone punya UID berbeda dari UID Google.
        $this->bindFirebaseClaims([
            'sub' => 'phone-auth-uid',
            'phone_number' => '+6281234567890',
        ]);

        $this->postJson('/api/auth/phone', ['id_token' => 'valid-token'])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id);

        $user->refresh();
        $this->assertSame('google-uid-123', $user->firebase_uid);
        $this->assertSame('081234567890', $user->phone);
    }

    public function test_google_login_still_registers_new_account(): void
    {
        DB::table('roles')->insert([
            'name' => 'customer',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->bindFirebaseClaims([
            'sub' => 'google-uid-new',
            'email' => 'new.customer@gmail.com',
            'name' => 'New Customer',
            'picture' => 'https://example.test/avatar.png',
        ]);

        $this->postJson('/api/auth/google', ['id_token' => 'valid-token'])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'new.customer@gmail.com')
            ->assertJsonPath('data.requires_profile_completion', true)
            ->assertJsonPath('data.profile_completed', false);

        $user = User::where('email', 'new.customer@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-uid-new', $user->firebase_uid);
        $this->assertSame('customer', $user->role);
    }

    public function test_error_response_never_exposes_technical_details(): void
    {
        $body = $this->postJson('/api/request-otp', ['phone' => '081298765432'])
            ->getContent();

        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('Stack trace', $body);
        $this->assertStringNotContainsString('vendor/', $body);
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
}
