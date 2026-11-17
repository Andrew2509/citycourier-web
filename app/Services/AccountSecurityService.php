<?php

namespace App\Services;

use App\Models\AccountSecurityLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

class AccountSecurityService
{
    public const PIN_MAX_ATTEMPTS = 5;

    public const PIN_LOCK_SECONDS = 300;

    public function __construct(
        private readonly PhoneVerificationService $phoneVerification,
    ) {}

    public function buildOverview(User $user, Request $request): array
    {
        $currentToken = $user->currentAccessToken();
        if ($currentToken instanceof PersonalAccessToken) {
            $this->attachTokenMetadata($currentToken, $request);
        }

        $devices = $this->listDevices($user, $currentToken);
        $logs = $this->recentLogs($user, 20);

        $phoneVerified = $user->hasVerifiedPhone();
        $hasPin = $user->hasTransactionPin();
        $biometricEnabled = (bool) ($user->getAttribute('biometric_enabled') ?? false);
        $securityAlertsEnabled = (bool) ($user->getAttribute('security_alerts_enabled') ?? true);

        $layers = [
            [
                'key' => 'phone_verified',
                'label' => 'Verifikasi Nomor Telepon',
                'active' => $phoneVerified,
            ],
            [
                'key' => 'transaction_pin',
                'label' => 'PIN Transaksi 6 Digit',
                'active' => $hasPin,
            ],
            [
                'key' => 'biometric_login',
                'label' => 'Masuk dengan Biometrik',
                'active' => $biometricEnabled,
            ],
            [
                'key' => 'security_alerts',
                'label' => 'Notifikasi Keamanan Aktif',
                'active' => $securityAlertsEnabled,
            ],
        ];

        $activeLayers = count(array_filter($layers, fn (array $layer) => $layer['active']));
        $totalLayers = count($layers);

        if ($activeLayers >= 3) {
            $scoreLabel = 'Kuat';
            $scoreLevel = 'strong';
            $scoreSubtitle = 'Akun Anda terlindungi dengan standar enkripsi City Courier Express.';
        } elseif ($activeLayers === 2) {
            $scoreLabel = 'Sedang';
            $scoreLevel = 'medium';
            $scoreSubtitle = 'Aktifkan PIN transaksi dan biometrik untuk perlindungan lebih optimal.';
        } else {
            $scoreLabel = 'Perlu Ditingkatkan';
            $scoreLevel = 'weak';
            $scoreSubtitle = 'Lengkapi verifikasi nomor HP dan PIN transaksi untuk menjaga keamanan akun.';
        }

        $loginMethods = [];
        if ($user->hasGoogleAuth()) {
            $loginMethods[] = 'Google';
        }
        if ($phoneVerified) {
            $loginMethods[] = 'Nomor Telepon & OTP';
        }
        if ($hasPin) {
            $loginMethods[] = 'PIN Transaksi';
        }
        if ($loginMethods === []) {
            $loginMethods[] = $user->registrationMethod();
        }

        $latestLoginLog = collect($logs)->first(function (array $log) {
            return str_starts_with((string) ($log['event_type'] ?? ''), 'login_');
        });

        $currentOrLatestDevice = $devices[0] ?? null;
        $latestLoginSummary = $latestLoginLog
            ? trim(($latestLoginLog['device_name'] ?: 'Perangkat Terdaftar') . ' • ' . ($latestLoginLog['time_label'] ?: 'Baru saja'))
            : ($currentOrLatestDevice
                ? trim(($currentOrLatestDevice['device_name'] ?: 'Perangkat Ini') . ' • ' . ($currentOrLatestDevice['last_used_label'] ?: 'Aktif sekarang'))
                : 'Belum ada aktivitas login tercatat');

        $pinUpdatedAt = $user->getAttribute('transaction_pin_updated_at');
        $pinUpdatedHuman = $hasPin
            ? ($pinUpdatedAt instanceof CarbonInterface
                ? 'Terakhir diubah ' . $this->humanTimeAgoId($pinUpdatedAt)
                : 'PIN transaksi aktif')
            : 'Belum diatur • Aktifkan untuk transaksi & keamanan';

        $alertCount = collect($logs)
            ->filter(fn (array $log) => in_array($log['status'] ?? '', ['warning', 'alert'], true))
            ->count();

        if (! $phoneVerified || ! $hasPin) {
            $alertCount++;
        }

        return [
            'score' => [
                'active_layers' => $activeLayers,
                'total_layers' => $totalLayers,
                'label' => $scoreLabel,
                'level' => $scoreLevel,
                'subtitle' => $scoreSubtitle,
                'layers' => $layers,
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->hasRealEmail() ? $user->email : null,
                'phone' => $user->phone,
                'phone_formatted' => $this->formatDisplayPhone($user->phone),
                'phone_verified' => $phoneVerified,
                'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
                'has_google_auth' => $user->hasGoogleAuth(),
                'registration_method' => $user->registrationMethod(),
                'login_methods' => $loginMethods,
                'login_method_summary' => implode(' • ', $loginMethods),
            ],
            'pin' => [
                'has_pin' => $hasPin,
                'updated_at' => $pinUpdatedAt instanceof CarbonInterface ? $pinUpdatedAt->toIso8601String() : null,
                'updated_human' => $pinUpdatedHuman,
            ],
            'preferences' => [
                'biometric_enabled' => $biometricEnabled,
                'security_alerts_enabled' => $securityAlertsEnabled,
            ],
            'devices' => $devices,
            'trusted_device_count' => count($devices),
            'latest_login' => [
                'summary' => $latestLoginSummary,
                'device_name' => $latestLoginLog['device_name'] ?? ($currentOrLatestDevice['device_name'] ?? null),
                'time_label' => $latestLoginLog['time_label'] ?? ($currentOrLatestDevice['last_used_label'] ?? null),
                'ip_address' => $latestLoginLog['ip_address'] ?? ($currentOrLatestDevice['ip_address'] ?? null),
            ],
            'activity_logs' => $logs,
            'alert_count' => $alertCount,
        ];
    }

