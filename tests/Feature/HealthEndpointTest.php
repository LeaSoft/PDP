<?php

namespace Tests\Feature;

use App\Repositories\HealthRepository;
use RuntimeException;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_answers_ok_with_the_database_and_the_deployed_version(): void
    {
        config(['app.version' => 'b6410e52e6d4']);

        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('checks.database', 'ok');
        $response->assertJsonPath('version', 'b6410e52e6d4');
        $response->assertJsonStructure(['status', 'checks' => ['database'], 'version', 'timestamp']);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_health_reports_no_version_outside_a_deploy(): void
    {
        config(['app.version' => null]);

        $this->getJson('/api/health')->assertOk()->assertJsonPath('version', null);
    }

    public function test_health_starts_no_session_and_sets_no_cookies(): void
    {
        $response = $this->get('/api/health');

        $response->assertOk();
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_health_answers_503_without_the_reason_when_the_database_is_down(): void
    {
        $this->app->instance(HealthRepository::class, new class extends HealthRepository
        {
            public function pingDatabase(): void
            {
                throw new RuntimeException('SQLSTATE[HY000] [2002] Connection refused');
            }
        });

        $response = $this->getJson('/api/health');

        $response->assertStatus(503);
        $response->assertJsonPath('status', 'degraded');
        $response->assertJsonPath('checks.database', 'error');
        $response->assertDontSee('Connection refused');
    }
}
