<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\User;
use App\Modules\Chat\Models\WhatsappContact;
use App\Modules\Chat\Models\WhatsappMessageLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\CreatesPaymentWalletSchema;

class WhatsAppCloudSystemTest extends TestCase
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
        if (! Schema::hasTable('shipment_logs')) {
            Schema::create('shipment_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
                $table->unsignedBigInteger('courier_id')->nullable();
                $table->string('status');
                $table->string('location')->nullable();
                $table->text('description')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('courier_locations')) {
            Schema::create('courier_locations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('courier_id');
                $table->unsignedBigInteger('shipment_id')->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->decimal('accuracy', 8, 2)->nullable();
                $table->timestamps();
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
            'sender_name' => 'Ayu Pengirim',
            'sender_phone' => '081244445555',
            'sender_address' => 'Jl. Melati No. 1',
            'receiver_name' => 'Tio Penerima',
            'receiver_phone' => '081455556666',
            'receiver_address' => 'Jl. Kenanga No. 9',
            'package_weight' => 1.5,
            'total_cost' => 20000,
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
            'price' => 20000,
            'status' => 'delivering',
        ]);
    }

    public function test_internal_chat_and_voip_endpoints_are_removed(): void
    {
        Sanctum::actingAs($this->customer);

        $this->getJson('/api/v1/chat/conversations')->assertStatus(404);
        $this->postJson('/api/v1/chat/conversations/by-order/' . $this->order->id)->assertStatus(404);
        $this->getJson('/api/v1/chat/unread-count')->assertStatus(404);
        $this->getJson('/api/v1/chat/whatsapp/status')->assertStatus(404);
        $this->getJson('/api/v1/calls/incoming')->assertStatus(404);
        $this->postJson('/api/v1/calls/initiate', ['order_id' => $this->order->id])->assertStatus(404);
        $this->postJson('/api/v1/devices/fcm-token', ['fcm_token' => 'tok'])->assertStatus(404);
    }

    public function test_contact_phones_are_restricted_to_authorized_order_participants(): void
    {
        // 1. Guest (unauthenticated) on tracking endpoints gets redacted phones
        $this->getJson('/api/shipments/track/RESI-CC-001')
            ->assertOk()
            ->assertJsonPath('data.contacts_authorized', false)
            ->assertJsonPath('data.customer_phone', null)
            ->assertJsonPath('data.sender_phone', null)
            ->assertJsonPath('data.receiver_phone', null)
            ->assertJsonPath('data.courier_phone', null)
            ->assertJsonPath('data.courier.phone', null);

        $this->getJson('/api/shipments/RESI-CC-001/tracking')
            ->assertOk()
            ->assertJsonPath('data.contacts_authorized', false)
            ->assertJsonPath('data.customer_phone', null)
            ->assertJsonPath('data.sender_phone', null)
            ->assertJsonPath('data.receiver_phone', null)
            ->assertJsonPath('data.courier_phone', null)
            ->assertJsonPath('data.courier.phone', null);

        $webGuestHtml = $this->get('/track/RESI-CC-001')->assertOk()->getContent();
        $this->assertStringNotContainsString('href="tel:', $webGuestHtml);
        $this->assertStringNotContainsString('081244445555', $webGuestHtml);
        $this->assertStringNotContainsString('081455556666', $webGuestHtml);

        // 2. Unrelated authenticated user gets 403 on shipment detail & available orders, and redacted tracking
        Sanctum::actingAs($this->stranger);
        $this->getJson('/api/shipments/' . $this->shipment->id)->assertStatus(403);
        $this->getJson('/api/orders/available')->assertStatus(403);
        $this->getJson('/api/shipments/RESI-CC-001/tracking')
            ->assertOk()
            ->assertJsonPath('data.contacts_authorized', false)
            ->assertJsonPath('data.sender_phone', null)
            ->assertJsonPath('data.courier_phone', null);

        // 3. Owner customer receives authorized contact phones including courier
        Sanctum::actingAs($this->customer);
        $this->getJson('/api/shipments/' . $this->shipment->id)
            ->assertOk()
            ->assertJsonPath('data.contacts_authorized', true)
            ->assertJsonPath('data.courier_phone', '081322223333');

        $this->getJson('/api/shipments/RESI-CC-001/tracking')
            ->assertOk()
            ->assertJsonPath('data.contacts_authorized', true)
            ->assertJsonPath('data.customer_phone', '081211112222')
            ->assertJsonPath('data.sender_phone', '081244445555')
            ->assertJsonPath('data.receiver_phone', '081455556666')
            ->assertJsonPath('data.courier_phone', '081322223333')
            ->assertJsonPath('data.courier.phone', '081322223333');

        // 4. Assigned courier receives authorized customer/sender/receiver phones
        Sanctum::actingAs($this->courierUser);
        $this->getJson('/api/shipments/' . $this->shipment->id)
            ->assertOk()
            ->assertJsonPath('data.contacts_authorized', true)
            ->assertJsonPath('data.customer_phone', '081211112222')
            ->assertJsonPath('data.sender_phone', '081244445555')
            ->assertJsonPath('data.receiver_phone', '081455556666');

        $this->getJson('/api/orders/active')
            ->assertOk()
            ->assertJsonPath('data.customer_phone', '081211112222')
            ->assertJsonPath('data.sender_phone', '081244445555')
            ->assertJsonPath('data.receiver_phone', '081455556666');
    }

    public function test_whatsapp_webhook_verify_signature_stop_optout_and_monotonic_status(): void
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

        WhatsappContact::create([
            'user_id' => $this->customer->id,
            'wa_phone_e164' => '6281211112222',
            'opt_in_status' => WhatsappContact::OPT_IN,
        ]);

        WhatsappMessageLog::create([
            'wa_message_id' => 'wamid.OUTBOUND_1',
            'direction' => WhatsappMessageLog::DIRECTION_OUTBOUND,
            'event_hash' => hash('sha256', 'outbound:wamid.OUTBOUND_1'),
            'signature_valid' => true,
            'http_status' => 200,
            'processing_status' => WhatsappMessageLog::PROCESS_PROCESSED,
            'payload' => ['wa_status' => 'accepted'],
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

        // Status webhook updates outbound log monotonically (read then older delivered is ignored)
        $postSigned([
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [
                            ['id' => 'wamid.OUTBOUND_1', 'status' => 'read', 'timestamp' => (string) now()->timestamp],
                            ['id' => 'wamid.OUTBOUND_1', 'status' => 'delivered', 'timestamp' => (string) now()->subMinute()->timestamp],
                        ],
                    ],
                ]],
            ]],
        ])->assertOk();

        $outbound = WhatsappMessageLog::query()
            ->where('direction', WhatsappMessageLog::DIRECTION_OUTBOUND)
            ->where('wa_message_id', 'wamid.OUTBOUND_1')
            ->firstOrFail();
        $this->assertSame('read', $outbound->payload['wa_status'] ?? null);

        // STOP keyword marks contact opted out
        $postSigned([
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '6281211112222',
                            'id' => 'wamid.STOP_1',
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => 'STOP'],
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->assertSame(
            WhatsappContact::OPT_OUT,
            WhatsappContact::query()->where('user_id', $this->customer->id)->value('opt_in_status')
        );
    }

    public function test_admin_can_configure_test_and_toggle_meta_whatsapp_cloud_api_in_providers_panel(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $opsRole = Role::firstOrCreate(['name' => 'ops-admin', 'guard_name' => 'web']);
        $viewPerm = Permission::firstOrCreate(['name' => 'settings.view', 'guard_name' => 'web']);
        $editPerm = Permission::firstOrCreate(['name' => 'settings.edit', 'guard_name' => 'web']);
        $opsRole->givePermissionTo([$viewPerm, $editPerm]);

        $opsAdmin = User::create([
            'name' => 'Ops Provider Admin',
            'email' => 'ops.wa@city.test',
            'role' => 'admin',
        ]);
        $opsAdmin->assignRole($opsRole);

        config([
            'whatsapp.enabled' => false,
            'whatsapp.phone_number_id' => '',
            'whatsapp.waba_id' => '',
            'whatsapp.access_token' => '',
            'whatsapp.app_secret' => '',
            'whatsapp.verify_token' => '',
        ]);

        // Non-admin cannot access admin WA Cloud settings or toggle
        $this->actingAs($this->stranger)
            ->postJson('/admin/settings/whatsapp-cloud', ['whatsapp_cloud_base_url' => 'https://graph.facebook.com', 'whatsapp_cloud_api_version' => 'v21.0'])
            ->assertStatus(403);
        $this->actingAs($this->stranger)
            ->postJson('/admin/providers/whatsapp_cloud/toggle', ['enabled' => 1])
            ->assertStatus(403);

        $this->actingAs($opsAdmin);
        $providersPage = $this->get('/admin/providers')->assertOk();
        $providersPage->assertSee('Meta WhatsApp Business Cloud API');
        $providersPage->assertSee('/admin/providers/whatsapp_cloud/toggle');

        $manualToken = 'EAAG_MANUAL_DB_TOKEN_SECRET_991122';
        $manualAppSecret = 'manual-meta-app-secret-778899';
        $manualVerifyToken = 'manual-verify-token-445566';

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

        Http::fake([
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

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.providers.whatsapp_cloud', true);

        // Verify Meta GET webhook handshake & signed POST webhook work purely from DB secrets
        $this->get('/api/v1/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=' . $manualVerifyToken . '&hub.challenge=DB_CHAL_OK')
            ->assertOk()
            ->assertSeeText('DB_CHAL_OK');

        $this->actingAs($opsAdmin)
            ->postJson('/admin/providers/whatsapp_cloud/toggle', ['enabled' => 0])
            ->assertOk()
            ->assertJsonPath('provider', 'whatsapp_cloud')
            ->assertJsonPath('enabled', false);

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.providers.whatsapp_cloud', false);

        $this->actingAs($opsAdmin)
            ->post('/admin/settings/whatsapp-cloud', [
                'whatsapp_cloud_base_url' => 'https://graph.facebook.com',
                'whatsapp_cloud_api_version' => 'v21.0',
                'clear_whatsapp_cloud_secrets' => '1',
            ])->assertRedirect();

        $this->assertSame('', Setting::get('whatsapp_cloud_access_token'));
    }

    public function test_admin_send_wa_test_message_131037_and_validation_errors(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $admin = User::create([
            'name' => 'Super Admin WA Test',
            'email' => 'wa.test.admin@city.test',
            'role' => 'admin',
        ]);
        $admin->assignRole('super-admin');

        config([
            'whatsapp.enabled' => true,
            'whatsapp.phone_number_id' => '998811223344',
            'whatsapp.access_token' => 'test-token-wa-diag',
            'whatsapp.app_secret' => 'test-secret-wa-diag',
            'whatsapp.verify_token' => 'test-verify-wa-diag',
        ]);

        Http::fake([
            'https://graph.facebook.com/v21.0/998811223344/messages' => Http::sequence()
                ->push(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.TEST_HELLO_1']]], 200)
                ->push([
                    'error' => [
                        'message' => 'WhatsApp provided number needs display name approval before message can be sent.',
                        'code' => 131037,
                    ],
                ], 400),
        ]);

        $this->actingAs($admin)
            ->postJson('/admin/settings/whatsapp-cloud/test', [
                'test_action' => 'send_test',
                'whatsapp_cloud_test_phone' => '081234567890',
                'whatsapp_cloud_test_mode' => 'hello_world',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $res131037 = $this->actingAs($admin)
            ->postJson('/admin/settings/whatsapp-cloud/test', [
                'test_action' => 'send_test',
                'whatsapp_cloud_test_phone' => '081234567890',
                'whatsapp_cloud_test_mode' => 'hello_world',
            ])
            ->assertStatus(400)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('131037', (string) $res131037->json('message'));

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
}
