<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * GET /api/health dipakai untuk memastikan server produksi sudah menjalankan
 * commit terbaru. Endpoint harus publik, ringan (tanpa database), dan tidak
 * membocorkan kredensial.
 */
class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_is_public_and_reports_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure([
                'status',
                'app',
                'env',
                'php',
                'time',
                'deploy' => ['commit', 'subject', 'committed_at', 'source'],
            ]);
    }

    public function test_health_reports_commit_from_app_commit_config(): void
    {
        config(['app.commit' => 'abc1234']);

        $this->getJson('/api/health')
            ->assertStatus(200)
            ->assertJsonPath('deploy.commit', 'abc1234')
            ->assertJsonPath('deploy.source', 'env');
    }

    public function test_health_never_exposes_provider_credentials(): void
    {
        config(['app.commit' => 'abc1234']);

        $body = $this->getJson('/api/health')->getContent();

        $this->assertStringNotContainsString('IPAYMU', $body);
        $this->assertStringNotContainsString('api_key', $body);
        $this->assertStringNotContainsString('APP_KEY', $body);
    }
}
