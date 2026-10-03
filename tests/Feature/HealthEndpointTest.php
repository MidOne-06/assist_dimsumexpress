<?php

namespace Tests\Feature;

use App\Services\SchedulerHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_database_and_scheduler_status_without_caching(): void
    {
        Storage::fake('local');
        app(SchedulerHeartbeat::class)->touch();

        $response = $this->get(route('health'));

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.scheduler', 'ok');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
    }

    public function test_health_endpoint_fails_when_the_scheduler_signal_is_stale(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('health/scheduler-heartbeat.json', json_encode([
            'updated_at' => now()->subMinutes(4)->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        $this->get(route('health'))
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.scheduler', 'stale');
    }
}
