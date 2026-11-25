<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Modules\Chat\Events\ConversationUpdatedBroadcast;
use App\Modules\Chat\Events\MessageSentBroadcast;
use App\Modules\Chat\Events\MessageStatusUpdatedBroadcast;
use App\Modules\Chat\Events\UserTypingBroadcast;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Models\UserDeviceToken;
use App\Modules\Chat\Models\WhatsappContact;
use App\Modules\Chat\Models\WhatsappMessageLog;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesPaymentWalletSchema;

class ChatWhatsAppSystemTest extends TestCase
{
    use CreatesPaymentWalletSchema;

    private User $customer;
    private User $courierUser;
    private User $stranger;
    private Courier $courier;
    private Shipment $shipment;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBaseTablesForWalletTests();
        if (! Schema::hasTable('permissions')) {
            (require database_path('migrations/2026_04_17_071227_create_permission_tables.php'))->up();
        }
        foreach (glob(database_path('migrations/2026_11_25_00000*.php')) ?: [] as $file) {
            $migration = require $file;
            $migration->up();
        }

        $this->customer = User::create([
            'name' => 'Ayu Customer',
            'email' => 'ayu@example.com',
            'phone' => '081211112222',
            'phone_verified_at' => now(),
            'password' => 'secret123',
        ]);

        $this->courierUser = User::create([
            'name' => 'Bima Kurir',
            'email' => 'bima@example.com',
            'phone' => '081322223333',
            'phone_verified_at' => now(),
            'password' => 'secret123',
        ]);

        $this->stranger = User::create([
            'name' => 'User Lain',
            'email' => 'lain@example.com',
            'phone' => '081999998888',
            'phone_verified_at' => null,
            'password' => 'secret123',
        ]);

        $this->courier = Courier::create([
            'user_id' => $this->courierUser->id,
            'phone' => '081322223333',
            'vehicle_type' => 'motor',
            'is_verified' => true,
            'is_active' => true,
        ]);

        $this->shipment = Shipment::create([
            'shipment_number' => 'CC-20261125-001',
            'tracking_number' => 'RESI-CC-001',
            'user_id' => $this->customer->id,
            'customer_name' => 'Ayu Customer',
            'customer_phone' => '081211112222',
            'sender_name' => 'Ayu Customer',
            'sender_phone' => '081211112222',
            'sender_address' => 'Jl. Melati No. 1',
            'receiver_name' => 'Tio Penerima',
            'receiver_phone' => '081455556666',
            'receiver_address' => 'Jl. Kenanga No. 9',
            'package_weight' => 1.5,
            'total_cost' => 18000,
            'status' => 'delivering',
        ]);

