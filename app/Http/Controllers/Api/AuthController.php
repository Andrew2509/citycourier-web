<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Courier;
use App\Services\PhoneNumber;
use App\Services\PhoneVerificationService;
use App\Services\WhatsAppManager;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Login nomor telepon hanya berlaku untuk akun yang sudah terdaftar
     * melalui Google. Pesan ini dipakai backend dan Flutter.
     */
    public const PHONE_NOT_REGISTERED_CODE = 'PHONE_NOT_REGISTERED';

    public const PHONE_NOT_REGISTERED_MESSAGE = 'Nomor telepon belum terdaftar. Silakan gunakan nomor telepon yang sudah terdaftar melalui Google. Jika belum memiliki akun, silakan daftar terlebih dahulu menggunakan Google.';

    public const INVALID_PHONE_CODE = 'INVALID_PHONE';

    public const INVALID_PHONE_MESSAGE = 'Format nomor telepon tidak valid. Gunakan format seperti 081234567890.';

    public const PHONE_TAKEN_CODE = 'PHONE_TAKEN';

    public const PHONE_TAKEN_MESSAGE = 'Nomor telepon sudah digunakan oleh akun lain. Silakan gunakan nomor telepon lain.';

    public const OTP_SENT_MESSAGE = 'Kode OTP telah dikirim ke nomor telepon Anda.';

    public const OTP_VERIFIED_MESSAGE = 'Nomor telepon berhasil diverifikasi.';

    public const OTP_INVALID_CODE = 'OTP_INVALID';

    public const OTP_INVALID_MESSAGE = 'Kode OTP yang Anda masukkan salah. Silakan coba lagi.';

    public const OTP_EXPIRED_CODE = 'OTP_EXPIRED';

    public const OTP_EXPIRED_MESSAGE = 'Kode OTP sudah kedaluwarsa. Silakan minta kode baru.';

    public const OTP_TOO_MANY_CODE = 'OTP_TOO_MANY';

    public const OTP_TOO_MANY_MESSAGE = 'Terlalu banyak percobaan kode OTP. Silakan minta kode baru.';

    public const OTP_COOLDOWN_CODE = 'OTP_COOLDOWN';

    public const EMAIL_MANAGED_CODE = 'EMAIL_MANAGED_BY_GOOGLE';

    public const EMAIL_MANAGED_MESSAGE = 'Email dikelola oleh akun Google dan tidak dapat diubah di sini.';

    protected $whatsappManager;

    public function __construct(
        WhatsAppManager $whatsappManager,
        private readonly PhoneVerificationService $phoneVerification,
    ) {
        $this->whatsappManager = $whatsappManager;
    }

    /**
     * Login and issue Sanctum token.
     * POST /api/login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah.',
            ], 401);
        }

        // Load courier profile
        $user->load('courier');

        // Check if courier is verified
        if ($user->courier && !$user->courier->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda belum diverifikasi oleh admin.',
            ], 403);
        }

        $token = $user->createToken('courier-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'courier' => $user->courier,
                'token' => $token,
            ],
        ]);
    }

    /**
     * Register a new courier.
     * POST /api/register-kurir
     */
    public function registerKurir(Request $request)
    {
        // Try to get user from token if provided
        $user = Auth::guard('sanctum')->user();
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email' . ($user ? '|unique:users,email,' . $user->id : '|unique:users,email'),
            'password' => $user ? 'nullable|string|min:8|confirmed' : 'required|string|min:8|confirmed',
            'nik' => 'required|string|size:16',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'city' => 'required|string',
            'vehicle_type' => 'required|in:motor,mobil,pickup,box,truck,sepeda',
            'vehicle_brand' => 'required|string',
            'vehicle_year' => 'required|string|size:4',
            'vehicle_plate' => 'required|string|max:20',
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:4096',
            'id_card_photo' => 'required|image|mimes:jpg,jpeg,png|max:4096',
            'driving_license_photo' => 'required|image|mimes:jpg,jpeg,png|max:4096',
            'skck_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!$user) {
            // Create new user if guest
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'courier',
            ]);
        } else {
            /** @var \App\Models\User $user */
            // Update existing user role
            $user->update(['role' => 'courier']);
            if ($request->password) {
                $user->update(['password' => Hash::make($request->password)]);
            }
        }

        // Handle file uploads
        $photoPath = $request->file('photo')->store('couriers/photos', 'public');
        $idCardPath = $request->file('id_card_photo')->store('couriers/documents', 'public');
        $licensePath = $request->file('driving_license_photo')->store('couriers/documents', 'public');
        $skckPath = $request->hasFile('skck_photo')
            ? $request->file('skck_photo')->store('couriers/documents', 'public')
            : null;

        // Create or update courier profile
        $courier = Courier::updateOrCreate(
            ['user_id' => $user->id],
            [
                'nik' => $request->nik,
                'phone' => $request->phone,
                'address' => $request->address,
                'city' => $request->city,
                'photo' => $photoPath,
                'vehicle_type' => $request->vehicle_type,
                'vehicle_brand' => $request->vehicle_brand,
                'vehicle_year' => $request->vehicle_year,
                'vehicle_plate' => $request->vehicle_plate,
                'id_card_photo' => $idCardPath,
                'driving_license_photo' => $licensePath,
                'skck_photo' => $skckPath,
                'is_verified' => false,
                'is_active' => false,
            ]
        );

        $token = $user->createToken('courier-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil. Menunggu verifikasi admin.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'courier' => $courier,
                'token' => $token,
            ],
        ], 201);
    }

    /**
     * Logout (revoke current token).
     * POST /api/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * Get authenticated user profile.
     * GET /api/profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        $user->load('courier');

        return response()->json([
            'success' => true,
            'data' => array_merge([
                'user' => $this->userPayload($user),
                'courier' => $user->courier,
            ], $this->profileFlags($user)),
        ]);
    }

    /**
     * Request OTP using phone number.
     * POST /api/request-otp
     */
    public function requestOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = PhoneNumber::normalize($request->phone);

        if ($phone === null) {
            return response()->json([
                'success' => false,
                'code' => self::INVALID_PHONE_CODE,
                'message' => self::INVALID_PHONE_MESSAGE,
            ], 422);
        }

        // Login nomor telepon bukan pendaftaran. Nomor harus sudah dimiliki
        // oleh akun yang terdaftar melalui Google.
        if (!$this->findGoogleUserByPhone($phone)) {
            return $this->phoneNotRegisteredResponse();
        }

        $send = $this->phoneVerification->send(
            PhoneVerificationService::PURPOSE_LOGIN,
            $phone,
            4,
        );

        if ($send['status'] === PhoneVerificationService::STATUS_COOLDOWN) {
            return $this->otpCooldownResponse((int) ($send['retry_after'] ?? 60));
        }

        if ($send['status'] !== PhoneVerificationService::STATUS_SENT) {
            Log::warning('Gagal mengirim OTP login WhatsApp', [
                'phone' => $phone,
                'provider_message' => $send['provider_message'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'code' => 'OTP_SEND_FAILED',
                'message' => 'Gagal mengirim kode OTP. Silakan coba lagi beberapa saat lagi.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => self::OTP_SENT_MESSAGE,
            'retry_after' => $send['retry_after'] ?? PhoneVerificationService::COOLDOWN_SECONDS,
            'expires_in' => PhoneVerificationService::EXPIRY_MINUTES * 60,
        ]);
    }

    /**
     * Verify OTP and Login.
     * POST /api/verify-otp
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'otp' => 'required|string|size:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = PhoneNumber::normalize($request->phone);

        if ($phone === null) {
            return response()->json([
                'success' => false,
                'code' => self::INVALID_PHONE_CODE,
                'message' => self::INVALID_PHONE_MESSAGE,
            ], 422);
        }

        $verification = $this->phoneVerification->verify(
            PhoneVerificationService::PURPOSE_LOGIN,
            $phone,
            (string) $request->otp,
        );

        if ($verification['status'] !== PhoneVerificationService::STATUS_OK) {
            return $this->otpErrorResponse($verification);
        }

        // OTP valid, tetapi login tetap hanya boleh untuk akun yang sudah
        // terdaftar via Google. Jangan pernah membuat akun baru di sini.
        $user = $this->findGoogleUserByPhone($phone);

        if (!$user) {
            return $this->phoneNotRegisteredResponse();
        }

        // OTP membuktikan kepemilikan nomor yang memang sudah tersimpan pada
        // akun Google ini. Status verifikasi boleh diisi, tetapi nomor dan
        // identitas Google tidak diubah.
        if (!$user->hasVerifiedPhone()) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        // Load courier profile if exists
        $user->load('courier');

        $token = $user->createToken('flutter-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => array_merge([
                'user' => $this->userPayload($user),
                'courier' => $user->courier,
                'token' => $token,
            ], $this->profileFlags($user)),
        ]);
    }

    /**
     * Verify Firebase ID Token and Login/Register with Google.
     * POST /api/auth/google
     */
    public function loginWithGoogle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $idToken = $request->id_token;

        try {
            $verifiedIdToken = app('firebase.auth')->verifyIdToken($idToken);
            $firebaseUid = $verifiedIdToken->claims()->get('sub');
            $email = $verifiedIdToken->claims()->get('email');

            if (!$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Google tidak memiliki email yang dapat digunakan.',
                ], 422);
            }

            $email = strtolower(trim((string) $email));
            $firebaseUid = (string) $firebaseUid;
            $name = trim((string) ($verifiedIdToken->claims()->get('name') ?? ''));
            $avatar = $verifiedIdToken->claims()->get('picture');

            // Satu akun Google hanya boleh memiliki satu baris pengguna.
            // Cocokkan dulu lewat UID Firebase, lalu lewat email Google.
            $user = User::where('firebase_uid', $firebaseUid)->first()
                ?? User::where('email', $email)->first();

            if ($user) {
                $updates = [];

                // Akun lama yang belum punya UID Google dihubungkan, bukan
                // diduplikasi.
                if ((string) $user->firebase_uid !== $firebaseUid) {
                    $updates['firebase_uid'] = $firebaseUid;
                }

                // Email hasil generate sistem diganti dengan email Google asli.
                if (!$user->hasRealEmail()) {
                    $updates['email'] = $email;
                }

                $currentName = trim((string) $user->name);
                if ($name !== '' && ($currentName === '' || str_starts_with($currentName, 'User ') || $currentName === 'Google User')) {
                    $updates['name'] = $name;
                }

                // Foto Google hanya dipakai bila belum ada foto atau foto lama
                // masih berupa URL Google. Foto unggahan pengguna dipertahankan.
                $currentAvatar = trim((string) $user->avatar);
                if (is_string($avatar) && $avatar !== ''
                    && ($currentAvatar === '' || filter_var($currentAvatar, FILTER_VALIDATE_URL))) {
                    $updates['avatar'] = $avatar;
                }

                if ($updates !== []) {
                    $user->update($updates);
                }
            } else {
                try {
                    $user = User::create([
                        'name' => $name !== '' ? $name : 'Google User',
                        'email' => $email,
                        'firebase_uid' => $firebaseUid,
                        'avatar' => is_string($avatar) ? $avatar : null,
                        'password' => Hash::make(Str::random(32)), // Required field in migration
                        'role' => 'customer',
                    ]);
                } catch (QueryException $e) {
                    // Antisipasi dua permintaan login bersamaan: pakai baris
                    // yang sudah dibuat permintaan pertama.
                    $user = User::where('firebase_uid', $firebaseUid)
                        ->orWhere('email', $email)
                        ->first();

                    if (!$user) {
                        throw $e;
                    }
                }

                // Assign customer role (spatie) bila role tersedia.
                try {
                    $user->assignRole('customer');
                } catch (\Throwable $e) {
                    Log::warning('Role customer tidak dapat diberikan saat registrasi Google', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $token = $user->createToken('google-auth')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login Google berhasil.',
                'data' => array_merge([
                    'user' => $this->userPayload($user),
                    'token' => $token,
                ], $this->profileFlags($user)),
            ]);

        } catch (\Exception $e) {
            // Detail teknis hanya masuk log, tidak dikirim ke klien.
            Log::warning('Verifikasi token Firebase Google gagal', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token Firebase tidak valid.',
            ], 401);
        }
    }

    /**
     * Verify Firebase ID Token and Login/Register with Phone Number.
     * POST /api/auth/phone
     */
    public function loginWithPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $idToken = $request->id_token;

        try {
            $verifiedIdToken = app('firebase.auth')->verifyIdToken($idToken);
            $firebaseUid = $verifiedIdToken->claims()->get('sub');
            $phone = $verifiedIdToken->claims()->get('phone_number');

            if (!$phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor HP tidak ditemukan dalam token Firebase.',
                ], 400);
            }

            // Cari berdasarkan nomor (akun Google yang sudah menyimpan nomor)
            // atau UID Firebase yang sama. Jangan pernah membuat akun baru.
            $user = $this->findGoogleUserByPhone($phone)
                ?? User::where('firebase_uid', $firebaseUid)->first();

            if (!$user || !$user->hasGoogleAuth()) {
                return $this->phoneNotRegisteredResponse();
            }

            // Jangan mengubah phone/firebase_uid di sini. Login nomor telepon
            // tidak boleh mengubah identitas akun Google tanpa verifikasi.
            $token = $user->createToken('phone-auth')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login nomor HP berhasil.',
                'data' => array_merge([
                    'user' => $this->userPayload($user),
                    'token' => $token,
                ], $this->profileFlags($user)),
            ]);

        } catch (\Exception $e) {
            // Detail teknis hanya masuk log, tidak dikirim ke klien.
            Log::warning('Verifikasi token Firebase nomor HP gagal', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token Firebase tidak valid.',
            ], 401);
        }
    }

    /**
     * Request OTP for courier phone verification (6 digits).
     * POST /api/courier/request-phone-otp
     */
    public function requestCourierPhoneOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $request->phone;

        // Generate 6-digit OTP for courier registration
        $otp = (string) random_int(100000, 999999);

        // Store in cache for 10 minutes (different key from login OTP)
        Cache::put('courier_phone_otp_' . $phone, $otp, now()->addMinutes(10));

        // Send OTP via WhatsApp
        $message = "Kode verifikasi pendaftaran kurir City Courier Anda adalah: *{$otp}*\n\nJangan sebarkan kode ini kepada siapapun.\n\n_Pesan ini dikirim otomatis oleh sistem City Courier._";

        $waResult = $this->whatsappManager->sendMessage($phone, $message);

        if (!$waResult['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim OTP via WhatsApp. ' . ($waResult['message'] ?? ''),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kode verifikasi berhasil dikirim ke WhatsApp.',
        ]);
    }

    /**
     * Verify OTP for courier phone verification.
     * POST /api/courier/verify-phone-otp
     */
    public function verifyCourierPhoneOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $request->phone;
        $otp = $request->otp;

        $cachedOtp = Cache::get('courier_phone_otp_' . $phone);

        if (!$cachedOtp || $cachedOtp !== $otp) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP salah atau telah kadaluarsa.',
            ], 401);
        }

        // Clear OTP after successful verification
        Cache::forget('courier_phone_otp_' . $phone);

        return response()->json([
            'success' => true,
            'message' => 'Nomor HP berhasil diverifikasi.',
            'data' => [
                'phone_verified' => true,
            ],
        ]);
    }

    /**
     * Update data profil yang boleh diubah pengguna.
     *
     * Nomor telepon TIDAK diubah di sini: nomor hanya boleh disimpan setelah
     * OTP diverifikasi lewat endpoint /profile/phone/*. Email akun Google juga
     * tidak dapat diganti dari aplikasi.
     * POST /api/profile/update
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = [
            'name' => trim((string) $request->name),
            'address' => $request->address,
        ];

        if ($request->filled('email')) {
            $email = strtolower(trim((string) $request->email));

            if ($user->hasGoogleAuth() && $email !== strtolower(trim((string) $user->email))) {
                return response()->json([
                    'success' => false,
                    'code' => self::EMAIL_MANAGED_CODE,
                    'message' => self::EMAIL_MANAGED_MESSAGE,
                ], 422);
            }

            if (!$user->hasGoogleAuth()) {
                $taken = User::where('email', $email)->whereKeyNot($user->id)->exists();

                if ($taken) {
                    return response()->json([
                        'success' => false,
                        'code' => 'EMAIL_TAKEN',
                        'message' => 'Email sudah digunakan oleh akun lain.',
                    ], 422);
                }

                $data['email'] = $email;
            }
        }

        // Catatan: `phone` sengaja tidak pernah disimpan di sini agar status
        // verifikasi tidak bisa dilewati dari aplikasi.

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('photos', 'public');
            $data['avatar'] = $path;
        }

        $user->update($data);
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => array_merge([
                'user' => $this->userPayload($user),
            ], $this->profileFlags($user)),
        ]);
    }

    /**
     * Kirim OTP untuk memverifikasi nomor telepon akun yang sedang login.
     * POST /api/profile/phone/request-otp
     */
    public function requestProfilePhoneOtp(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = PhoneNumber::normalize($request->phone);

        if ($phone === null) {
            return response()->json([
                'success' => false,
                'code' => self::INVALID_PHONE_CODE,
                'message' => self::INVALID_PHONE_MESSAGE,
            ], 422);
        }

        if (PhoneNumber::normalize($user->phone) === $phone && $user->hasVerifiedPhone()) {
            return response()->json([
                'success' => true,
                'message' => self::OTP_VERIFIED_MESSAGE,
                'already_verified' => true,
            ]);
        }

        if ($this->otherUserWithPhone($phone, (int) $user->id) !== null) {
            return $this->phoneTakenResponse();
        }

        $send = $this->phoneVerification->send(
            PhoneVerificationService::PURPOSE_PROFILE,
            $phone,
            6,
            (int) $user->id,
        );

        if ($send['status'] === PhoneVerificationService::STATUS_COOLDOWN) {
            return $this->otpCooldownResponse((int) ($send['retry_after'] ?? 60));
        }

        if ($send['status'] !== PhoneVerificationService::STATUS_SENT) {
            Log::warning('Gagal mengirim OTP verifikasi nomor profil', [
                'user_id' => $user->id,
                'provider_message' => $send['provider_message'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'code' => 'OTP_SEND_FAILED',
                'message' => 'Gagal mengirim kode OTP. Silakan coba lagi beberapa saat lagi.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => self::OTP_SENT_MESSAGE,
            'retry_after' => $send['retry_after'] ?? PhoneVerificationService::COOLDOWN_SECONDS,
            'expires_in' => PhoneVerificationService::EXPIRY_MINUTES * 60,
        ]);
    }

    /**
     * Verifikasi OTP dan simpan nomor telepon ke akun yang sedang login.
     * POST /api/profile/phone/verify-otp
     */
    public function verifyProfilePhoneOtp(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'otp' => 'required|string|min:4|max:8',
            'name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = PhoneNumber::normalize($request->phone);

        if ($phone === null) {
            return response()->json([
                'success' => false,
                'code' => self::INVALID_PHONE_CODE,
                'message' => self::INVALID_PHONE_MESSAGE,
            ], 422);
        }

        if ($this->otherUserWithPhone($phone, (int) $user->id) !== null) {
            return $this->phoneTakenResponse();
        }

        $verification = $this->phoneVerification->verify(
            PhoneVerificationService::PURPOSE_PROFILE,
            $phone,
            (string) $request->otp,
            (int) $user->id,
        );

        if ($verification['status'] !== PhoneVerificationService::STATUS_OK) {
            return $this->otpErrorResponse($verification);
        }

        try {
            DB::transaction(function () use ($user, $phone, $request) {
                if ($this->otherUserWithPhone($phone, (int) $user->id) !== null) {
                    throw new \RuntimeException(self::PHONE_TAKEN_CODE);
                }

                $updates = [
                    'phone' => $phone,
                    'phone_verified_at' => now(),
                ];

                if ($request->filled('name')) {
                    $updates['name'] = trim((string) $request->name);
                }

                if ($request->has('address')) {
                    $updates['address'] = $request->address;
                }

                $user->forceFill($updates)->save();
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === self::PHONE_TAKEN_CODE) {
                return $this->phoneTakenResponse();
            }

            throw $e;
        } catch (QueryException $e) {
            // Unique index pada kolom phone menangkap balapan dua akun yang
            // mencoba memakai nomor yang sama.
            Log::warning('Nomor telepon gagal disimpan karena duplikat', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->phoneTakenResponse();
        }

        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => self::OTP_VERIFIED_MESSAGE,
            'data' => array_merge([
                'user' => $this->userPayload($user),
                'phone_verified' => true,
            ], $this->profileFlags($user)),
        ]);
    }

    /**
     * Cari akun Google berdasarkan nomor telepon.
     *
     * Nomor dicocokkan setelah dinormalisasi (`08…`, `62…`, `+62…`) dan
     * akun harus benar-benar memiliki autentikasi Google. Nomor yang hanya
     * tersimpan di profil tanpa akun Google tidak dianggap terdaftar.
     */
    private function findGoogleUserByPhone(?string $phone): ?User
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            return null;
        }

        $variants = PhoneNumber::variants($normalized);

        if ($variants === []) {
            return null;
        }

        $users = User::query()
            ->whereIn('phone', $variants)
            ->get();

        // Toleransi data lama yang menyimpan spasi/tanda hubung, mis.
        // "0812-3456-7890" atau "+62 812 3456 7890".
        if ($users->isEmpty()) {
            $users = User::query()
                ->whereNotNull('phone')
                ->whereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '.', '') IN (?, ?, ?)",
                    $variants
                )
                ->get();
        }

        foreach ($users as $user) {
            if (PhoneNumber::normalize($user->phone) === $normalized && $user->hasGoogleAuth()) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Cari akun lain (selain pemilik saat ini) yang sudah memakai nomor ini.
     * Dipakai agar dua akun tidak pernah berbagi nomor yang sama.
     */
    private function otherUserWithPhone(string $phone, int $exceptUserId): ?User
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            return null;
        }

        $variants = PhoneNumber::variants($normalized);

        if ($variants === []) {
            return null;
        }

        $users = User::query()
            ->whereKeyNot($exceptUserId)
            ->whereIn('phone', $variants)
            ->get();

        if ($users->isEmpty()) {
            $users = User::query()
                ->whereKeyNot($exceptUserId)
                ->whereNotNull('phone')
                ->whereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '.', '') IN (?, ?, ?)",
                    $variants
                )
                ->get();
        }

        foreach ($users as $user) {
            if (PhoneNumber::normalize($user->phone) === $normalized) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Bentuk data pengguna yang konsisten untuk seluruh respons autentikasi.
     *
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'phone_verified' => $user->hasVerifiedPhone(),
            'phone_verified_at' => $user->phone_verified_at?->toISOString(),
            'address' => $user->address,
            'photo_url' => $user->photo_url,
            'role' => $user->role,
            'registration_method' => $user->registrationMethod(),
            'account_status' => $user->accountStatus(),
        ];
    }

    /**
     * Status kelengkapan profil yang menjadi acuan Flutter dan admin.
     *
     * @return array<string, mixed>
     */
    private function profileFlags(User $user): array
    {
        $missing = $user->missingProfileFields();

        return [
            'profile_completed' => $missing === [],
            'requires_profile_completion' => $missing !== [],
            'missing_profile_fields' => $missing,
            'phone_verified' => $user->hasVerifiedPhone(),
        ];
    }

    private function phoneTakenResponse()
    {
        return response()->json([
            'success' => false,
            'code' => self::PHONE_TAKEN_CODE,
            'message' => self::PHONE_TAKEN_MESSAGE,
        ], 422);
    }

    private function otpCooldownResponse(int $seconds)
    {
        $seconds = max(1, $seconds);

        return response()->json([
            'success' => false,
            'code' => self::OTP_COOLDOWN_CODE,
            'message' => "Tunggu {$seconds} detik sebelum meminta kode OTP baru.",
            'retry_after' => $seconds,
        ], 429);
    }

    /**
     * @param array{status?: string, attempts_left?: int} $result
     */
    private function otpErrorResponse(array $result)
    {
        $status = $result['status'] ?? PhoneVerificationService::STATUS_INVALID;

        if ($status === PhoneVerificationService::STATUS_EXPIRED) {
            return response()->json([
                'success' => false,
                'code' => self::OTP_EXPIRED_CODE,
                'message' => self::OTP_EXPIRED_MESSAGE,
            ], 422);
        }

        if ($status === PhoneVerificationService::STATUS_TOO_MANY) {
            return response()->json([
                'success' => false,
                'code' => self::OTP_TOO_MANY_CODE,
                'message' => self::OTP_TOO_MANY_MESSAGE,
            ], 422);
        }

        return response()->json([
            'success' => false,
            'code' => self::OTP_INVALID_CODE,
            'message' => self::OTP_INVALID_MESSAGE,
            'attempts_left' => $result['attempts_left'] ?? null,
        ], 422);
    }

    private function phoneNotRegisteredResponse()
    {
        return response()->json([
            'success' => false,
            'code' => self::PHONE_NOT_REGISTERED_CODE,
            'message' => self::PHONE_NOT_REGISTERED_MESSAGE,
        ], 404);
    }
}
