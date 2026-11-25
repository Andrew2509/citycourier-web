<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Modules\Chat\Events\CallSessionUpdatedBroadcast;
use App\Modules\Chat\Events\CallSignalBroadcast;
use App\Modules\Chat\Events\IncomingCallBroadcast;
use App\Modules\Chat\Models\CallSession;
use App\Modules\Chat\Models\CallSignal;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\UserDeviceToken;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesPaymentWalletSchema;

class VoiceCallSystemTest extends TestCase
{
    use CreatesPaymentWalletSchema;

    private User $customer;
    private User $courierUser;
    private User $otherCourierUser;
    private User $stranger;
    private Courier $courier;
    private Courier $otherCourier;
    private Shipment $shipment;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBaseTablesForWalletTests();

        if (! Schema::hasColumn('couriers', 'photo')) {
            Schema::table('couriers', function (Blueprint $table): void {
                $table->string('photo')->nullable();
            });
        }

        if (! Schema::hasTable('permissions')) {
            (require database_path('migrations/2026_04_17_071227_create_permission_tables.php'))->up();
        }
        foreach (glob(database_path('migrations/2026_11_25_00000*.php')) ?: [] as $file) {
            $migration = require $file;
            $migration->up();
        }

        $this->customer = User::create([
            'name' => 'Ayu Customer',
            'email' => 'ayu.call@example.com',
            'phone' => '081211112222',
            'avatar' => 'avatars/ayu.jpg',
            'phone_verified_at' => now(),
            'password' => 'secret123',
        ]);

        $this->courierUser = User::create([
            'name' => 'Bima Kurir',
            'email' => 'bima.call@example.com',
            'phone' => '081322223333',
            'avatar' => 'avatars/bima_user.jpg',
            'phone_verified_at' => now(),
            'password' => 'secret123',
        ]);

        $this->otherCourierUser = User::create([
            'name' => 'Doni Kurir Lain',
            'email' => 'doni.call@example.com',
            'phone' => '081344445555',
            'phone_verified_at' => now(),
            'password' => 'secret123',
        ]);

        $this->stranger = User::create([
            'name' => 'Penyusup',
            'email' => 'stranger.call@example.com',
            'phone' => '081999998888',
            'password' => 'secret123',
        ]);

        $this->courier = Courier::create([
            'user_id' => $this->courierUser->id,
            'phone' => '081322223333',
            'photo' => 'couriers/bima_profile.jpg',
            'vehicle_type' => 'motor',
            'is_verified' => true,
            'is_active' => true,
        ]);

        $this->otherCourier = Courier::create([
            'user_id' => $this->otherCourierUser->id,
            'phone' => '081344445555',
            'vehicle_type' => 'motor',
            'is_verified' => true,
            'is_active' => true,
        ]);

        $this->shipment = Shipment::create([
            'shipment_number' => 'CC-CALL-2026-001',
            'tracking_number' => 'RESI-CALL-001',
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
            'order_number' => 'CC-CALL-2026-001',
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

    public function test_customer_and_courier_call_lifecycle_signaling_duration_and_turn_ephemeral_credentials(): void
    {
        config([
            'voip.enabled' => true,
            'voip.stun_urls' => ['stun:stun.l.google.com:19302'],
            'voip.turn_urls' => ['turn:turn.neonflux.my.id:3478?transport=udp'],
            'voip.turn_shared_secret' => 'super-secret-turn-hmac-key',
            'voip.turn_ttl_seconds' => 600,
            'fcm.enabled' => true,
            'fcm.project_id' => 'city-courier-test',
            'fcm.static_access_token' => 'fcm-test-oauth',
            'queue.default' => 'sync',
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb' => [
                'driver' => 'reverb',
                'key' => 'cc-reverb-key',
                'secret' => 'cc-reverb-secret',
                'app_id' => 'cc-app',
                'options' => ['host' => 'ws.neonflux.my.id', 'port' => 443, 'scheme' => 'https'],
            ],
        ]);

        UserDeviceToken::create([
            'user_id' => $this->courierUser->id,
            'platform' => 'android',
            'fcm_token' => 'fcm-courier-token-1',
            'is_active' => true,
        ]);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/city-courier-test/messages:send' => Http::response(['name' => 'msg/1'], 200),
        ]);
        Event::fake([IncomingCallBroadcast::class, CallSessionUpdatedBroadcast::class, CallSignalBroadcast::class]);

        // Public realtime-config must never expose STUN/TURN secrets
        Sanctum::actingAs($this->customer);
        $rtRes = $this->getJson('/api/v1/chat/realtime-config')->assertOk();
        $rtRes->assertJsonMissingPath('data.ice_servers');