        $this->order = Order::create([
            'order_number' => 'CC-20261125-001',
            'courier_id' => $this->courier->id,
            'customer_name' => 'Ayu Customer',
            'customer_phone' => '081211112222',
            'pickup_address' => 'Jl. Melati No. 1',
            'delivery_address' => 'Jl. Kenanga No. 9',
            'package_weight' => 1.5,
            'price' => 18000,
            'status' => 'delivering',
        ]);
    }

    public function test_chat_tables_and_real_order_shipment_conversation_resolution(): void
    {
        $this->assertTrue(Schema::hasTable('conversations'));
        $this->assertTrue(Schema::hasTable('messages'));
        $this->assertTrue(Schema::hasTable('whatsapp_contacts'));
        $this->assertTrue(Schema::hasTable('whatsapp_message_logs'));
        $this->assertTrue(Schema::hasTable('user_device_tokens'));

        // Customer opens chat by tracking_number (resolves Shipment <-> Order via order_number == shipment_number)
        Sanctum::actingAs($this->customer);
        $customerRes = $this->postJson('/api/v1/chat/conversations/by-order/' . $this->shipment->tracking_number);
        $customerRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_id', $this->order->id)
            ->assertJsonPath('data.shipment_id', $this->shipment->id)
            ->assertJsonPath('data.customer_user_id', $this->customer->id)
            ->assertJsonPath('data.courier_user_id', $this->courierUser->id)
            ->assertJsonPath('data.viewer_role', 'customer');

        $convId = $customerRes->json('data.id');

        // Courier opens same conversation by order.id
        Sanctum::actingAs($this->courierUser);
        $this->postJson('/api/v1/chat/conversations/by-order/' . $this->order->id)
            ->assertOk()
            ->assertJsonPath('data.id', $convId)
            ->assertJsonPath('data.viewer_role', 'courier');

        $this->assertDatabaseCount('conversations', 1);

        // Stranger rejected with 403
        Sanctum::actingAs($this->stranger);
        $this->postJson('/api/v1/chat/conversations/by-order/' . $this->order->id)->assertStatus(403);
        $this->getJson('/api/v1/chat/conversations/' . $convId)->assertStatus(403);
    }

    public function test_send_image_idempotent_read_cursor_pagination_and_reverb_auth(): void
    {
        Storage::fake('local');
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb' => [
                'driver' => 'reverb',
                'key' => 'cc-reverb-key',
                'secret' => 'cc-reverb-secret',
                'app_id' => 'cc-app',
                'options' => ['host' => 'ws.neonflux.my.id', 'port' => 443, 'scheme' => 'https'],
            ],
        ]);

        Sanctum::actingAs($this->customer);
        $convId = $this->postJson('/api/v1/chat/conversations/by-order/' . $this->order->id)->json('data.id');

        // Upload photo message + idempotent text replay
        $imgRes = $this->post("/api/v1/chat/conversations/{$convId}/messages", [
            'body' => 'Foto pagar depan rumah.',
            'media' => UploadedFile::fake()->image('pagar.jpg', 300, 300),
            'client_message_id' => 'real-img-1',
        ], ['Accept' => 'application/json']);
        $imgRes->assertStatus(201)->assertJsonPath('data.message_type', 'image');
        $imgMsgId = $imgRes->json('data.id');

        $this->postJson("/api/v1/chat/conversations/{$convId}/messages", [
            'body' => 'Jangan duplikat',
            'client_message_id' => 'dup-txt-1',
        ])->assertStatus(201);
        $this->postJson("/api/v1/chat/conversations/{$convId}/messages", [
            'body' => 'Jangan duplikat',
            'client_message_id' => 'dup-txt-1',
        ])->assertOk();
        $this->assertDatabaseCount('messages', 2);

        // Authorized courier downloads private image & gets Reverb auth signature
        Sanctum::actingAs($this->courierUser);
        $this->get("/api/v1/chat/media/{$imgMsgId}")->assertOk();
        $this->postJson('/api/v1/chat/broadcasting/auth', [
            'socket_id' => '444.555',
            'channel_name' => 'private-conversation.' . $convId,
        ])->assertOk()->assertJsonStructure(['auth']);

        // Stranger denied media & Reverb channel auth
        Sanctum::actingAs($this->stranger);
        $this->getJson("/api/v1/chat/media/{$imgMsgId}")->assertStatus(403);
        $this->postJson('/api/v1/chat/broadcasting/auth', [
            'socket_id' => '444.555',
            'channel_name' => 'private-conversation.' . $convId,
        ])->assertStatus(403);
    }

    public function test_whatsapp_opt_in_24h_window_template_and_signed_webhook_with_real_user(): void
    {
        // Unverified phone_verified_at=null rejected
        Sanctum::actingAs($this->stranger);
        $this->postJson('/api/v1/chat/whatsapp/opt-in', ['opt_in' => true])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'PHONE_NOT_VERIFIED');

        // Verified customer opts in via real phone_verified_at timestamp
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v1/chat/whatsapp/opt-in', ['opt_in' => true])
            ->assertOk()
            ->assertJsonPath('data.phone_e164', '6281211112222')
            ->assertJsonPath('data.opt_in_status', 'opted_in');

        config([
            'whatsapp.enabled' => true,
            'whatsapp.phone_number_id' => '1122334455',
            'whatsapp.access_token' => 'meta-test-token',
            'whatsapp.app_secret' => 'meta-app-secret',
            'whatsapp.verify_token' => 'meta-verify-tok',
        ]);

        Http::fake([
            'https://graph.facebook.com/v21.0/1122334455/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.REAL_OUT_1']],
            ], 200),
        ]);

        // Courier sends message while customer 24h window is still closed -> Utility Template sent
        Sanctum::actingAs($this->courierUser);
        $convId = $this->postJson('/api/v1/chat/conversations/by-order/' . $this->order->id)->json('data.id');
        $this->postJson("/api/v1/chat/conversations/{$convId}/messages", [
            'body' => 'Saya sudah di depan jalan masuk.',
            'client_message_id' => 'wa-real-1',
        ])->assertStatus(201);

        Http::assertSent(fn (HttpClientRequest $req) => ($req->data()['type'] ?? null) === 'template');

        // Customer replies via official Meta Webhook -> opens window, stores WA message, broadcasts without loop
        Event::fake([MessageSentBroadcast::class, ConversationUpdatedBroadcast::class, MessageStatusUpdatedBroadcast::class]);
        Queue::fake([SendWhatsAppMessageJob::class]);

        $inboundPayload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '6281211112222',
                            'id' => 'wamid.REAL_IN_1',
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => 'Iya rumah nomor 14 mas.'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $raw = json_encode($inboundPayload, JSON_THROW_ON_ERROR);
        $sig = 'sha256=' . hash_hmac('sha256', $raw, 'meta-app-secret');

        $this->call('POST', '/api/v1/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $sig,
        ], $raw)->assertOk()->assertJsonPath('duplicate', false);

        // Duplicate webhook returns 200 without duplicating message
        $this->call('POST', '/api/v1/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $sig,
        ], $raw)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $convId,
            'sender_user_id' => $this->customer->id,
            'channel' => Message::CHANNEL_WHATSAPP,
            'wa_message_id' => 'wamid.REAL_IN_1',
            'body' => 'Iya rumah nomor 14 mas.',
        ]);

        Queue::assertNothingPushed();
        Event::assertDispatched(MessageSentBroadcast::class);
    }

    public function test_fcm_push_sends_and_revokes_stale_tokens(): void
    {
        config([
            'fcm.enabled' => true,
            'fcm.project_id' => 'cc-firebase-prod',
            'fcm.static_access_token' => 'test-oauth-bearer',
        ]);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/cc-firebase-prod/messages:send' => Http::response(
                ['error' => ['status' => 'NOT_FOUND', 'message' => 'UNREGISTERED']],
                404
            ),
        ]);

        Sanctum::actingAs($this->courierUser);
        $this->postJson('/api/v1/devices/fcm-token', [
            'fcm_token' => 'courier-stale-token',
            'platform' => 'android',
        ])->assertOk();

        Sanctum::actingAs($this->customer);
        $convId = $this->postJson('/api/v1/chat/conversations/by-order/' . $this->order->id)->json('data.id');
        $this->postJson("/api/v1/chat/conversations/{$convId}/messages", [
            'body' => 'Cek push notification.',
            'client_message_id' => 'fcm-real-1',
        ])->assertStatus(201);

        $this->assertDatabaseHas('user_device_tokens', [
            'user_id' => $this->courierUser->id,
            'fcm_token' => 'courier-stale-token',
            'is_active' => false,
        ]);
    }

    public function test_unauthenticated_inbox_unread_read_cursor_typing_and_closed_guard(): void
    {
        $this->getJson('/api/v1/chat/conversations')->assertStatus(401);
        $this->postJson('/api/v1/chat/conversations/by-order/' . $this->order->id)->assertStatus(401);

        Sanctum::actingAs($this->customer);
        $convId = $this->postJson('/api/v1/chat/conversations/by-order/' . $this->order->id)->json('data.id');

        $lastMsgId = null;
        for ($i = 1; $i <= 4; $i++) {
            $res = $this->postJson("/api/v1/chat/conversations/{$convId}/messages", [
                'body' => "Pesan urut $i",
                'client_message_id' => "seq-real-$i",
            ])->assertStatus(201);
            $lastMsgId = $res->json('data.id');
        }

        // Courier sees unread count in summary and inbox, then marks read
        Sanctum::actingAs($this->courierUser);
        $this->getJson('/api/v1/chat/unread-count')
            ->assertOk()
            ->assertJsonPath('data.total_unread', 4);

        $this->getJson('/api/v1/chat/conversations')
            ->assertOk()
            ->assertJsonPath('data.total_unread', 4)
            ->assertJsonPath('data.conversations.0.unread_count', 4);

        $page1 = $this->getJson("/api/v1/chat/conversations/{$convId}/messages?limit=2")
            ->assertOk()
            ->assertJsonPath('data.has_more', true)
            ->assertJsonPath('data.messages.0.body', 'Pesan urut 3')
            ->assertJsonPath('data.messages.1.body', 'Pesan urut 4');

        $beforeId = $page1->json('data.next_before_id');
        $this->getJson("/api/v1/chat/conversations/{$convId}/messages?limit=2&before_id={$beforeId}")
            ->assertOk()
            ->assertJsonPath('data.messages.0.body', 'Pesan urut 1')
            ->assertJsonPath('data.messages.1.body', 'Pesan urut 2');

        $this->postJson("/api/v1/chat/conversations/{$convId}/read", [
            'last_read_message_id' => $lastMsgId,
        ])->assertOk()->assertJsonPath('data.updated_count', 4);

        $this->getJson('/api/v1/chat/unread-count')
            ->assertOk()
            ->assertJsonPath('data.total_unread', 0);

        // Typing indicator throttles repeated calls
        $this->postJson("/api/v1/chat/conversations/{$convId}/typing", ['is_typing' => true])
            ->assertOk()
            ->assertJsonPath('data.throttled', false);
        $this->postJson("/api/v1/chat/conversations/{$convId}/typing", ['is_typing' => true])
            ->assertOk()
            ->assertJsonPath('data.throttled', true);

        // Closed conversation rejects new messages
        Conversation::whereKey($convId)->update(['status' => Conversation::STATUS_CLOSED]);
        $this->postJson("/api/v1/chat/conversations/{$convId}/messages", [
            'body' => 'Setelah ditutup',
        ])->assertStatus(403);
    }

    public function test_realtime_config_channel_authorizers_fcm_delete_and_broadcast_events(): void
    {
        Sanctum::actingAs($this->customer);
        $convId = $this->postJson('/api/v1/chat/conversations/by-order/' . $this->order->id)->json('data.id');

        // Default booted driver authorizers
        $channels = Broadcast::getChannels();
        $convAuth = $channels->get('conversation.{conversationId}');
        $userAuth = $channels->get('user.{userId}');
        $this->assertNotNull($convAuth);
        $this->assertNotNull($userAuth);
        $this->assertTrue((bool) $convAuth($this->customer, $convId));
        $this->assertTrue((bool) $convAuth($this->courierUser, $convId));
        $this->assertFalse((bool) $convAuth($this->stranger, $convId));
        $this->assertTrue((bool) $userAuth($this->customer, $this->customer->id));
        $this->assertFalse((bool) $userAuth($this->customer, $this->courierUser->id));

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb' => [
                'driver' => 'reverb',
                'key' => 'city-courier-public-key',
                'secret' => 'city-courier-secret-never-leaked',
                'app_id' => 'city-courier-app',
                'options' => ['host' => 'ws.neonflux.my.id', 'port' => 443, 'scheme' => 'https'],
            ],
        ]);

        $cfg = $this->getJson('/api/v1/chat/realtime-config')->assertOk();
        $cfg->assertJsonPath('data.driver', 'reverb')
            ->assertJsonPath('data.key', 'city-courier-public-key')
            ->assertJsonPath('data.host', 'ws.neonflux.my.id')
            ->assertJsonMissing(['city-courier-secret-never-leaked']);

        // Switching to reverb at runtime lazily registers channels via RealtimeController::authenticate
        $this->postJson('/api/v1/chat/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-user.' . $this->customer->id,
        ])->assertOk()->assertJsonStructure(['auth']);
        $this->assertTrue(Broadcast::getChannels()->has('conversation.{conversationId}'));

        // FCM register & delete
        $this->postJson('/api/v1/devices/fcm-token', [
            'fcm_token' => 'android-fcm-del-1',
            'platform' => 'android',
        ])->assertOk()->assertJsonPath('data.is_active', true);

        $this->deleteJson('/api/v1/devices/fcm-token', [
            'fcm_token' => 'android-fcm-del-1',
        ])->assertOk();

        $this->assertDatabaseHas('user_device_tokens', [
            'user_id' => $this->customer->id,
            'fcm_token' => 'android-fcm-del-1',
            'is_active' => false,
        ]);

        // Broadcast events verified directly
        Event::fake([
            MessageSentBroadcast::class,
            ConversationUpdatedBroadcast::class,
            MessageStatusUpdatedBroadcast::class,
            UserTypingBroadcast::class,
        ]);

        $msgId = $this->postJson("/api/v1/chat/conversations/{$convId}/messages", [
            'body' => 'Cek broadcast event.',
            'client_message_id' => 'evt-real-1',
        ])->assertStatus(201)->json('data.id');

        Event::assertDispatched(MessageSentBroadcast::class);
        Event::assertDispatched(ConversationUpdatedBroadcast::class);

        $this->postJson("/api/v1/chat/conversations/{$convId}/typing", ['is_typing' => true])->assertOk();
        Event::assertDispatched(UserTypingBroadcast::class);

        Sanctum::actingAs($this->courierUser);
        $this->postJson("/api/v1/chat/conversations/{$convId}/read", [
            'last_read_message_id' => $msgId,
        ])->assertOk();
        Event::assertDispatched(MessageStatusUpdatedBroadcast::class);
    }

    public function test_database_unique_constraints_and_cascade_delete(): void
    {
        $conv = Conversation::create([
            'order_id' => $this->order->id,
            'customer_user_id' => $this->customer->id,
            'courier_user_id' => $this->courierUser->id,
        ]);

        $duplicateOrderThrown = false;
        try {
            Conversation::create([
                'order_id' => $this->order->id,
                'customer_user_id' => $this->customer->id,
                'courier_user_id' => $this->courierUser->id,
            ]);
        } catch (QueryException) {
            $duplicateOrderThrown = true;
        }
        $this->assertTrue($duplicateOrderThrown);

        Message::create([
            'conversation_id' => $conv->id,
            'sender_user_id' => $this->customer->id,
            'sender_role' => Message::ROLE_CUSTOMER,
            'client_message_id' => 'dup-constraint-1',
            'body' => 'Pertama',
        ]);

        $duplicateClientMsgThrown = false;
        try {
            Message::create([
                'conversation_id' => $conv->id,
                'sender_user_id' => $this->customer->id,
                'sender_role' => Message::ROLE_CUSTOMER,
                'client_message_id' => 'dup-constraint-1',
                'body' => 'Duplikat',
            ]);
        } catch (QueryException) {
            $duplicateClientMsgThrown = true;
        }
        $this->assertTrue($duplicateClientMsgThrown);

        $conv->delete();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_whatsapp_outbound_skips_when_disabled_or_opted_out_and_prevents_loop(): void
    {
        Http::fake();
        config(['whatsapp.enabled' => false]);

        $courierContact = WhatsappContact::create([
            'user_id' => $this->courierUser->id,
            'wa_phone_e164' => '6281322223333',
            'opt_in_status' => WhatsappContact::OPT_IN,
            'last_inbound_at' => now()->subMinute(),
            'window_expires_at' => now()->addHours(23),
        ]);

        Sanctum::actingAs($this->customer);
        $convId = $this->postJson('/api/v1/chat/conversations/by-order/' . $this->order->id)->json('data.id');

        $this->postJson("/api/v1/chat/conversations/{$convId}/messages", [
            'body' => 'Flag WA masih mati.',
            'client_message_id' => 'out-flag-off',
        ])->assertStatus(201);
        Http::assertNothingSent();
        $this->assertDatabaseCount('whatsapp_message_logs', 0);

        // Enable Meta WA, but opt out courier -> still skipped
        config([
            'whatsapp.enabled' => true,
            'whatsapp.phone_number_id' => '1122334455',
            'whatsapp.access_token' => 'meta-test-token',
        ]);
        $courierContact->markOptedOut('user_toggle');
        $courierContact->save();

        $this->postJson("/api/v1/chat/conversations/{$convId}/messages", [
            'body' => 'Jangan kirim jika opt-out.',
            'client_message_id' => 'out-optout',
        ])->assertStatus(201);
        Http::assertNothingSent();

        // Anti-loop: inbound whatsapp message must never dispatch outbound job
        Queue::fake();
        $inboundMsg = Message::create([
            'conversation_id' => $convId,
            'sender_user_id' => $this->customer->id,
            'sender_role' => Message::ROLE_CUSTOMER,
            'channel' => Message::CHANNEL_WHATSAPP,
            'message_type' => Message::TYPE_TEXT,
            'body' => 'Dari WA',
            'delivery_status' => Message::STATUS_SENT,
            'wa_message_id' => 'wamid.ANTI_LOOP_1',
        ]);
        \App\Modules\Chat\Events\InternalChatMessagePersisted::dispatch(Conversation::findOrFail($convId), $inboundMsg);
        Queue::assertNothingPushed();
        SendWhatsAppMessageJob::dispatchSync($convId, $inboundMsg->id);
        $this->assertSame('wamid.ANTI_LOOP_1', $inboundMsg->fresh()->wa_message_id);
    }

    public function test_whatsapp_outbound_in_window_sends_text_and_uploads_media(): void
    {
        config([
            'whatsapp.enabled' => true,
            'whatsapp.phone_number_id' => '1122334455',
            'whatsapp.access_token' => 'meta-test-token',
        ]);

        WhatsappContact::create([
            'user_id' => $this->courierUser->id,
            'wa_phone_e164' => '6281322223333',
            'opt_in_status' => WhatsappContact::OPT_IN,
            'last_inbound_at' => now()->subMinutes(5),
            'window_expires_at' => now()->addHours(23),
        ]);

        Storage::fake('local');
        Http::fake([
            'https://graph.facebook.com/v21.0/1122334455/media' => Http::response(['id' => 'media-wa-88'], 200),
            'https://graph.facebook.com/v21.0/1122334455/messages' => Http::sequence()
                ->push(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.OUT_OK_1']]], 200)
                ->push(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.OUT_IMG_1']]], 200),
        ]);

        Sanctum::actingAs($this->customer);
        $convId = $this->postJson('/api/v1/chat/conversations/by-order/' . $this->order->id)->json('data.id');

        $textMsgId = $this->postJson("/api/v1/chat/conversations/{$convId}/messages", [
            'body' => 'Saya tunggu di lobby.',
            'client_message_id' => 'out-text-ok',
        ])->assertStatus(201)->json('data.id');

        Http::assertSent(fn (HttpClientRequest $req) => str_ends_with($req->url(), '/messages')
            && ($req->data()['type'] ?? null) === 'text'
            && str_contains($req->data()['text']['body'] ?? '', '[Order CC-20261125-001]'));

        $this->assertDatabaseHas('messages', [
            'id' => $textMsgId,
            'wa_message_id' => 'wamid.OUT_OK_1',
            'wa_status' => 'accepted',
        ]);

        $imgMsgId = $this->post("/api/v1/chat/conversations/{$convId}/messages", [
            'body' => 'Foto lokasi paket.',
            'media' => UploadedFile::fake()->image('lokasi.jpg', 200, 200),
            'client_message_id' => 'out-img-ok',
        ], ['Accept' => 'application/json'])->assertStatus(201)->json('data.id');

        Http::assertSent(fn (HttpClientRequest $req) => str_ends_with($req->url(), '/media'));
        Http::assertSent(fn (HttpClientRequest $req) => str_ends_with($req->url(), '/messages')
            && ($req->data()['type'] ?? null) === 'image'
            && ($req->data()['image']['id'] ?? null) === 'media-wa-88');

        $this->assertDatabaseHas('messages', [
            'id' => $imgMsgId,
            'wa_message_id' => 'wamid.OUT_IMG_1',
        ]);
    }

    public function test_whatsapp_outbound_permanent_meta_error_preserves_internal_sent_status(): void
    {
        config([
            'whatsapp.enabled' => true,
            'whatsapp.phone_number_id' => '1122334455',
            'whatsapp.access_token' => 'meta-test-token',
        ]);

        WhatsappContact::create([
            'user_id' => $this->courierUser->id,
            'wa_phone_e164' => '6281322223333',
            'opt_in_status' => WhatsappContact::OPT_IN,
            'last_inbound_at' => now()->subMinutes(5),
            'window_expires_at' => now()->addHours(23),
        ]);

        Http::fake([
            'https://graph.facebook.com/v21.0/1122334455/messages' => Http::response([
                'error' => ['message' => 'Message undeliverable', 'code' => 131026],
            ], 400),
        ]);

        Sanctum::actingAs($this->customer);
        $convId = $this->postJson('/api/v1/chat/conversations/by-order/' . $this->order->id)->json('data.id');

        $errMsgId = $this->postJson("/api/v1/chat/conversations/{$convId}/messages", [
            'body' => 'Test permanent Meta error.',
            'client_message_id' => 'out-err-131026',
        ])->assertStatus(201)->json('data.id');

        $this->assertDatabaseHas('messages', [
            'id' => $errMsgId,
            'delivery_status' => Message::STATUS_SENT,
            'wa_status' => 'failed',
            'wa_error_code' => '131026',
        ]);
    }

    public function test_whatsapp_webhook_verify_signature_context_routing_stop_and_monotonic_status(): void
    {
        config([
            'whatsapp.enabled' => true,
            'whatsapp.verify_token' => 'verify-tok-123',
            'whatsapp.app_secret' => 'app-sec-123',
            'whatsapp.phone_number_id' => '1122334455',
            'whatsapp.access_token' => 'meta-token',
        ]);

        // Verify token GET endpoint
        $this->get('/api/v1/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=verify-tok-123&hub.challenge=CHAL_OK')
            ->assertOk()
            ->assertSeeText('CHAL_OK');
        $this->get('/api/v1/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=salah&hub.challenge=CHAL_OK')
            ->assertStatus(403);

        // Create second active order/shipment so customer has 2 conversations
        Shipment::create([
            'shipment_number' => 'CC-20261125-002',
            'tracking_number' => 'RESI-CC-002',
            'user_id' => $this->customer->id,
            'customer_name' => 'Ayu Customer',
            'customer_phone' => '081211112222',
            'sender_name' => 'Ayu Customer',
            'sender_phone' => '081211112222',
            'sender_address' => 'Jl. Melati No. 1',
            'receiver_name' => 'Budi Penerima',
            'receiver_phone' => '081566667777',
            'receiver_address' => 'Jl. Anggrek No. 2',
            'package_weight' => 1.0,
            'total_cost' => 15000,
            'status' => 'delivering',
        ]);
        $order2 = Order::create([
            'order_number' => 'CC-20261125-002',
            'courier_id' => $this->courier->id,
            'customer_name' => 'Ayu Customer',
            'customer_phone' => '081211112222',
            'pickup_address' => 'Jl. Melati No. 1',
            'delivery_address' => 'Jl. Anggrek No. 2',
            'package_weight' => 1.0,
            'price' => 15000,
            'status' => 'delivering',
        ]);

        Sanctum::actingAs($this->customer);
        $olderConvId = $this->postJson('/api/v1/chat/conversations/by-order/' . $this->order->id)->json('data.id');
        $newerConvId = $this->postJson('/api/v1/chat/conversations/by-order/' . $order2->id)->json('data.id');

        WhatsappContact::create([
            'user_id' => $this->customer->id,
            'wa_phone_e164' => '6281211112222',
            'opt_in_status' => WhatsappContact::OPT_IN,
            'active_conversation_id' => $newerConvId,
        ]);

        // Outbound message on older order so context.id can route back to it
        $outboundOlder = Message::create([
            'conversation_id' => $olderConvId,
            'sender_user_id' => $this->courierUser->id,
            'sender_role' => Message::ROLE_COURIER,
            'channel' => Message::CHANNEL_INTERNAL,
            'message_type' => Message::TYPE_TEXT,
            'body' => 'Order 1 sedang dikirim.',
            'wa_message_id' => 'wamid.OLDER_ORDER_MSG',
            'delivery_status' => Message::STATUS_SENT,
        ]);

        $postSigned = function (array $payload, ?string $overrideSig = null) {
            $raw = json_encode($payload, JSON_THROW_ON_ERROR);
            $sig = $overrideSig ?? ('sha256=' . hash_hmac('sha256', $raw, 'app-sec-123'));

            return $this->call('POST', '/api/v1/whatsapp/webhook', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $sig,
            ], $raw);
        };

        // Invalid signature returns 403 and logs audit row
        $postSigned(['object' => 'whatsapp_business_account', 'entry' => []], 'sha256=invalid')
            ->assertStatus(403);
        $this->assertDatabaseHas('whatsapp_message_logs', [
            'signature_valid' => false,
            'http_status' => 403,
        ]);

        // Context reply routes to older conversation instead of newer active_conversation_id
        $postSigned([
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '6281211112222',
                            'id' => 'wamid.CTX_REPLY_1',
                            'context' => ['id' => 'wamid.OLDER_ORDER_MSG'],
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => 'Balas khusus order 1 ya.'],
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $olderConvId,
            'wa_message_id' => 'wamid.CTX_REPLY_1',
            'body' => 'Balas khusus order 1 ya.',
        ]);

        // Status webhook monotonic update + late downgrade ignored
        Event::fake([MessageStatusUpdatedBroadcast::class]);
        $statusPayload = fn (string $st, int $ts) => [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.OLDER_ORDER_MSG',
                            'status' => $st,
                            'timestamp' => (string) $ts,
                        ]],
                    ],
                ]],
            ]],
        ];

        $postSigned($statusPayload('delivered', now()->subSeconds(10)->timestamp))->assertOk();
        $this->assertSame(Message::STATUS_DELIVERED, $outboundOlder->fresh()->delivery_status);

        $postSigned($statusPayload('read', now()->subSeconds(5)->timestamp))->assertOk();
        $this->assertSame(Message::STATUS_READ, $outboundOlder->fresh()->delivery_status);

        $postSigned($statusPayload('sent', now()->subMinutes(5)->timestamp))->assertOk();
        $this->assertSame(Message::STATUS_READ, $outboundOlder->fresh()->delivery_status);
        Event::assertDispatchedTimes(MessageStatusUpdatedBroadcast::class, 2);

        // STOP keyword revokes opt-in without creating an internal chat message
        $beforeMsgCount = Message::count();
        $postSigned([
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '6281211112222',
                            'id' => 'wamid.STOP_KW_1',
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => 'STOP'],
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('whatsapp_contacts', [
            'user_id' => $this->customer->id,
            'opt_in_status' => WhatsappContact::OPT_OUT,
            'opt_in_source' => 'stop_keyword',
        ]);
        $this->assertSame($beforeMsgCount, Message::count());
    }
}
