<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Setting;
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
            'chat.whatsapp_bridge_enabled' => true,
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
            'chat.whatsapp_bridge_enabled' => true,
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
            'chat.whatsapp_bridge_enabled' => true,
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
            'chat.whatsapp_bridge_enabled' => true,
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

    public function test_admin_can_configure_test_and_toggle_meta_whatsapp_cloud_api_in_providers_panel(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $opsRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'ops-admin', 'guard_name' => 'web']);
        $viewPerm = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'settings.view', 'guard_name' => 'web']);
        $editPerm = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'settings.edit', 'guard_name' => 'web']);
        $opsRole->givePermissionTo([$viewPerm, $editPerm]);

        $opsAdmin = User::create([
            'name' => 'Ops Provider Admin',
            'email' => 'ops.wa@city.test',
            'role' => 'admin',
        ]);
        $opsAdmin->assignRole($opsRole);

        // Start with completely blank .env/config for WhatsApp Cloud API while optional bridge flag is enabled
        config([
            'chat.whatsapp_bridge_enabled' => true,
            'whatsapp.enabled' => false,
            'whatsapp.phone_number_id' => '',
            'whatsapp.waba_id' => '',
            'whatsapp.access_token' => '',
            'whatsapp.app_secret' => '',
            'whatsapp.verify_token' => '',
        ]);

        // Non-admin customer cannot access admin WA Cloud settings or toggle
        $this->actingAs($this->stranger)
            ->postJson('/admin/settings/whatsapp-cloud', ['whatsapp_cloud_base_url' => 'https://graph.facebook.com', 'whatsapp_cloud_api_version' => 'v21.0'])
            ->assertStatus(403);
        $this->actingAs($this->stranger)
            ->postJson('/admin/providers/whatsapp_cloud/toggle', ['enabled' => 1])
            ->assertStatus(403);

        // Ops admin with settings.view + settings.edit loads /admin/providers and sees WA Cloud API card
        $this->actingAs($opsAdmin);
        $providersPage = $this->get('/admin/providers')->assertOk();
        $providersPage->assertSee('Meta WhatsApp Business Cloud API');
        $providersPage->assertSee('/admin/providers/whatsapp_cloud/toggle');

        $manualToken = 'EAAG_MANUAL_DB_TOKEN_SECRET_991122';
        $manualAppSecret = 'manual-meta-app-secret-778899';
        $manualVerifyToken = 'manual-verify-token-445566';

        // Save credentials manually via Admin Providers form
        $this->post('/admin/settings/whatsapp-cloud', [
            'whatsapp_cloud_base_url' => 'https://graph.facebook.com',
            'whatsapp_cloud_api_version' => 'v21.0',
            'whatsapp_cloud_phone_number_id' => '998811223344',
            'whatsapp_cloud_waba_id' => '776655443322',
            'whatsapp_cloud_utility_template_name' => 'custom_citycourier_tpl',
            'whatsapp_cloud_utility_template_lang' => 'id',
            'whatsapp_cloud_window_hours' => 24,
            'whatsapp_cloud_timeout_seconds' => 12,
            'whatsapp_cloud_enabled' => '1',
            'whatsapp_cloud_access_token' => $manualToken,
            'whatsapp_cloud_app_secret' => $manualAppSecret,
            'whatsapp_cloud_verify_token' => $manualVerifyToken,
        ])->assertRedirect();

        // All secrets must be stored encrypted (enc:...) and never leak plaintext in HTML
        foreach ([
            'whatsapp_cloud_access_token' => $manualToken,
            'whatsapp_cloud_app_secret' => $manualAppSecret,
            'whatsapp_cloud_verify_token' => $manualVerifyToken,
        ] as $key => $plainSecret) {
            $raw = Setting::get($key);
            $this->assertIsString($raw);
            $this->assertStringStartsWith('enc:', $raw);
            $this->assertStringNotContainsString($plainSecret, $raw);
        }

        $afterSaveHtml = $this->get('/admin/providers')->assertOk()->getContent();
        $this->assertStringContainsString('API Terkonfigurasi', $afterSaveHtml);
        $this->assertStringContainsString('Webhook Siap', $afterSaveHtml);
        $this->assertStringNotContainsString($manualToken, $afterSaveHtml);
        $this->assertStringNotContainsString($manualAppSecret, $afterSaveHtml);
        $this->assertStringNotContainsString($manualVerifyToken, $afterSaveHtml);

        // Saving again with blank secret inputs preserves existing encrypted secrets
        $this->post('/admin/settings/whatsapp-cloud', [
            'whatsapp_cloud_base_url' => 'https://graph.facebook.com',
            'whatsapp_cloud_api_version' => 'v21.0',
            'whatsapp_cloud_phone_number_id' => '998811223344',
            'whatsapp_cloud_waba_id' => '776655443322',
            'whatsapp_cloud_utility_template_name' => 'custom_citycourier_tpl',
            'whatsapp_cloud_utility_template_lang' => 'id',
            'whatsapp_cloud_window_hours' => 24,
            'whatsapp_cloud_timeout_seconds' => 12,
            'whatsapp_cloud_enabled' => '1',
        ])->assertRedirect();

        // Admin Test Koneksi Meta API uses DB credentials (even with empty .env) and outbound template uses DB settings
        Http::fake([
            'https://graph.facebook.com/v21.0/998811223344/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.DB_CREDS_1']],
            ], 200),
            'https://graph.facebook.com/v21.0/998811223344*' => Http::response([
                'id' => '998811223344',
                'display_phone_number' => '+62 811-2233-4455',
                'verified_name' => 'City Courier Official',
                'quality_rating' => 'GREEN',
            ], 200),
        ]);

        $this->postJson('/admin/settings/whatsapp-cloud/test', [
            'whatsapp_cloud_base_url' => 'https://graph.facebook.com',
            'whatsapp_cloud_api_version' => 'v21.0',
            'whatsapp_cloud_phone_number_id' => '998811223344',
        ])->assertOk()
            ->assertJsonPath('success', true);

        // Verify public /api/settings + chat whatsapp status reflect DB toggle ON
        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.providers.whatsapp_cloud', true);

        Sanctum::actingAs($this->customer);
        $this->getJson('/api/v1/chat/whatsapp/status')
            ->assertOk()
            ->assertJsonPath('data.whatsapp_cloud_enabled', true);

        // Verify Meta GET webhook handshake & signed POST webhook work purely from DB secrets
        $this->get('/api/v1/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=' . $manualVerifyToken . '&hub.challenge=DB_CHAL_OK')
            ->assertOk()
            ->assertSeeText('DB_CHAL_OK');

        $this->postJson('/api/v1/chat/whatsapp/opt-in', ['opt_in' => true])->assertOk();

        Sanctum::actingAs($this->courierUser);
        $convId = $this->postJson('/api/v1/chat/conversations/by-order/' . $this->order->id)->json('data.id');
        $this->postJson("/api/v1/chat/conversations/{$convId}/messages", [
            'body' => 'Pesan pakai kredensial Admin UI.',
            'client_message_id' => 'db-creds-msg-1',
        ])->assertStatus(201);

        Http::assertSent(fn (HttpClientRequest $req) => str_ends_with($req->url(), '/messages')
            && $req->hasHeader('Authorization', 'Bearer ' . $manualToken)
            && ($req->data()['template']['name'] ?? null) === 'custom_citycourier_tpl');

        // Signed webhook using DB App Secret
        $signedBody = json_encode(['object' => 'whatsapp_business_account', 'entry' => []], JSON_THROW_ON_ERROR);
        $sig = 'sha256=' . hash_hmac('sha256', $signedBody, $manualAppSecret);
        $this->call('POST', '/api/v1/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $sig,
        ], $signedBody)->assertOk();

        // Admin toggles provider OFF -> runtime disabled without erasing DB secrets
        $this->actingAs($opsAdmin)
            ->postJson('/admin/providers/whatsapp_cloud/toggle', ['enabled' => 0])
            ->assertOk()
            ->assertJsonPath('provider', 'whatsapp_cloud')
            ->assertJsonPath('enabled', false);

        Sanctum::actingAs($this->customer);
        $this->getJson('/api/v1/chat/whatsapp/status')
            ->assertOk()
            ->assertJsonPath('data.whatsapp_cloud_enabled', false);

        // Clear secrets checkbox wipes encrypted secrets cleanly
        $this->actingAs($opsAdmin)
            ->post('/admin/settings/whatsapp-cloud', [
                'whatsapp_cloud_base_url' => 'https://graph.facebook.com',
                'whatsapp_cloud_api_version' => 'v21.0',
                'clear_whatsapp_cloud_secrets' => '1',
            ])->assertRedirect();

        $this->assertSame('', Setting::get('whatsapp_cloud_access_token'));
        $this->assertSame('', Setting::get('whatsapp_cloud_app_secret'));
        $this->assertSame('', Setting::get('whatsapp_cloud_verify_token'));
    }

    public function test_admin_can_send_wa_test_message_and_dispatch_after_response_without_queue_worker(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $admin = User::create([
            'name' => 'Super Admin WA Test',
            'email' => 'wa.test.admin@city.test',
            'role' => 'admin',
        ]);
        $admin->assignRole('super-admin');

        config([
            'chat.whatsapp_bridge_enabled' => true,
            'whatsapp.enabled' => true,
            'whatsapp.phone_number_id' => '998811223344',
            'whatsapp.access_token' => 'test-token-wa-diag',
            'whatsapp.app_secret' => 'test-secret-wa-diag',
            'whatsapp.verify_token' => 'test-verify-wa-diag',
        ]);

        Http::fake([
            'https://graph.facebook.com/v21.0/998811223344/messages' => Http::sequence()
                ->push(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.TEST_HELLO_1']]], 200)
                ->push(['error' => ['message' => 'Recipient phone number not in allowed list', 'code' => 131030]], 400)
                ->push(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.AFTER_RESPONSE_1']]], 200),
        ]);

        // 1. Admin triggers "Kirim Tes Pesan WA" (hello_world) & verifies clear 131030 error hint
        $this->actingAs($admin)
            ->postJson('/admin/settings/whatsapp-cloud/test', [
                'test_action' => 'send_test',
                'whatsapp_cloud_test_phone' => '081234567890',
                'whatsapp_cloud_test_mode' => 'hello_world',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($admin)
            ->postJson('/admin/settings/whatsapp-cloud/test', [
                'test_action' => 'send_test',
                'whatsapp_cloud_test_phone' => '081234567891',
                'whatsapp_cloud_test_mode' => 'hello_world',
            ])
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        // 2. Check counterparty.wa_opted_in on ConversationResource before and after courier opt-in
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v1/chat/conversations/by-order/' . $this->order->id)
            ->assertOk()
            ->assertJsonPath('data.counterparty.wa_opted_in', false);

        Sanctum::actingAs($this->courierUser);
        $this->postJson('/api/v1/chat/whatsapp/opt-in', ['opt_in' => true])
            ->assertOk()
            ->assertJsonPath('data.whatsapp_cloud_enabled', true)
            ->assertJsonPath('data.phone_verified', true);

        // Open courier window so free-text dispatch runs cleanly after response even on database queue driver
        $courierContact = WhatsappContact::query()->where('user_id', $this->courierUser->id)->firstOrFail();
        $courierContact->openWindow(now()->subMinutes(5));
        $courierContact->save();

        config([
            'queue.default' => 'database',
            'chat.use_queue_worker' => false,
        ]);

        Sanctum::actingAs($this->customer);
        $convRes = $this->postJson('/api/v1/chat/conversations/by-order/' . $this->order->id)
            ->assertOk()
            ->assertJsonPath('data.counterparty.wa_opted_in', true);
        $convId = $convRes->json('data.id');

        $msgId = $this->postJson("/api/v1/chat/conversations/{$convId}/messages", [
            'body' => 'Kirim tanpa queue worker.',
            'client_message_id' => 'no-worker-msg-1',
        ])->assertStatus(201)->json('data.id');

        $this->assertDatabaseHas('messages', [
            'id' => $msgId,
            'wa_message_id' => 'wamid.AFTER_RESPONSE_1',
            'wa_status' => 'accepted',
        ]);
    }

    public function test_send_test_message_131037_display_name_error_returns_indonesian_guidance(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $admin = User::create([
            'name' => 'Super Admin WA 131037',
            'email' => 'wa.131037.admin@city.test',
            'role' => 'admin',
        ]);
        $admin->assignRole('super-admin');

        config([
            'whatsapp.enabled' => true,
            'whatsapp.phone_number_id' => '998811223344',
            'whatsapp.access_token' => 'test-token-wa-131037',
            'whatsapp.app_secret' => 'test-secret-wa-131037',
            'whatsapp.verify_token' => 'test-verify-wa-131037',
        ]);

        Http::fake([
            'https://graph.facebook.com/v21.0/998811223344/messages' => Http::response([
                'error' => [
                    'message' => 'WhatsApp provided number needs display name approval before message can be sent.',
                    'code' => 131037,
                    'error_data' => ['details' => 'Display name approval required.'],
                ],
            ], 400),
        ]);

        $response = $this->actingAs($admin)
            ->postJson('/admin/settings/whatsapp-cloud/test', [
                'test_action' => 'send_test',
                'whatsapp_cloud_test_phone' => '081234567890',
                'whatsapp_cloud_test_mode' => 'hello_world',
            ])
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $message = (string) $response->json('message');
        $this->assertStringContainsString('131037', $message);
        $this->assertStringContainsString('Display Name', $message);
        $this->assertStringContainsString('Business Suite', $message);
    }

    public function test_admin_whatsapp_cloud_validation_errors_are_rendered_on_providers_page(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $admin = User::create([
            'name' => 'Super Admin WA Errors',
            'email' => 'wa.errors.admin@city.test',
            'role' => 'admin',
        ]);
        $admin->assignRole('super-admin');

        // Invalid base URL fails validation and must NOT fail silently:
        // the redirect back to /admin/providers must render the $errors bag.
        $this->actingAs($admin)
            ->from('/admin/providers')
            ->post('/admin/settings/whatsapp-cloud', [
                'whatsapp_cloud_base_url' => 'not-a-valid-url',
                'whatsapp_cloud_api_version' => 'v21.0',
            ])
            ->assertRedirect('/admin/providers');

        $this->actingAs($admin)
            ->get('/admin/providers')
            ->assertOk()
            ->assertSee('must be a valid URL');
    }

    public function test_internal_chat_never_dispatches_whatsapp_by_default_even_if_cloud_enabled(): void
    {
        config([
            'chat.whatsapp_bridge_enabled' => false,
            'whatsapp.enabled' => true,
            'whatsapp.phone_number_id' => '1122334455',
            'whatsapp.access_token' => 'meta-test-token',
        ]);
        Setting::set('whatsapp_cloud_enabled', '1', 'whatsapp_cloud');

        WhatsappContact::create([
            'user_id' => $this->courierUser->id,
            'wa_phone_e164' => '6281322223333',
            'opt_in_status' => WhatsappContact::OPT_IN,
            'last_inbound_at' => now()->subMinute(),
            'window_expires_at' => now()->addHours(23),
        ]);

        Http::fake();
        Queue::fake([SendWhatsAppMessageJob::class]);

        Sanctum::actingAs($this->customer);
        $this->getJson('/api/v1/chat/whatsapp/status')
            ->assertOk()
            ->assertJsonPath('data.whatsapp_cloud_enabled', false);

        $convRes = $this->postJson('/api/v1/chat/conversations/by-order/' . $this->order->id)
            ->assertOk()
            ->assertJsonPath('data.wa_bridged_enabled', false)
            ->assertJsonPath('data.counterparty.wa_opted_in', false);
        $convId = $convRes->json('data.id');

        $msgRes = $this->postJson("/api/v1/chat/conversations/{$convId}/messages", [
            'body' => 'Pesan internal murni tanpa WA.',
            'client_message_id' => 'pure-internal-1',
        ]);
        $msgRes->assertStatus(201)
            ->assertJsonPath('data.delivery_status', Message::STATUS_SENT)
            ->assertJsonPath('data.wa_status', null);

        Queue::assertNothingPushed();
        Http::assertNothingSent();
        $this->assertDatabaseCount('whatsapp_message_logs', 0);
    }
}