    public function isWeakPin(string $pin): bool
    {
        if (preg_match('/^\d{6}$/', $pin) !== 1) {
            return true;
        }

        if (preg_match('/^(\d)\1{5}$/', $pin) === 1) {
            return true;
        }

        $digits = array_map('intval', str_split($pin));
        $asc = true;
        $desc = true;
        for ($i = 1; $i < 6; $i++) {
            if ($digits[$i] - $digits[$i - 1] !== 1) {
                $asc = false;
            }
            if ($digits[$i] - $digits[$i - 1] !== -1) {
                $desc = false;
            }
        }

        return $asc || $desc;
    }

    /**
     * Verifikasi PIN transaksi dengan pembatasan percobaan gagal.
     *
     * @return array{ok: bool, status_code?: int, code?: string, message?: string, attempts_left?: int, retry_after?: int}
     */
    public function verifyPin(User $user, string $pin, ?Request $request = null, bool $silentSuccessLog = true): array
    {
        if (! $user->hasTransactionPin()) {
            return [
                'ok' => false,
                'status_code' => 422,
                'code' => 'PIN_NOT_SET',
                'message' => 'PIN transaksi belum diatur pada akun ini.',
            ];
        }

        $lockKey = $this->pinLockKey((int) $user->id);
        $lockUntil = (int) Cache::get($lockKey, 0);
        $now = now()->getTimestamp();

        if ($lockUntil > $now) {
            return [
                'ok' => false,
                'status_code' => 423,
                'code' => 'PIN_LOCKED',
                'message' => 'Terlalu banyak percobaan PIN salah. Silakan coba lagi beberapa saat lagi atau gunakan Lupa PIN.',
                'retry_after' => $lockUntil - $now,
            ];
        }

        if (! Hash::check($pin, (string) $user->getAttribute('transaction_pin'))) {
            $failKey = $this->pinFailKey((int) $user->id);
            $attempts = (int) Cache::get($failKey, 0) + 1;

            if ($attempts >= self::PIN_MAX_ATTEMPTS) {
                Cache::forget($failKey);
                Cache::put($lockKey, $now + self::PIN_LOCK_SECONDS, now()->addSeconds(self::PIN_LOCK_SECONDS));

                $this->logEvent(
                    $user,
                    'pin_locked',
                    'PIN Transaksi Terkunci Sementara',
                    'Terjadi 5 kali percobaan PIN salah beruntun.',
                    $request,
                    'alert'
                );

                return [
                    'ok' => false,
                    'status_code' => 423,
                    'code' => 'PIN_LOCKED',
                    'message' => 'Terlalu banyak percobaan PIN salah. Akses PIN dikunci sementara selama 5 menit.',
                    'retry_after' => self::PIN_LOCK_SECONDS,
                ];
            }

            Cache::put($failKey, $attempts, now()->addMinutes(15));
            $attemptsLeft = self::PIN_MAX_ATTEMPTS - $attempts;

            return [
                'ok' => false,
                'status_code' => 422,
                'code' => 'PIN_INVALID',
                'message' => "PIN tidak sesuai. Sisa percobaan: {$attemptsLeft}x.",
                'attempts_left' => $attemptsLeft,
            ];
        }

        Cache::forget($this->pinFailKey((int) $user->id));
        Cache::forget($lockKey);

        if (! $silentSuccessLog) {
            $this->logEvent(
                $user,
                'pin_verified',
                'Verifikasi PIN Berhasil',
                'PIN transaksi berhasil diverifikasi.',
                $request
            );
        }

        return ['ok' => true];
    }

