<?php

namespace JunixLabs\Observatory\Tests\Unit;

use Illuminate\Support\Facades\Http;
use JunixLabs\Observatory\Exporters\SidMonitorExporter;
use JunixLabs\Observatory\Tests\TestCase;

class SidMonitorExporterTest extends TestCase
{
    protected SidMonitorExporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'observatory.exporter' => 'sidmonitor',
            'observatory.sidmonitor.endpoint' => 'https://api.sidmonitor.test',
            'observatory.sidmonitor.api_key' => 'test-api-key-123',
            'observatory.sidmonitor.timeout' => 5,
            'observatory.sidmonitor.batch.size' => 100,
            'observatory.sidmonitor.batch.interval' => 10,
            'observatory.sidmonitor.batch.max_buffer_size' => 1000,
            'observatory.circuit_breaker.threshold' => 3,
            'observatory.circuit_breaker.cooldown' => 30,
        ]);

        $this->exporter = new SidMonitorExporter($this->app);
    }

    public function test_records_inbound_to_buffer(): void
    {
        $this->exporter->recordInbound([
            'method' => 'GET',
            'uri' => '/api/users',
            'route' => 'api.users.index',
            'status_code' => 200,
            'duration' => 0.150,
        ]);

        $output = json_decode($this->exporter->getOutput(), true);

        $this->assertEquals(1, $output['buffer']['inbound']);
        $this->assertEquals(0, $output['buffer']['outbound']);
        $this->assertEquals(0, $output['buffer']['jobs']);
        $this->assertEquals(0, $output['buffer']['scheduled_tasks']);
    }

    public function test_records_outbound_to_buffer(): void
    {
        $this->exporter->recordOutbound([
            'method' => 'POST',
            'host' => 'api.example.com',
            'path' => '/v1/charge',
            'status_code' => 201,
            'duration' => 0.250,
        ]);

        $output = json_decode($this->exporter->getOutput(), true);

        $this->assertEquals(0, $output['buffer']['inbound']);
        $this->assertEquals(1, $output['buffer']['outbound']);
        $this->assertEquals(0, $output['buffer']['jobs']);
        $this->assertEquals(0, $output['buffer']['scheduled_tasks']);
    }

    public function test_records_job_to_buffer(): void
    {
        $this->exporter->recordJob([
            'job_name' => 'App\\Jobs\\SendEmail',
            'queue' => 'default',
            'status' => 'processed',
            'duration' => 1.5,
        ]);

        $output = json_decode($this->exporter->getOutput(), true);

        $this->assertEquals(0, $output['buffer']['inbound']);
        $this->assertEquals(0, $output['buffer']['outbound']);
        $this->assertEquals(1, $output['buffer']['jobs']);
        $this->assertEquals(0, $output['buffer']['scheduled_tasks']);
    }

    public function test_records_scheduled_task_to_buffer(): void
    {
        $this->exporter->recordScheduledTask([
            'command' => 'inspire',
            'description' => 'Display an inspiring quote',
            'expression' => '* * * * *',
            'status' => 'completed',
            'duration_ms' => 120,
        ]);

        $output = json_decode($this->exporter->getOutput(), true);

        $this->assertEquals(0, $output['buffer']['inbound']);
        $this->assertEquals(0, $output['buffer']['outbound']);
        $this->assertEquals(0, $output['buffer']['jobs']);
        $this->assertEquals(1, $output['buffer']['scheduled_tasks']);
    }

    public function test_auto_flushes_when_batch_size_reached(): void
    {
        Http::fake([
            'api.sidmonitor.test/*' => Http::response(['status' => 'ok'], 200),
        ]);

        // Set a small batch size to trigger auto-flush
        config(['observatory.sidmonitor.batch.size' => 5]);
        $exporter = new SidMonitorExporter($this->app);

        for ($i = 0; $i < 5; $i++) {
            $exporter->recordInbound([
                'method' => 'GET',
                'uri' => '/api/test/' . $i,
                'route' => 'api.test',
                'status_code' => 200,
                'duration' => 0.1,
            ]);
        }

        // After reaching batch size, buffer should be flushed (emptied)
        $output = json_decode($exporter->getOutput(), true);
        $this->assertEquals(0, $output['buffer']['inbound']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.sidmonitor.test/api/ingest/batch'
                && $request->hasHeader('X-API-Key', 'test-api-key-123');
        });
    }

    public function test_circuit_breaker_opens_after_threshold_failures(): void
    {
        Http::fake([
            'api.sidmonitor.test/*' => Http::response(['error' => 'server error'], 500),
        ]);

        config([
            'observatory.sidmonitor.batch.size' => 1,
            'observatory.circuit_breaker.threshold' => 3,
            'observatory.circuit_breaker.cooldown' => 60,
        ]);
        $exporter = new SidMonitorExporter($this->app);

        // Trigger 3 failures (threshold) by recording inbound entries one at a time
        for ($i = 0; $i < 3; $i++) {
            $exporter->recordInbound([
                'method' => 'GET',
                'uri' => '/fail/' . $i,
                'status_code' => 200,
                'duration' => 0.1,
            ]);
        }

        $output = json_decode($exporter->getOutput(), true);

        $this->assertEquals(3, $output['circuit_breaker']['consecutive_failures']);
        $this->assertTrue($output['circuit_breaker']['is_open']);
    }

    public function test_circuit_breaker_allows_retry_after_cooldown(): void
    {
        $callCount = 0;

        Http::fake(function () use (&$callCount) {
            $callCount++;

            // First 3 calls fail (opening circuit), subsequent calls succeed
            if ($callCount <= 3) {
                return Http::response(['error' => 'server error'], 500);
            }

            return Http::response(['status' => 'ok'], 200);
        });

        config([
            'observatory.sidmonitor.batch.size' => 1,
            'observatory.circuit_breaker.threshold' => 3,
            'observatory.circuit_breaker.cooldown' => 1, // 1 second cooldown
        ]);
        $exporter = new SidMonitorExporter($this->app);

        // Trigger enough failures to open the circuit
        for ($i = 0; $i < 3; $i++) {
            $exporter->recordInbound([
                'method' => 'GET',
                'uri' => '/fail/' . $i,
                'status_code' => 200,
                'duration' => 0.1,
            ]);
        }

        $output = json_decode($exporter->getOutput(), true);
        $this->assertTrue($output['circuit_breaker']['is_open']);

        // Wait for cooldown to expire
        sleep(2);

        $exporter->recordInbound([
            'method' => 'GET',
            'uri' => '/retry',
            'status_code' => 200,
            'duration' => 0.1,
        ]);

        // Manually flush to trigger the half-open retry
        $exporter->flush();

        $output = json_decode($exporter->getOutput(), true);

        // After successful retry, circuit breaker should be reset
        $this->assertEquals(0, $output['circuit_breaker']['consecutive_failures']);
        $this->assertFalse($output['circuit_breaker']['is_open']);
    }

    public function test_buffer_trimming_when_exceeding_max_buffer_size(): void
    {
        Http::fake([
            'api.sidmonitor.test/*' => Http::response(['error' => 'server error'], 500),
        ]);

        config([
            'observatory.sidmonitor.batch.size' => 5,
            'observatory.sidmonitor.batch.max_buffer_size' => 10,
            'observatory.circuit_breaker.threshold' => 1,
            'observatory.circuit_breaker.cooldown' => 300,
        ]);
        $exporter = new SidMonitorExporter($this->app);

        // Fill the buffer past batch size to trigger a flush that fails,
        // which opens the circuit breaker and trims the buffer
        for ($i = 0; $i < 5; $i++) {
            $exporter->recordInbound([
                'method' => 'GET',
                'uri' => '/item/' . $i,
                'status_code' => 200,
                'duration' => 0.1,
            ]);
        }

        // Circuit is now open. Add more items — they will not be flushed
        // but buffer trimming will occur when auto-flush detects an open circuit.
        for ($i = 0; $i < 20; $i++) {
            $exporter->recordInbound([
                'method' => 'GET',
                'uri' => '/overflow/' . $i,
                'status_code' => 200,
                'duration' => 0.1,
            ]);
        }

        // Force a flush attempt, which will trim due to open circuit
        $exporter->flush();

        $output = json_decode($exporter->getOutput(), true);

        // Buffer should be trimmed to max_buffer_size (10)
        $this->assertLessThanOrEqual(10, $output['buffer']['inbound']);
    }

    public function test_get_output_returns_json_status(): void
    {
        $this->exporter->recordInbound([
            'method' => 'GET',
            'uri' => '/api/status',
            'status_code' => 200,
            'duration' => 0.05,
        ]);

        $raw = $this->exporter->getOutput();
        $output = json_decode($raw, true);

        $this->assertNotNull($output, 'getOutput() must return valid JSON');
        $this->assertEquals('sidmonitor', $output['exporter']);
        $this->assertEquals('https://api.sidmonitor.test', $output['endpoint']);
        $this->assertArrayHasKey('buffer', $output);
        $this->assertArrayHasKey('inbound', $output['buffer']);
        $this->assertArrayHasKey('outbound', $output['buffer']);
        $this->assertArrayHasKey('jobs', $output['buffer']);
        $this->assertArrayHasKey('scheduled_tasks', $output['buffer']);
        $this->assertArrayHasKey('circuit_breaker', $output);
        $this->assertArrayHasKey('consecutive_failures', $output['circuit_breaker']);
        $this->assertArrayHasKey('is_open', $output['circuit_breaker']);
    }

    public function test_empty_api_key_prevents_sending(): void
    {
        Http::fake();

        config([
            'observatory.sidmonitor.api_key' => '',
            'observatory.sidmonitor.batch.size' => 1,
        ]);
        $exporter = new SidMonitorExporter($this->app);

        $exporter->recordInbound([
            'method' => 'GET',
            'uri' => '/api/test',
            'status_code' => 200,
            'duration' => 0.1,
        ]);

        // Manually flush to be sure
        $exporter->flush();

        // No HTTP requests should have been sent
        Http::assertNothingSent();

        // Buffer should be cleared even without sending
        $output = json_decode($exporter->getOutput(), true);
        $this->assertEquals(0, $output['buffer']['inbound']);
    }
}
