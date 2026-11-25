# Panduan Setup Chat Realtime & WhatsApp Business Cloud API (`City-Courier-web`)

Modul `App\Modules\Chat` dan `App\Modules\WhatsApp` terintegrasi langsung ke aplikasi Laravel utama via `App\Modules\Chat\ChatServiceProvider` di `bootstrap/providers.php`.

## 1. Struktur File Terpasang

- Modul domain: `app/Modules/Chat/*` dan `app/Modules/WhatsApp/*`
- Konfigurasi: `config/chat.php`, `config/whatsapp.php`, `config/fcm.php`, `config/broadcasting.php` (konfigurasi Meta Cloud API berada di `config/whatsapp.php` terpisah dari `config/services.php` agar tidak bentrok dengan notifikasi Fonnte/OrbitWA)
- Routing & channel: `routes/chat_api.php` (`/api/v1/chat/*`, `/api/v1/whatsapp/webhook`, `/api/v1/devices/fcm-token`) dan `routes/chat_channels.php` (`conversation.{conversationId}`, `user.{userId}`)
- Migrasi database:
  - `2026_11_25_000001_create_conversations_table.php` (`conversations`)
  - `2026_11_25_000002_create_messages_table.php` (`messages`)
  - `2026_11_25_000003_create_whatsapp_contacts_table.php` (`whatsapp_contacts`)
  - `2026_11_25_000004_create_whatsapp_message_logs_table.php` (`whatsapp_message_logs`)
  - `2026_11_25_000005_create_user_device_tokens_table.php` (`user_device_tokens`)

## 2. Migrasi Database

```bash
php artisan migrate
```

## 3. Konfigurasi `.env`

1. **Realtime Broadcasting (Reverb / Pusher Protocol)**:
   - Default lokal aman menggunakan `BROADCAST_CONNECTION=log` (chat internal tetap tersimpan tanpa error koneksi WebSocket).
   - Untuk mengaktifkan realtime WebSocket produksi: atur `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST=ws.neonflux.my.id`, `REVERB_PORT=443`, `REVERB_SCHEME=https`.
   - `pusher/pusher-php-server` sudah terpasang untuk mengirim event broadcast ke server Reverb/Pusher. Bila daemon WebSocket Reverb dijalankan langsung dari repo ini (bukan container/host terpisah), pasang paket server `composer require laravel/reverb` dan jalankan `php artisan reverb:start`.
2. **Meta WhatsApp Business Cloud API (Resmi)**:
   - Biarkan `WHATSAPP_CLOUD_ENABLED=false` hingga kredensial Meta siap (chat internal tetap berfungsi penuh).
   - Saat siap live: isi `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_WABA_ID`, `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN`.
   - Buat Utility Template di Meta WhatsApp Manager dengan nama sesuai `WHATSAPP_UTILITY_TEMPLATE_NAME` (`city_courier_chat_notification`, 3 parameter body: nomor order, nama pengirim, pratinjau pesan) untuk dipakai otomatis saat jendela layanan 24 jam customer sudah berakhir.
   - Arahkan Callback Webhook Meta ke `https://www.neonflux.my.id/api/v1/whatsapp/webhook`.
3. **Firebase Cloud Messaging (HTTP v1)**:
   - Aktifkan `FCM_ENABLED=true`, isi `FCM_PROJECT_ID` dan `FCM_CREDENTIALS_PATH`.

## 4. Worker Produksi

Jalankan worker antrean persisten agar job outbound WhatsApp, webhook async, dan FCM berjalan di latar belakang:

```bash
php artisan queue:work --tries=3 --backoff=10,60,180
```

## 5. Pengujian Otomatis

```bash
php artisan test tests/Unit/PhoneNumberNormalizerTest.php tests/Feature/ChatWhatsAppSystemTest.php
```