        // Customer initiates call with initial SDP offer
        $initRes = $this->postJson('/api/v1/calls/initiate', [
            'order_identifier' => $this->shipment->tracking_number,
            'offer' => ['type' => 'offer', 'sdp' => 'v=0-offer-sdp-customer'],
        ]);
        $initRes->assertStatus(201)
            ->assertJsonPath('data.call.status', CallSession::STATUS_RINGING)
            ->assertJsonPath('data.call.caller_id', $this->customer->id)
            ->assertJsonPath('data.call.receiver_id', $this->courierUser->id)
            ->assertJsonPath('data.call.order_number', 'CC-CALL-2026-001')
            ->assertJsonPath('data.call.caller.avatar', url('storage/avatars/ayu.jpg'))
            ->assertJsonPath('data.call.receiver.avatar', url('storage/couriers/bima_profile.jpg'));

        // Ephemeral HMAC TURN credentials verified; raw shared secret never exposed
        $iceServers = $initRes->json('data.ice_servers');
        $this->assertCount(2, $iceServers);
        $this->assertStringNotContainsString('super-secret-turn-hmac-key', json_encode($iceServers));
        $this->assertStringEndsWith(':' . $this->customer->id, $iceServers[1]['username']);

        $callId = (int) $initRes->json('data.call.id');
        Event::assertDispatched(IncomingCallBroadcast::class, fn ($e) => $e->callSession->id === $callId);

        // Verify data-only HIGH priority FCM incoming_call payload was sent
        Http::assertSent(function (HttpClientRequest $req): bool {
            $data = $req->data()['message']['data'] ?? [];
            $android = $req->data()['message']['android'] ?? [];

            return ($data['type'] ?? null) === 'incoming_call'
                && ($data['caller_name'] ?? null) === 'Ayu Customer'
                && ($android['priority'] ?? null) === 'HIGH'
                && ! isset($req->data()['message']['notification']);
        });

        // Duplicate concurrent call attempt blocked with 409
        $this->postJson('/api/v1/calls/initiate', [
            'order_identifier' => $this->order->id,
        ])->assertStatus(409)->assertJsonPath('error_code', 'CALL_ALREADY_ACTIVE');

        // Courier checks incoming call & reads initial offer signal
        Sanctum::actingAs($this->courierUser);
        $this->getJson('/api/v1/calls/incoming')
            ->assertOk()
            ->assertJsonPath('data.call.id', $callId);

        $signalsRes = $this->getJson("/api/v1/calls/{$callId}/signals?after_id=0")->assertOk();
        $this->assertCount(1, $signalsRes->json('data.signals'));
        $this->assertSame('offer', $signalsRes->json('data.signals.0.signal_type'));
        $lastSignalId = (int) $signalsRes->json('data.signals.0.id');

        // Reverb private-call.{id} authorization allowed for courier & customer, denied for stranger
        $this->postJson('/api/v1/chat/broadcasting/auth', [
            'socket_id' => '111.222',
            'channel_name' => 'private-call.' . $callId,
        ])->assertOk()->assertJsonStructure(['auth']);

        Sanctum::actingAs($this->stranger);
        $this->postJson('/api/v1/chat/broadcasting/auth', [
            'socket_id' => '111.222',
            'channel_name' => 'private-call.' . $callId,
        ])->assertStatus(403);

        Sanctum::actingAs($this->courierUser);

        // Courier accepts with SDP answer and sends an ICE candidate
        $this->postJson("/api/v1/calls/{$callId}/accept", [
            'answer' => ['type' => 'answer', 'sdp' => 'v=0-answer-sdp-courier'],
        ])
            ->assertOk()
            ->assertJsonPath('data.call.status', CallSession::STATUS_ACCEPTED);

        $this->postJson("/api/v1/calls/{$callId}/signals", [
            'signal_type' => CallSignal::TYPE_ICE_CANDIDATE,
            'payload' => ['candidate' => 'candidate:1 1 UDP 2122 192.168.1.2 50000 typ host', 'sdpMid' => '0', 'sdpMLineIndex' => 0],
        ])->assertStatus(201);

        Event::assertDispatched(CallSessionUpdatedBroadcast::class);
        Event::assertDispatched(CallSignalBroadcast::class, 2);

        // Customer polls only new signals after $lastSignalId -> gets answer + ICE candidate
        Sanctum::actingAs($this->customer);
        $custSignals = $this->getJson("/api/v1/calls/{$callId}/signals?after_id={$lastSignalId}")->assertOk();
        $this->assertCount(2, $custSignals->json('data.signals'));
        $this->assertSame('answer', $custSignals->json('data.signals.0.signal_type'));
        $this->assertSame('ice_candidate', $custSignals->json('data.signals.1.signal_type'));

