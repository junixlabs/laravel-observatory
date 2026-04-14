<?php

namespace JunixLabs\Observatory\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckCommand extends Command
{
    protected $signature = 'observatory:check';

    protected $description = 'Validate Observatory configuration and test connectivity';

    public function handle(): int
    {
        $this->info('Observatory Configuration Check');
        $this->newLine();

        $hasError = false;

        // 1. Basic config
        $enabled = config('observatory.enabled', true);
        $this->line($enabled
            ? '  [OK] Observatory is enabled'
            : '  [WARN] Observatory is disabled');

        // 2. Project identity
        $project = config('observatory.project', config('app.name'));
        if ($project && $project !== 'Laravel' && $project !== 'laravel') {
            $this->line("  [OK] Project: {$project}");
        } else {
            $this->warn('  [WARN] Project name is generic. Set OBSERVATORY_PROJECT in .env');
        }

        $environment = config('observatory.labels.environment', config('app.env'));
        $this->line("  [OK] Environment: {$environment}");

        // 3. Log channel
        $channel = config('observatory.log_channel', 'observatory');
        $channelConfig = config("logging.channels.{$channel}");
        if ($channelConfig) {
            $this->line("  [OK] Log channel: {$channel}");
        } else {
            $this->error("  [FAIL] Log channel '{$channel}' not found in logging config");
            $hasError = true;
        }

        // 4. Features
        $this->newLine();
        $this->info('Features:');

        $features = [
            'inbound' => config('observatory.inbound.enabled', true),
            'outbound' => config('observatory.outbound.enabled', true),
            'jobs' => config('observatory.jobs.enabled', true),
            'scheduled_tasks' => config('observatory.scheduled_tasks.enabled', true),
            'exceptions' => config('observatory.exceptions.enabled', true),
            'request_id' => config('observatory.request_id.enabled', true),
        ];

        foreach ($features as $name => $status) {
            $icon = $status ? 'ON' : 'OFF';
            $this->line("  [{$icon}] {$name}");
        }

        // 5. Exporter
        $this->newLine();
        $exporter = config('observatory.exporter', 'prometheus');
        $this->info("Exporter: {$exporter}");

        if ($exporter === 'sidmonitor') {
            $endpoint = config('observatory.sidmonitor.endpoint');
            $apiKey = config('observatory.sidmonitor.api_key');

            if (empty($apiKey)) {
                $this->error('  [FAIL] SIDMONITOR_API_KEY is not set');
                $hasError = true;
            } else {
                $this->line('  [OK] API key configured');
                $this->line("  [OK] Endpoint: {$endpoint}");

                // Test connectivity
                $this->line('  Testing connectivity...');

                try {
                    $response = Http::timeout(5)
                        ->withHeaders(['X-API-Key' => $apiKey])
                        ->get("{$endpoint}/health");

                    if ($response->successful()) {
                        $this->line('  [OK] SidMonitor backend reachable');
                    } else {
                        $this->warn("  [WARN] SidMonitor returned HTTP {$response->status()}");
                    }
                } catch (\Exception $e) {
                    $this->error("  [FAIL] Cannot reach SidMonitor: {$e->getMessage()}");
                    $hasError = true;
                }
            }
        } elseif ($exporter === 'prometheus') {
            $prometheusEnabled = config('observatory.prometheus.enabled', false);
            $storage = config('observatory.prometheus.storage', 'apcu');
            $this->line($prometheusEnabled
                ? "  [OK] Prometheus endpoint enabled (storage: {$storage})"
                : '  [INFO] Prometheus endpoint disabled');
        }

        // 6. Outbound services
        $this->newLine();
        $services = config('observatory.outbound.services', []);
        $this->info('Outbound Service Detection: ' . count($services) . ' services');

        foreach ($services as $pattern => $name) {
            $this->line("  {$pattern} => {$name}");
        }

        $this->newLine();

        if ($hasError) {
            $this->error('Configuration has errors. Fix them before deploying.');

            return Command::FAILURE;
        }

        $this->info('All checks passed.');

        return Command::SUCCESS;
    }
}
