<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Services\ShipmentNotificationService;
use App\Services\WhatsAppManager;
use Tests\TestCase;

class ShipmentWhatsAppNotificationTest extends TestCase
{
    public function test_shipment_generates_correct_tracking_and_qr_urls()
    {
        $shipment = new Shipment([
            'shipment_number'       => 'SHP-20261028-123456',
            'tracking_number'       => 'CC261028E5F6',
            'customer_name'         => 'Princenton',
            'customer_phone'        => '081343323155',
            'sender_name'           => 'brightness',
            'sender_phone'          => '081343323155',
            'sender_address'        => 'Sidoarjo',
            'origin_name'           => 'Sidoarjo',
            'receiver_name'         => 'andrew',
            'receiver_phone'        => '081343323155',
            'receiver_address'      => 'Surabaya',
            'destination_name'      => 'Surabaya',
            'package_weight'        => 1.5,
            'courier_code'          => 'motor',
            'courier_name'          => 'Motor',
            'courier_service'       => 'Instant',
            'vehicle'               => 'motor',
            'shipping_cost'         => 12600,
            'total_cost'            => 12600,
            'payment_method'        => 'COD',
            'status'                => 'pending',
            'whatsapp_notification' => 'unsent',
        ]);

        $this->assertEquals('CC261028E5F6', $shipment->tracking_number);
        $this->assertEquals('Motor', $shipment->vehicle_display_name);
        $this->assertEquals('Rp12.600', $shipment->formatted_total_cost);
        $this->assertStringContainsString('/track/CC261028E5F6', $shipment->tracking_url);
        $this->assertStringContainsString('/track/CC261028E5F6/qrcode.png', $shipment->qr_code_url);
    }

    public function test_notification_service_prevents_duplicate_sends()
    {
        $mockWa = $this->createMock(WhatsAppManager::class);
        $mockWa->expects($this->never())->method('sendMessage');

        $service = new ShipmentNotificationService($mockWa);

        $shipment = new Shipment([
            'tracking_number'       => 'CC261028E5F6',
            'sender_phone'          => '081343323155',
            'whatsapp_notification' => 'sent',
        ]);

        $result = $service->sendOrderSuccessNotification($shipment, false);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['already_sent']);
    }

    public function test_notification_service_formats_message_correctly()
    {
        $mockWa = $this->createMock(WhatsAppManager::class);
        $capturedMessage = null;
        $capturedMedia = null;

        $mockWa->expects($this->once())
            ->method('sendMessage')
            ->willReturnCallback(function ($to, $message, $mediaUrl = null) use (&$capturedMessage, &$capturedMedia) {
                $capturedMessage = $message;
                $capturedMedia = $mediaUrl;
                return ['success' => true];
            });

        $service = new ShipmentNotificationService($mockWa);

        $shipment = new Shipment([
            'shipment_number'       => 'SHP-TEST-001',
            'tracking_number'       => 'CC261028E5F6',
            'customer_name'         => 'Princenton',
            'customer_phone'        => '081343323155',
            'sender_name'           => 'brightness',
            'sender_phone'          => '081343323155',
            'sender_address'        => 'Wedoro, Waru, Sidoarjo',
            'origin_name'           => 'Sidoarjo',
            'receiver_name'         => 'andrew',
            'receiver_phone'        => '081343323155',
            'receiver_address'      => 'Untag Surabaya',
            'destination_name'      => 'Surabaya',
            'package_weight'        => 1.5,
            'courier_code'          => 'motor',
            'courier_name'          => 'Motor',
            'courier_service'       => 'Instant',
            'vehicle'               => 'motor',
            'shipping_cost'         => 12600,
            'total_cost'            => 12600,
            'payment_method'        => 'COD',
            'status'                => 'pending',
            'whatsapp_notification' => 'unsent',
        ]);

        $result = $service->sendOrderSuccessNotification($shipment);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Halo, Princenton 👋', $capturedMessage);
        $this->assertStringContainsString('CC261028E5F6', $capturedMessage);
        $this->assertStringContainsString('brightness - Sidoarjo', $capturedMessage);
        $this->assertStringContainsString('andrew - Surabaya', $capturedMessage);
        $this->assertStringContainsString('Motor', $capturedMessage);
        $this->assertStringContainsString('Rp12.600', $capturedMessage);
        $this->assertStringContainsString('Menunggu Kurir', $capturedMessage);
        $this->assertStringContainsString('/track/CC261028E5F6', $capturedMessage);
        $this->assertStringContainsString('/track/CC261028E5F6/qrcode.png', $capturedMedia);
    }
}
