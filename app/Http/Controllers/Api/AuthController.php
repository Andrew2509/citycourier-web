<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Courier;
use App\Services\PhoneNumber;
use App\Services\WhatsAppManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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

    protected $whatsappManager;

    public function __construct(WhatsAppManager $whatsappManager)
    {
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
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'photo_url' => $user->photo_url,
                    'role' => $user->role,
                ],
                'courier' => $user->courier,
            ],
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

        // Generate real random 4-digit OTP
        $otp = (string) random_int(1000, 9999);

        // Store in cache for 10 minutes (kunci memakai nomor ternormalisasi)
        Cache::put($this->otpCacheKey($phone), $otp, now()->addMinutes(10));

        // Send OTP via WhatsApp (uses configured provider: Fonnte/OrbitWA/Mock)
        $waResult = $this->whatsappManager->sendOtp($phone, $otp);

        if (!$waResult['success']) {
            Log::warning('Gagal mengirim OTP login WhatsApp', [
                'phone' => $phone,
                'provider_message' => $waResult['message'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'code' => 'OTP_SEND_FAILED',
                'message' => 'Gagal mengirim kode OTP. Silakan coba lagi beberapa saat lagi.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP berhasil dikirim ke WhatsApp.',
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

        $otp = $request->otp;

        $cacheKey = $this->otpCacheKey($phone);
        $cachedOtp = Cache::get($cacheKey);

        // Kompatibilitas OTP yang dikirim sebelum nomor dinormalisasi.
        if (!$cachedOtp) {
            $cacheKey = 'otp_' . $request->phone;
            $cachedOtp = Cache::get($cacheKey);
        }

        if (!$cachedOtp || !hash_equals((string) $cachedOtp, (string) $otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP salah atau telah kadaluarsa.',
            ], 401);
        }

        // Clear OTP
        Cache::forget($cacheKey);
        Cache::forget($this->otpCacheKey($phone));

        // OTP valid, tetapi login tetap hanya boleh untuk akun yang sudah
        // terdaftar via Google. Jangan pernah membuat akun baru di sini.
        $user = $this->findGoogleUserByPhone($phone);

        if (!$user) {
            return $this->phoneNotRegisteredResponse();
        }

        // Load courier profile if exists
        $user->load('courier');

        $token = $user->createToken('flutter-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'address' => $user->address,
                    'photo_url' => $user->photo_url,
                    'role' => $user->role,
                ],
                'courier' => $user->courier,
                'token' => $token,
            ],
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
            $name = $verifiedIdToken->claims()->get('name') ?? 'Google User';
            $avatar = $verifiedIdToken->claims()->get('picture');

            // Find user by firebase_uid or email
            $user = User::where('firebase_uid', $firebaseUid)
                ->orWhere('email', $email)
                ->first();

            if ($user) {
                // Update existing user with firebase info if not set
                $user->update([
                    'firebase_uid' => $firebaseUid,
                    'avatar' => $avatar,
                ]);
            } else {
                // Register new user
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'firebase_uid' => $firebaseUid,
                    'avatar' => $avatar,
                    'password' => Hash::make(Str::random(16)), // Required field in migration
                    'role' => 'customer',
                ]);

                // Assign customer role (spatie)
                $user->assignRole('customer');
            }

            $token = $user->createToken('google-auth')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login Google berhasil.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'address' => $user->address,
                        'photo_url' => $user->photo_url,
                        'role' => $user->role,
                    ],
                    'token' => $token,
                ],
            ]);

        } catch (\Exception $e) {
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
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'email' => $user->email,
                        'address' => $user->address,
                        'photo_url' => $user->photo_url,
                        'role' => $user->role,
                    ],
                    'token' => $token,
                ],
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
     * Update user profile (name and email).
     * POST /api/profile/update
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
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
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
        ];

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('photos', 'public');
            $data['avatar'] = $path;
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'photo_url' => $user->photo_url,
                ]
            ]
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

    private function otpCacheKey(string $normalizedPhone): string
    {
        return 'otp_' . $normalizedPhone;
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
