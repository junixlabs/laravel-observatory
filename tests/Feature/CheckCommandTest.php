<?php

namespace JunixLabs\Observatory\Tests\Feature;

use Illuminate\Support\Facades\Http;
use JunixLabs\Observatory\Tests\TestCase;

class CheckCommandTest extends TestCase
{
    public function test_check_command_exists(): void
    {
        $this->artisan('observatory:check')
            ->assertSuccessful();
    }

    public function test_check_command_shows_enabled_status(): void
    {
        config(['observatory.enabled' => true]);

        $this->artisan('observatory:check')
            ->expectsOutputToContain('[OK] Observatory is enabled')
            ->assertSuccessful();
    }

    public function test_check_command_shows_disabled_warning(): void
    {
        config(['observatory.enabled' => false]);

        $this->artisan('observatory:check')
            ->expectsOutputToContain('[WARN] Observatory is disabled')
            ->assertSuccessful();
    }

    public function test_check_command_shows_custom_project_name(): void
    {
        config(['observatory.project' => 'hub-admin']);

        $this->artisan('observatory:check')
            ->expectsOutputToContain('[OK] Project: hub-admin')
            ->assertSuccessful();
    }

    public function test_check_command_warns_generic_project_name(): void
    {
        config(['observatory.project' => 'laravel']);

        $this->artisan('observatory:check')
            ->expectsOutputToContain('Project name is generic')
            ->assertSuccessful();
    }

    public function test_check_command_shows_feature_toggles(): void
    {
        config(['observatory.inbound.enabled' => true]);
        config(['observatory.jobs.enabled' => false]);

        $this->artisan('observatory:check')
            ->expectsOutputToContain('[ON] inbound')
            ->expectsOutputToContain('[OFF] jobs')
            ->assertSuccessful();
    }

    public function test_check_command_shows_prometheus_exporter_info(): void
    {
        config(['observatory.exporter' => 'prometheus']);
        config(['observatory.prometheus.enabled' => false]);

        $this->artisan('observatory:check')
            ->expectsOutputToContain('Exporter: prometheus')
            ->expectsOutputToContain('Prometheus endpoint disabled')
            ->assertSuccessful();
    }

    public function test_check_command_fails_when_sidmonitor_api_key_missing(): void
    {
        config(['observatory.exporter' => 'sidmonitor']);
        config(['observatory.sidmonitor.api_key' => '']);

        $this->artisan('observatory:check')
            ->expectsOutputToContain('SIDMONITOR_API_KEY is not set')
            ->assertFailed();
    }

    public function test_check_command_tests_sidmonitor_connectivity_success(): void
    {
        config(['observatory.exporter' => 'sidmonitor']);
        config(['observatory.sidmonitor.api_key' => 'test-key-123']);
        config(['observatory.sidmonitor.endpoint' => 'https://api.test.com']);

        Http::fake([
            'api.test.com/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $this->artisan('observatory:check')
            ->expectsOutputToContain('[OK] API key configured')
            ->expectsOutputToContain('[OK] SidMonitor backend reachable')
            ->assertSuccessful();
    }

    public function test_check_command_warns_sidmonitor_non_200_response(): void
    {
        config(['observatory.exporter' => 'sidmonitor']);
        config(['observatory.sidmonitor.api_key' => 'test-key-123']);
        config(['observatory.sidmonitor.endpoint' => 'https://api.test.com']);

        Http::fake([
            'api.test.com/health' => Http::response('Server Error', 500),
        ]);

        $this->artisan('observatory:check')
            ->expectsOutputToContain('SidMonitor returned HTTP 500')
            ->assertSuccessful();
    }

    public function test_check_command_fails_sidmonitor_connectivity_error(): void
    {
        config(['observatory.exporter' => 'sidmonitor']);
        config(['observatory.sidmonitor.api_key' => 'test-key-123']);
        config(['observatory.sidmonitor.endpoint' => 'https://api.test.com']);

        Http::fake([
            'api.test.com/health' => function () {
                throw new \Exception('Connection refused');
            },
        ]);

        $this->artisan('observatory:check')
            ->expectsOutputToContain('Cannot reach SidMonitor')
            ->assertFailed();
    }

    public function test_check_command_shows_outbound_services(): void
    {
        config(['observatory.outbound.services' => [
            '*.stripe.com' => 'stripe',
            '*.sendgrid.com' => 'sendgrid',
        ]]);

        $this->artisan('observatory:check')
            ->expectsOutputToContain('2 services')
            ->expectsOutputToContain('*.stripe.com => stripe')
            ->assertSuccessful();
    }

    public function test_check_command_fails_for_invalid_log_channel(): void
    {
        config(['observatory.log_channel' => 'nonexistent_channel']);
        config(['logging.channels' => []]);

        $this->artisan('observatory:check')
            ->expectsOutputToContain("Log channel 'nonexistent_channel' not found")
            ->assertFailed();
    }
}