    /**
     * Buat baru atau ubah PIN transaksi 6 digit.
     *
     * @return array{ok: bool, status_code?: int, code?: string, message: string, attempts_left?: int, retry_after?: int}
     */
    public function setOrChangePin(
        User $user,
        string $newPin,
        ?string $currentPin = null,
        ?string $otp = null,
        ?Request $request = null,
    ): array {
        if (preg_match('/^\d{6}$/', $newPin) !== 1) {
            return [
                'ok' => false,
                'status_code' => 422,
                'code' => 'PIN_FORMAT_INVALID',
                'message' => 'PIN harus terdiri dari 6 digit angka.',
            ];
        }

        if ($this->isWeakPin($newPin)) {
            return [
                'ok' => false,
                'status_code' => 422,
                'code' => 'PIN_WEAK',
                'message' => 'Hindari PIN berurutan (123456) atau angka berulang (111111).',
            ];
        }

        $hadPin = $user->hasTransactionPin();

        if ($hadPin) {
            if ($otp !== null && trim($otp) !== '') {
                $phone = PhoneNumber::normalize($user->phone);
                if ($phone === null || ! $user->hasVerifiedPhone()) {
                    return [
                        'ok' => false,
                        'status_code' => 422,
                        'code' => 'PHONE_NOT_VERIFIED',
                        'message' => 'Nomor telepon terverifikasi diperlukan untuk reset PIN via OTP.',
                    ];
                }

                $otpResult = $this->phoneVerification->verify(
                    PhoneVerificationService::PURPOSE_SECURITY_PIN,
                    $phone,
                    trim($otp),
                    (int) $user->id
                );

                if (($otpResult['status'] ?? null) !== PhoneVerificationService::STATUS_OK) {
                    $status = $otpResult['status'] ?? PhoneVerificationService::STATUS_INVALID;
                    $message = match ($status) {
                        PhoneVerificationService::STATUS_EXPIRED => AuthController::OTP_EXPIRED_MESSAGE,
                        PhoneVerificationService::STATUS_TOO_MANY => AuthController::OTP_TOO_MANY_MESSAGE,
                        default => AuthController::OTP_INVALID_MESSAGE,
                    };

                    return [
                        'ok' => false,
                        'status_code' => 422,
                        'code' => 'OTP_' . strtoupper((string) $status),
                        'message' => $message,
                        'attempts_left' => $otpResult['attempts_left'] ?? null,
                    ];
                }

                Cache::forget($this->pinFailKey((int) $user->id));
                Cache::forget($this->pinLockKey((int) $user->id));
            } elseif ($currentPin !== null && trim($currentPin) !== '') {
                $check = $this->verifyPin($user, trim($currentPin), $request);
                if (! $check['ok']) {
                    return [
                        'ok' => false,
                        'status_code' => $check['status_code'] ?? 422,
                        'code' => $check['code'] ?? 'PIN_INVALID',
                        'message' => $check['message'] ?? 'PIN saat ini tidak sesuai.',
                        'attempts_left' => $check['attempts_left'] ?? null,
                        'retry_after' => $check['retry_after'] ?? null,
                    ];
                }
            } else {
                return [
                    'ok' => false,
                    'status_code' => 422,
                    'code' => 'CURRENT_PIN_REQUIRED',
                    'message' => 'Masukkan PIN saat ini atau kode OTP untuk mengubah PIN.',
                ];
            }

            if (Hash::check($newPin, (string) $user->getAttribute('transaction_pin'))) {
                return [
                    'ok' => false,
                    'status_code' => 422,
                    'code' => 'PIN_SAME_AS_OLD',
                    'message' => 'PIN baru harus berbeda dengan PIN sebelumnya.',
                ];
            }
        }

        $user->forceFill([
            'transaction_pin' => Hash::make($newPin),
            'transaction_pin_updated_at' => now(),
        ])->save();

        $this->logEvent(
            $user,
            $hadPin ? 'pin_updated' : 'pin_created',
            $hadPin ? 'PIN Transaksi Diperbarui' : 'PIN Transaksi Diaktifkan',
            $hadPin
                ? 'PIN transaksi 6 digit berhasil diperbarui.'
                : 'PIN transaksi 6 digit telah dibuat untuk melindungi akun Anda.',
            $request
        );

        return [
            'ok' => true,
            'message' => $hadPin
                ? 'PIN Transaksi berhasil diperbarui!'
                : 'PIN Transaksi berhasil dibuat!',
        ];
    }

