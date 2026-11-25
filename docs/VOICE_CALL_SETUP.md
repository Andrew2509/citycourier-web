# City Courier In-App Voice Call (VoIP WebRTC) Setup & Operations Guide

Dokumen ini menjelaskan arsitektur, skema database, endpoint API, konfigurasi STUN/TURN, signaling realtime, notifikasi panggilan masuk (FCM + Android full-screen/foreground service), serta prosedur deployment untuk fitur panggilan suara langsung antar Customer dan Courier di dalam aplikasi City Courier.

## 1. Arsitektur VoIP

- **Media Audio Realtime (Peer-to-Peer):** Menggunakan `flutter_webrtc` (Opus audio) antar perangkat Customer dan Courier tanpa mengarahkan pengguna ke WhatsApp atau aplikasi dialer eksternal. Server Laravel **tidak** memproses maupun merekam stream audio.
- **Autentikasi & Otorisasi:** Setiap inisiasi, penerimaan, penolakan, pengakhiran, pengambilan status, dan pertukaran sinyal diautentikasi via Laravel Sanctum (`auth:sanctum`) dan diverifikasi terhadap partisipan `conversations` aktif serta resolusi order/shipment (`OrderParticipantResolver`, `CallSessionPolicy`). Pihak ketiga non-peserta atau pesanan yang dibatalkan/berpindah kurir ditolak dengan `403`/`409`.
- **Signaling Dual-Transport:**
  1. **WebSocket Realtime (`ShouldBroadcastNow`):** Melalui private channel `private-user.{userId}` (`IncomingCallBroadcast` / `call.incoming`) dan `private-call.{callId}` (`CallSessionUpdatedBroadcast` / `call.updated`, `CallSignalBroadcast` / `call.signal`) via Laravel Reverb/Pusher ketika `BROADCAST_CONNECTION=reverb`.
  2. **REST Cursor Fallback:** Endpoint `/api/v1/calls/{callSession}/signals?after_id=...` melakukan persistensi SDP (`offer`/`answer`) dan `ice_candidate` sementara di MySQL (`call_signals`) agar negosiasi tetap berhasil walaupun koneksi WebSocket belum aktif atau terputus sesaat.
- **Notifikasi Background & Layar Terkunci:** Menggunakan `FcmPushService` (`SendCallPushNotificationJob`) dengan payload **data-only Android high priority** (`type=incoming_call` dan `type=call_cancelled`, `ttl=45s`). Flutter menanganinya pada foreground (`FirebaseMessaging.onMessage`) maupun background/terminated (`firebaseMessagingBackgroundHandler`) menggunakan Android high-importance call notification dengan `fullScreenIntent: true`, aksi `Terima` / `Tolak`, nada dering + getar via `MainActivity` (`MethodChannel` `city_courier/voice_call_native`), serta `VoiceCallForegroundService` (`foregroundServiceType="microphone"`).
- **STUN/TURN Dinamis & Aman:** Kredensial TURN **tidak pernah** ditanam di aplikasi Flutter. Backend menerbitkan kredensial TURN ephemeral (`HMAC-SHA1`, TTL default 3600 detik) melalui `IceServerConfigProvider` hanya untuk partisipan panggilan yang terautentikasi pada respons `/calls/incoming`, `/calls/initiate`, `/calls/{callSession}`, dan `/calls/{callSession}/accept`.

## 2. Database & Migration

Jalankan migrasi di server database MySQL:

```bash
php artisan migrate
```

Tabel yang ditambahkan:

1. `call_sessions` (`database/migrations/2026_11_25_000006_create_call_sessions_table.php`):
   - `id`, `call_uuid` (unique), `order_id`, `conversation_id` (FK cascade), `caller_id`, `receiver_id`, `status`, `end_reason`, `started_at`, `answered_at`, `ended_at`, `duration` (detik), `expires_at`, `created_at`, `updated_at`.
   - Nilai `status`: `ringing`, `accepted`, `rejected`, `missed`, `ended`, `failed`.
2. `call_signals` (`database/migrations/2026_11_25_000007_create_call_signals_table.php`):
   - `id`, `call_session_id` (FK cascade), `sender_id`, `receiver_id`, `signal_type` (`offer`, `answer`, `ice_candidate`), `payload`, `created_at`.

## 3. Endpoint API (`/api/v1/calls`)

Semua endpoint berada di `routes/chat_api.php` dengan middleware `auth:sanctum`:

