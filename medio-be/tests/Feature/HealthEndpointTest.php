<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_reports_service_status_and_request_id(): void
    {
        $response = $this->getJson('/api/health');

        $response
            ->assertOk()
            ->assertHeader('X-Request-ID')
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database', 'ok')
            ->assertJsonStructure([
                'status',
                'time',
                'app_env',
                'database',
            ]);
    }
}
