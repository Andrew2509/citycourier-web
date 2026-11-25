<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicShippingRegionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('group')->default('general');
                $table->timestamps();
            });
        }

        Setting::set('rajaongkir_enabled', '1', 'shipping');
        Setting::set('rajaongkir_provider', 'komerce', 'shipping');
        Setting::set('rajaongkir_api_key', 'test-rajaongkir-key', 'shipping');
    }

    public function test_guest_can_access_public_region_endpoints_and_calculate_cost_without_authentication(): void
    {
        Http::fake([
            'https://rajaongkir.komerce.id/api/v1/destination/province' => Http::response([
                'meta' => ['status' => 'success'],
                'data' => [
                    ['id' => 11, 'name' => 'Jawa Timur'],
                ],
            ], 200),
            'https://rajaongkir.komerce.id/api/v1/destination/city/11' => Http::response([
                'meta' => ['status' => 'success'],
                'data' => [
                    ['id' => 444, 'name' => 'Surabaya', 'province_id' => 11, 'province_name' => 'Jawa Timur', 'type' => 'Kota', 'postal_code' => '60111'],
                ],
            ], 200),
            'https://rajaongkir.komerce.id/api/v1/destination/district/444' => Http::response([
                'meta' => ['status' => 'success'],
                'data' => [
                    ['id' => 3550, 'name' => 'Sukolilo', 'city_id' => 444],
                ],
            ], 200),
            'https://rajaongkir.komerce.id/api/v1/destination/sub-district/3550' => Http::response([
                'meta' => ['status' => 'success'],
                'data' => [
                    ['id' => 7001, 'name' => 'Menur Pumpungan', 'district_id' => 3550, 'zip_code' => '60118'],
                ],
            ], 200),
            'https://rajaongkir.komerce.id/api/v1/calculate/district/domestic-cost' => Http::response([
                'meta' => ['status' => 'success'],
                'data' => [
                    ['courier_code' => 'jne', 'courier_name' => 'JNE', 'service' => 'REG', 'cost' => 12000, 'etd' => '1-2 hari'],
                ],
            ], 200),
        ]);

        $this->getJson('/api/shipping/provinces')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.province_id', '11')
            ->assertJsonPath('data.0.province', 'Jawa Timur');

        $this->getJson('/api/shipping/cities?province_id=11')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.city_id', '444')
            ->assertJsonPath('data.0.city_name', 'Surabaya');

        $this->getJson('/api/shipping/districts?city_id=444')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.subdistrict_id', '3550')
            ->assertJsonPath('data.0.subdistrict_name', 'Sukolilo');

        $this->getJson('/api/shipping/subdistricts?district_id=3550')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', '7001')
            ->assertJsonPath('data.0.name', 'Menur Pumpungan');

        $this->postJson('/api/shipping/cost', [
            'origin' => '3550',
            'destination' => '3551',
            'weight' => 1000,
            'courier' => 'jne',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.code', 'jne');
    }

    public function test_public_region_endpoints_still_respect_admin_provider_toggle(): void
    {
        Setting::set('rajaongkir_enabled', '0', 'shipping');

        $this->getJson('/api/shipping/provinces')
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        $this->getJson('/api/shipping/cities?province_id=11')
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        $this->getJson('/api/shipping/districts?city_id=444')
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        $this->getJson('/api/shipping/subdistricts?district_id=3550')
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'PROVIDER_DISABLED');

        $this->postJson('/api/shipping/cost', [
            'origin' => '3550',
            'destination' => '3551',
            'weight' => 1000,
            'courier' => 'jne',
        ])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'PROVIDER_DISABLED');
    }
}
