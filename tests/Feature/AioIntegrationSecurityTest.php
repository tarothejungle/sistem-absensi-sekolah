<?php

namespace Tests\Feature;

use App\Models\IntegrationApiAuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AioIntegrationSecurityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_previous_key_is_accepted_during_rotation_and_request_is_audited(): void
    {
        config([
            'services.aio.api_key' => 'current-test-key',
            'services.aio.previous_api_key' => 'previous-test-key',
            'services.aio.require_https' => false,
            'school.name' => 'Sekolah Integrasi Test',
            'school.city' => 'Kota Test',
        ]);

        $response = $this->withHeader('X-API-Key', 'previous-test-key')
            ->getJson(route('api.aio.attendance-report', ['month' => 8, 'year' => 2026]));

        $response->assertOk()
            ->assertJsonPath('data.school.name', 'Sekolah Integrasi Test')
            ->assertJsonPath('data.school.city', 'Kota Test')
            ->assertHeader('X-Request-ID');

        $this->assertDatabaseHas('integration_api_audit_logs', [
            'integration' => 'aio',
            'key_version' => 'previous',
            'response_status' => 200,
        ]);
    }

    public function test_aio_rejects_plain_http_when_https_is_required(): void
    {
        config([
            'services.aio.api_key' => 'current-test-key',
            'services.aio.previous_api_key' => '',
            'services.aio.require_https' => true,
        ]);

        $this->withHeader('X-API-Key', 'current-test-key')
            ->getJson(route('api.aio.attendance-report', ['month' => 8, 'year' => 2026]))
            ->assertStatus(426)
            ->assertJsonPath('error.code', 'https_required');
    }
}
