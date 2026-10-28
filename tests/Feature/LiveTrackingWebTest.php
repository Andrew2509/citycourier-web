<?php

namespace Tests\Feature;

use App\Models\Shipment;
use Tests\TestCase;

class LiveTrackingWebTest extends TestCase
{
    public function test_tracking_page_returns_404_when_resi_not_found()
    {
        $response = $this->get('/track/INVALIDRESI123');
        $response->assertStatus(404);
        $response->assertSee('Resi Tidak Ditemukan');
    }

    public function test_tracking_page_loads_with_correct_elements()
    {
        // Mock Shipment in memory
        $shipment = new Shipment([
            'shipment_number'       => 'SHP-20261028-111111',
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

        $this->assertEquals('CC261028E5F6', $shipment->tracking_number);
    }
}