    public function requestPinResetOtp(User $user, ?Request $request = null): array
    {
        $phone = PhoneNumber::normalize($user->phone);
        if ($phone === null || ! $user->hasVerifiedPhone()) {
            return [
                'ok' => false,
                'status_code' => 422,
                'code' => 'PHONE_NOT_VERIFIED',
                'message' => 'Verifikasi nomor telepon terlebih dahulu sebelum reset PIN.',
            ];
        }

        $send = $this->phoneVerification->send(
            PhoneVerificationService::PURPOSE_SECURITY_PIN,
            $phone,
            6,
            (int) $user->id
        );

        if ($send['status'] === PhoneVerificationService::STATUS_COOLDOWN) {
            $retryAfter = max(1, (int) ($send['retry_after'] ?? 60));

            return [
                'ok' => false,
                'status_code' => 429,
                'code' => AuthController::OTP_COOLDOWN_CODE,
                'message' => "Tunggu {$retryAfter} detik sebelum meminta kode OTP baru.",
                'retry_after' => $retryAfter,
            ];
        }

        if ($send['status'] === PhoneVerificationService::STATUS_PROVIDER_DISABLED || ! empty($send['disabled'])) {
            return [
                'ok' => false,
                'status_code' => 503,
                'code' => 'PROVIDER_DISABLED',
                'message' => $send['provider_message'] ?? 'Layanan WhatsApp Gateway sedang dinonaktifkan oleh Admin.',
            ];
        }

        if ($send['status'] !== PhoneVerificationService::STATUS_SENT) {
            return [
                'ok' => false,
                'status_code' => 502,
                'code' => 'OTP_SEND_FAILED',
                'message' => 'Gagal mengirim kode OTP ke WhatsApp. Silakan coba lagi.',
            ];
        }

        $this->logEvent(
            $user,
            'pin_reset_otp_requested',
            'Permintaan Reset PIN via OTP',
            'Kode OTP 6 digit dikirim ke nomor terdaftar.',
            $request
        );

        return [
            'ok' => true,
            'message' => 'Kode OTP 6 digit telah dikirim ke WhatsApp Anda.',
            'retry_after' => $send['retry_after'] ?? PhoneVerificationService::COOLDOWN_SECONDS,
            'expires_in' => PhoneVerificationService::EXPIRY_MINUTES * 60,
        ];
    }