| Method | Endpoint | Route Name | Fungsi |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/calls/incoming` | `citycourier.calls.incoming` | Mengambil panggilan masuk berstatus `ringing` untuk user login + `ice_servers` |
| `POST` | `/api/v1/calls/initiate` | `citycourier.calls.initiate` | Memulai panggilan dari `conversation_id` / `order_identifier` (+ opsional `offer`), kirim broadcast & push |
| `GET` | `/api/v1/calls/{callSession}` | `citycourier.calls.show` | Mengambil status terkini sesi panggilan (+ auto-expire `ringing` kadaluwarsa ke `missed`) |
| `POST` | `/api/v1/calls/{callSession}/accept` | `citycourier.calls.accept` | Receiver menerima panggilan (`status=accepted`), membatasi hanya `receiver_id` |
| `POST` | `/api/v1/calls/{callSession}/reject` | `citycourier.calls.reject` | Receiver menolak panggilan (`status=rejected`) & membatalkan notifikasi |
| `POST` | `/api/v1/calls/{callSession}/end` | `citycourier.calls.end` | Caller atau Receiver mengakhiri panggilan (`ended`/`missed`/`failed`), hitung `duration`, stop notifikasi |
| `GET` | `/api/v1/calls/{callSession}/signals` | `citycourier.calls.signals.index` | Polling cursor `?after_id=0` untuk SDP/ICE dari lawan bicara |
| `POST` | `/api/v1/calls/{callSession}/signals` | `citycourier.calls.signals.store` | Mengirim SDP (`offer`/`answer`) atau `ice_candidate` dan mem-broadcast via `call.signal` |

Private Broadcasting Channels (`routes/chat_channels.php`):
- `private-user.{userId}` — menerima event `call.incoming`
- `private-call.{callId}` — menerima event `call.updated` dan `call.signal`

## 4. Konfigurasi Server (`.env` & Layanan Eksternal)

Variabel di `.env` Laravel (`config/voip.php`, `config/fcm.php`, `config/broadcasting.php`):

```env
# WebRTC & STUN/TURN
VOIP_ENABLED=true
VOIP_RING_TIMEOUT_SECONDS=45
VOIP_MAX_CALL_SECONDS=14400
VOIP_STUN_URLS=stun:stun.l.google.com:19302,stun:stun1.l.google.com:19302
VOIP_TURN_URLS=turn:turn.domain-anda.com:3478?transport=udp,turns:turn.domain-anda.com:5349?transport=tcp
VOIP_TURN_SHARED_SECRET=ganti_dengan_secret_acak_minimal_32_karakter
VOIP_TURN_TTL_SECONDS=3600

# FCM Push (Notifikasi Background & Lock Screen)
FCM_ENABLED=true
FCM_PROJECT_ID=firebase-project-id-anda
FCM_CREDENTIALS_PATH=/path/aman/firebase-service-account.json
FCM_ANDROID_CALL_CHANNEL_ID=city_courier_calls

# Realtime WebSocket (Disarankan untuk respons < 1 detik)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=city-courier-app
REVERB_APP_KEY=isi_app_key
REVERB_APP_SECRET=isi_app_secret
REVERB_HOST=ws.domain-anda.com
REVERB_PORT=443
REVERB_SCHEME=https
```

### Rekomendasi TURN Server (`coturn`)

Pada jaringan seluler (CGNAT/symmetric NAT), STUN saja tidak selalu cukup untuk membangun koneksi P2P. Jalankan `coturn` dengan `use-auth-secret` agar cocok dengan algoritma `IceServerConfigProvider`:

```ini
listening-port=3478
tls-listening-port=5349
fingerprint
use-auth-secret
static-auth-secret=sama_persis_dengan_VOIP_TURN_SHARED_SECRET
realm=turn.domain-anda.com
```

## 5. Pengujian Otomatis & Checklist Uji Dua Perangkat

Jalankan suite otomatis setelah perubahan:

- Backend Laravel:
  ```bash
  php vendor/phpunit/phpunit/phpunit --testdox tests/Feature/VoiceCallSystemTest.php tests/Feature/ChatWhatsAppSystemTest.php
  ```
- Frontend Flutter:
  ```bash
  flutter test test/call/voice_call_system_test.dart test/chat/chat_foundation_test.dart test/chat/chat_ui_widget_test.dart
  ```

Checklist uji wajib di **minimal dua perangkat Android fisik** (setelah `php artisan migrate`, FCM, dan STUN/TURN aktif di server):
1. Customer menelepon Courier dari ikon telepon header chat -> Courier menerima & mendengar audio dua arah.
2. Courier menelepon Customer -> Customer menerima & mendengar audio dua arah.
3. Courier/Customer menekan **Tolak** panggilan masuk.
4. Kedua pihak menekan **Akhiri** panggilan dan memverifikasi durasi tersimpan di `call_sessions`.
5. Tombol **Mikrofon** (mute/unmute) & **Speaker** berfungsi pada perangkat nyata.
6. Panggilan tidak dijawab melebihi `VOIP_RING_TIMEOUT_SECONDS` (berubah ke `missed` + cancel notification).
7. Koneksi internet salah satu pihak terputus saat panggilan aktif (grace 10 detik -> `failed` dan cleanup).
8. Aplikasi di background atau layar terkunci saat panggilan masuk (notifikasi full-screen/aksi Terima & Tolak muncul dengan nada dering).
9. Izin mikrofon ditolak -> panggilan dicegah dengan pesan error jelas tanpa crash.
10. Pengguna non-peserta mencoba akses sesi panggilan -> ditolak HTTP `403`.