        // Fast-forward answered_at by 25s and end call -> duration recorded & signals purged
        CallSession::query()->whereKey($callId)->update(['answered_at' => now()->subSeconds(25)]);
        $endRes = $this->postJson("/api/v1/calls/{$callId}/end")->assertOk();
        $endRes->assertJsonPath('data.call.status', CallSession::STATUS_ENDED);
        $this->assertGreaterThanOrEqual(25, (int) $endRes->json('data.call.duration'));
        $this->assertSame([], $endRes->json('data.ice_servers'));
        $this->assertDatabaseCount('call_signals', 0);

        // Reverse direction: Courier can initiate call back to Customer after previous ended
        Sanctum::actingAs($this->courierUser);
        $reverseRes = $this->postJson('/api/v1/calls/initiate', [
            'order_identifier' => $this->order->id,
        ]);
        $reverseRes->assertStatus(201)
            ->assertJsonPath('data.call.caller_id', $this->courierUser->id)
            ->assertJsonPath('data.call.receiver_id', $this->customer->id);
    }

    public function test_authorization_rejects_strangers_caller_self_accept_cancelled_or_reassigned_orders(): void
    {
        Sanctum::actingAs($this->stranger);
        $this->postJson('/api/v1/calls/initiate', [
            'order_identifier' => $this->order->id,
        ])->assertStatus(403);

        Sanctum::actingAs($this->customer);
        $callId = (int) $this->postJson('/api/v1/calls/initiate', [
            'order_identifier' => $this->order->id,
        ])->assertStatus(201)->json('data.call.id');

        // Caller cannot accept their own outgoing call
        $this->postJson("/api/v1/calls/{$callId}/accept")->assertStatus(403);

        // Unauthorized third user cannot view, accept, reject, end, or signal the call
        Sanctum::actingAs($this->stranger);
        $this->getJson("/api/v1/calls/{$callId}")->assertStatus(403);
        $this->postJson("/api/v1/calls/{$callId}/accept")->assertStatus(403);
        $this->postJson("/api/v1/calls/{$callId}/reject")->assertStatus(403);
        $this->postJson("/api/v1/calls/{$callId}/end")->assertStatus(403);
        $this->postJson("/api/v1/calls/{$callId}/signals", [
            'signal_type' => CallSignal::TYPE_ICE_CANDIDATE,
            'payload' => ['candidate' => 'x'],
        ])->assertStatus(403);

        // Courier rejects call cleanly -> frees participants
        Sanctum::actingAs($this->courierUser);
        $this->postJson("/api/v1/calls/{$callId}/reject")
            ->assertOk()
            ->assertJsonPath('data.call.status', CallSession::STATUS_REJECTED)
            ->assertJsonPath('data.call.duration', 0);

        // Sending signal after terminal status fails cleanly with 409
        $this->postJson("/api/v1/calls/{$callId}/signals", [
            'signal_type' => CallSignal::TYPE_ICE_CANDIDATE,
            'payload' => ['candidate' => 'x'],
        ])->assertStatus(409);

        // If courier on order is reassigned away, old courier cannot call customer via stale conversation
        $this->order->update(['courier_id' => $this->otherCourier->id]);
        $conv = Conversation::query()->where('order_id', $this->order->id)->firstOrFail();

        $this->postJson('/api/v1/calls/initiate', [
            'conversation_id' => $conv->id,
        ])->assertStatus(403);
    }

    public function test_unanswered_ringing_call_expires_to_missed_and_failed_end_status_is_supported(): void
    {
        Sanctum::actingAs($this->customer);
        $callId = (int) $this->postJson('/api/v1/calls/initiate', [
            'order_identifier' => $this->order->id,
        ])->assertStatus(201)->json('data.call.id');

        // Simulate ring timeout expired
        CallSession::query()->whereKey($callId)->update(['expires_at' => now()->subSeconds(5)]);

        Sanctum::actingAs($this->courierUser);
        $this->getJson('/api/v1/calls/incoming')
            ->assertOk()
            ->assertJsonPath('data.call', null);

        $this->getJson("/api/v1/calls/{$callId}")
            ->assertOk()
            ->assertJsonPath('data.call.status', CallSession::STATUS_MISSED)
            ->assertJsonPath('data.call.end_reason', 'missed_timeout');

        // Customer starts another call, then terminates due to ICE failure
        Sanctum::actingAs($this->customer);
        $call2Id = (int) $this->postJson('/api/v1/calls/initiate', [
            'order_identifier' => $this->order->id,
        ])->assertStatus(201)->json('data.call.id');

        $this->postJson("/api/v1/calls/{$call2Id}/end", [
            'status' => CallSession::STATUS_FAILED,
            'reason' => 'ice_connection_failed',
        ])
            ->assertOk()
            ->assertJsonPath('data.call.status', CallSession::STATUS_FAILED)
            ->assertJsonPath('data.call.end_reason', 'ice_connection_failed');
    }
}