    public function updatePreferences(User $user, array $input, ?Request $request = null): array
    {
        $updates = [];

        if (array_key_exists('biometric_enabled', $input)) {
            $enabled = filter_var($input['biometric_enabled'], FILTER_VALIDATE_BOOLEAN);
            $updates['biometric_enabled'] = $enabled;
            $this->logEvent(
                $user,
                'biometric_toggled',
                $enabled ? 'Login Biometrik Diaktifkan' : 'Login Biometrik Dinonaktifkan',
                $enabled
                    ? 'Verifikasi sidik jari / Face ID diaktifkan untuk keamanan tambahan.'
                    : 'Login biometrik dimatikan pada perangkat.',
                $request,
                $enabled ? 'success' : 'warning'
            );
        }

        if (array_key_exists('security_alerts_enabled', $input)) {
            $enabled = filter_var($input['security_alerts_enabled'], FILTER_VALIDATE_BOOLEAN);
            $updates['security_alerts_enabled'] = $enabled;
            $this->logEvent(
                $user,
                'alerts_toggled',
                $enabled ? 'Notifikasi Keamanan Diaktifkan' : 'Notifikasi Keamanan Dimatikan',
                $enabled
                    ? 'Peringatan perangkat baru dan perubahan sensitif aktif.'
                    : 'Peringatan keamanan dinonaktifkan oleh pengguna.',
                $request,
                $enabled ? 'success' : 'warning'
            );
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
            $user->refresh();
        }

        return [
            'biometric_enabled' => (bool) ($user->getAttribute('biometric_enabled') ?? false),
            'security_alerts_enabled' => (bool) ($user->getAttribute('security_alerts_enabled') ?? true),
        ];
    }

    public function attachTokenMetadata(mixed $token, ?Request $request = null): void
    {
        if (! $token instanceof PersonalAccessToken || ! $request instanceof Request) {
            return;
        }

        if (! Schema::hasColumn('personal_access_tokens', 'device_name')) {
            return;
        }

        $headerDeviceName = trim((string) $request->header('X-Device-Name', $request->input('device_name', '')));
        $headerDeviceId = trim((string) $request->header('X-Device-Id', $request->input('device_id', '')));
        $headerPlatform = trim((string) $request->header('X-Device-Platform', $request->input('platform', '')));
        $headerAppVersion = trim((string) $request->header('X-App-Version', $request->input('app_version', '')));
        $userAgent = mb_substr(trim((string) $request->userAgent()), 0, 250);
        $ip = $request->ip();

        $updates = [];

        if ($headerDeviceName !== '') {
            $updates['device_name'] = mb_substr($headerDeviceName, 0, 120);
        } elseif (empty($token->getAttribute('device_name'))) {
            $updates['device_name'] = $this->inferDeviceName($token->name, $userAgent);
        }

        if ($headerDeviceId !== '') {
            $updates['device_id'] = mb_substr($headerDeviceId, 0, 120);
        }

        if ($headerPlatform !== '') {
            $updates['platform'] = mb_substr($headerPlatform, 0, 40);
        } elseif (empty($token->getAttribute('platform'))) {
            $updates['platform'] = $this->inferPlatform($userAgent);
        }

        if ($headerAppVersion !== '') {
            $updates['app_version'] = mb_substr($headerAppVersion, 0, 40);
        }

        if ($ip) {
            $updates['ip_address'] = $ip;
        }

        if ($userAgent !== '') {
            $updates['user_agent'] = $userAgent;
        }

        if ($token->getAttribute('is_trusted') === null) {
            $updates['is_trusted'] = true;
        }

        if ($updates !== []) {
            $token->forceFill($updates)->save();
        }
    }

    public function listDevices(User $user, mixed $currentToken): array
    {
        $currentId = $currentToken instanceof PersonalAccessToken ? $currentToken->id : null;

        $tokens = $user->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get();

        $mapped = $tokens->map(function (PersonalAccessToken $token) use ($currentId) {
            $isCurrent = $currentId !== null && (int) $token->id === (int) $currentId;
            $lastUsed = $token->last_used_at ?? $token->created_at;
            $deviceName = trim((string) $token->getAttribute('device_name'));

            if ($deviceName === '') {
                $deviceName = $this->inferDeviceName($token->name, (string) $token->getAttribute('user_agent'));
            }

            $platform = trim((string) $token->getAttribute('platform'));
            if ($platform === '') {
                $platform = $this->inferPlatform((string) $token->getAttribute('user_agent'));
            }

            return [
                'id' => $token->id,
                'token_name' => $token->name,
                'device_name' => $deviceName,
                'device_type' => str_contains(strtolower($deviceName), 'tab') || str_contains(strtolower($deviceName), 'pad')
                    ? 'tablet'
                    : 'mobile',
                'platform' => $platform,
                'app_version' => $token->getAttribute('app_version') ?: 'City Courier App',
                'ip_address' => $token->getAttribute('ip_address'),
                'location_label' => $token->getAttribute('location_label') ?: ($isCurrent ? 'Sesi Aktif Ini' : 'Indonesia'),
                'is_current' => $isCurrent,
                'is_trusted' => (bool) ($token->getAttribute('is_trusted') ?? true),
                'last_used_at' => $lastUsed?->toIso8601String(),
                'last_used_label' => $isCurrent
                    ? 'Aktif sekarang'
                    : ($lastUsed ? $this->humanTimeAgoId($lastUsed) : 'Baru login'),
                'created_at' => $token->created_at?->toIso8601String(),
            ];
        })->values()->all();

        usort($mapped, function (array $a, array $b) {
            if ($a['is_current'] !== $b['is_current']) {
                return $a['is_current'] ? -1 : 1;
            }

            return strcmp((string) ($b['last_used_at'] ?? ''), (string) ($a['last_used_at'] ?? ''));
        });

        return $mapped;
    }

    public function revokeDevice(User $user, int $tokenId, mixed $currentToken, ?Request $request = null): array
    {
        $currentId = $currentToken instanceof PersonalAccessToken ? $currentToken->id : null;

        if ($currentId !== null && $tokenId === (int) $currentId) {
            return [
                'ok' => false,
                'status_code' => 422,
                'code' => 'CANNOT_REVOKE_CURRENT_DEVICE',
                'message' => 'Tidak dapat menghapus sesi perangkat yang sedang Anda gunakan. Gunakan tombol Keluar jika ingin logout.',
            ];
        }

        $token = $user->tokens()->whereKey($tokenId)->first();
        if (! $token) {
            return [
                'ok' => false,
                'status_code' => 404,
                'code' => 'DEVICE_NOT_FOUND',
                'message' => 'Perangkat tidak ditemukan atau sudah keluar.',
            ];
        }

        $deviceName = $token->getAttribute('device_name') ?: $this->inferDeviceName($token->name, (string) $token->getAttribute('user_agent'));

        DB::transaction(function () use ($token, $user, $deviceName, $request) {
            $tokenIdDeleted = $token->id;
            $token->delete();

            $this->logEvent(
                $user,
                'device_revoked',
                'Perangkat Tepercaya Dihapus',
                "Akses sesi perangkat '{$deviceName}' telah dicabut.",
                $request,
                'warning',
                ['revoked_token_id' => $tokenIdDeleted, 'device_name' => $deviceName]
            );
        });

        return [
            'ok' => true,
            'message' => 'Perangkat berhasil dihapus dari sesi tepercaya.',
        ];
    }

    public function revokeOtherDevices(User $user, mixed $currentToken, ?Request $request = null): array
    {
        $currentId = $currentToken instanceof PersonalAccessToken ? $currentToken->id : null;

        $query = $user->tokens();
        if ($currentId !== null) {
            $query->where('id', '!=', $currentId);
        }

        $revokedCount = (int) $query->count();

        DB::transaction(function () use ($query, $user, $revokedCount, $request) {
            $query->delete();

            $this->logEvent(
                $user,
                'revoked_other_devices',
                'Keluar dari Semua Perangkat Lain',
                $revokedCount > 0
                    ? "{$revokedCount} sesi login di perangkat lain berhasil diakhiri."
                    : 'Tidak ada sesi aktif di perangkat lain yang perlu diakhiri.',
                $request,
                $revokedCount > 0 ? 'warning' : 'success',
                ['revoked_count' => $revokedCount]
            );
        });

        return [
            'ok' => true,
            'revoked_count' => $revokedCount,
            'message' => $revokedCount > 0
                ? 'Semua sesi di perangkat lain telah berhasil diakhiri.'
                : 'Hanya perangkat ini yang sedang aktif saat ini.',
        ];
    }

    public function recentLogs(User $user, int $limit = 20): array
    {
        if (! Schema::hasTable('account_security_logs')) {
            return [];
        }

        return AccountSecurityLog::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(max(1, min(50, $limit)))
            ->get()
            ->map(fn (AccountSecurityLog $log) => $log->toApiArray())
            ->values()
            ->all();
    }

    public function logEvent(
        User $user,
        string $eventType,
        string $title,
        ?string $description = null,
        ?Request $request = null,
        string $status = 'success',
        array $metadata = [],
        ?int $tokenId = null,
    ): ?AccountSecurityLog {
        if (! Schema::hasTable('account_security_logs')) {
            return null;
        }

        $deviceName = null;
        $ipAddress = null;

        if ($request instanceof Request) {
            $deviceName = trim((string) $request->header('X-Device-Name', $request->input('device_name', '')));
            if ($deviceName === '') {
                $deviceName = $this->inferDeviceName('app', (string) $request->userAgent());
            }
            $ipAddress = $request->ip();
        }

        return AccountSecurityLog::create([
            'user_id' => $user->id,
            'token_id' => $tokenId,
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'device_name' => $deviceName ? mb_substr($deviceName, 0, 120) : null,
            'ip_address' => $ipAddress,
            'status' => $status,
            'metadata' => $metadata !== [] ? $metadata : null,
        ]);
    }

    private function pinFailKey(int $userId): string
    {
        return "security:pin_fail:{$userId}";
    }

    private function pinLockKey(int $userId): string
    {
        return "security:pin_lock:{$userId}";
    }

    private function inferDeviceName(?string $tokenName, string $userAgent): string
    {
        $ua = strtolower($userAgent);
        if (str_contains($ua, 'dart') || str_contains($ua, 'okhttp') || str_contains($ua, 'android')) {
            return 'Perangkat Android (City Courier)';
        }
        if (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'cfnetwork')) {
            return 'Perangkat iOS (City Courier)';
        }

        return match ($tokenName) {
            'google-auth' => 'Perangkat Login Google',
            'phone-auth', 'flutter-app' => 'Perangkat Seluler OTP',
            default => 'Perangkat City Courier',
        };
    }

    private function inferPlatform(string $userAgent): string
    {
        $ua = strtolower($userAgent);
        if (str_contains($ua, 'android') || str_contains($ua, 'okhttp') || str_contains($ua, 'dart')) {
            return 'Android';
        }
        if (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ios')) {
            return 'iOS';
        }

        return 'Mobile';
    }

    private function formatDisplayPhone(?string $phone): ?string
    {
        $normalized = PhoneNumber::normalize($phone);
        if ($normalized === null) {
            return $phone ? trim($phone) : null;
        }

        $body = substr($normalized, 1); // buang '0' depan
        $chunks = str_split($body, 4);

        return '+62 ' . implode('-', $chunks);
    }

    private function humanTimeAgoId(CarbonInterface $time): string
    {
        $seconds = max(0, now()->getTimestamp() - $time->getTimestamp());

        if ($seconds < 60) {
            return 'baru saja';
        }
        if ($seconds < 3600) {
            $mins = (int) floor($seconds / 60);
            return "{$mins} menit lalu";
        }
        if ($seconds < 86400) {
            $hours = (int) floor($seconds / 3600);
            return "{$hours} jam lalu";
        }
        $days = (int) floor($seconds / 86400);
        if ($days < 30) {
            return "{$days} hari lalu";
        }
        $months = (int) floor($days / 30);
        if ($months < 12) {
            return "{$months} bulan lalu";
        }

        return $time->copy()->timezone('Asia/Jakarta')->format('d M Y');
    }
}
