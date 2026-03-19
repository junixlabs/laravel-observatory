<?php

namespace JunixLabs\Observatory\Tests\Unit;

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use JunixLabs\Observatory\Collectors\ScheduledTaskCollector;
use JunixLabs\Observatory\Contracts\ExporterInterface;
use JunixLabs\Observatory\Loggers\ScheduledTaskLogger;
use JunixLabs\Observatory\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;

class ScheduledTaskCollectorTest extends TestCase
{
    protected ExporterInterface|MockInterface $exporter;

    protected ScheduledTaskLogger|MockInterface $logger;

    protected ScheduledTaskCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exporter = Mockery::mock(ExporterInterface::class);
        $this->logger = Mockery::mock(ScheduledTaskLogger::class);

        $this->collector = new ScheduledTaskCollector($this->exporter, $this->logger);

        config([
            'observatory.scheduled_tasks.exclude_commands' => [],
            'observatory.scheduled_tasks.log_output' => false,
            'observatory.labels' => ['environment' => 'testing'],
        ]);
    }

    public function test_start_records_timing_data(): void
    {
        $event = $this->createScheduledEvent('inspire', '* * * * *');

        $this->logger->shouldReceive('start')->once()->with($event);

        $this->collector->start($event);

        // Call end to verify that start recorded timing data (duration should be small, not zero-ish fallback)
        $this->exporter->shouldReceive('recordScheduledTask')->once()->withArgs(function (array $data) {
            // If start() recorded timing properly, duration_ms should be very small but based on real start_time
            return isset($data['duration_ms'])
                && isset($data['scheduled_at'])
                && isset($data['started_at'])
                && $data['command'] === 'inspire';
        });

        $this->logger->shouldReceive('log')->once();

        $this->collector->end($event, 'completed');
    }

    public function test_end_calls_exporter_with_correct_data(): void
    {
        $event = $this->createScheduledEvent('cache:clear', '0 * * * *');

        $this->logger->shouldReceive('start')->once();
        $this->logger->shouldReceive('log')->once();

        $this->collector->start($event);

        $this->exporter->shouldReceive('recordScheduledTask')->once()->withArgs(function (array $data) {
            return $data['command'] === 'cache:clear'
                && $data['expression'] === '0 * * * *'
                && $data['status'] === 'completed'
                && isset($data['task_id'])
                && isset($data['duration_ms'])
                && isset($data['memory_usage_mb'])
                && isset($data['scheduled_at'])
                && isset($data['started_at'])
                && isset($data['completed_at'])
                && $data['labels'] === ['environment' => 'testing']
                && ! isset($data['error_message'])
                && ! isset($data['error_trace']);
        });

        $this->collector->end($event, 'completed');
    }

    public function test_end_with_failed_status_includes_exception_data(): void
    {
        $event = $this->createScheduledEvent('queue:work', '* * * * *');
        $exception = new \RuntimeException('Connection timed out');

        $this->logger->shouldReceive('start')->once();
        $this->logger->shouldReceive('log')->once();

        $this->collector->start($event);

        $this->exporter->shouldReceive('recordScheduledTask')->once()->withArgs(function (array $data) {
            return $data['status'] === 'failed'
                && $data['error_message'] === 'Connection timed out'
                && isset($data['error_trace'])
                && str_contains($data['error_trace'], ':');
        });

        $this->collector->end($event, 'failed', $exception);
    }

    public function test_skip_calls_end_with_skipped_status(): void
    {
        $event = $this->createScheduledEvent('report:generate', '0 0 * * *');

        $this->logger->shouldReceive('log')->once()->withArgs(function (
            ScheduledEvent $e,
            string $status,
        ) {
            return $status === 'skipped';
        });

        $this->exporter->shouldReceive('recordScheduledTask')->once()->withArgs(function (array $data) {
            return $data['status'] === 'skipped'
                && $data['command'] === 'report:generate';
        });

        $this->collector->skip($event);
    }

    public function test_should_monitor_returns_false_for_excluded_commands(): void
    {
        config([
            'observatory.scheduled_tasks.exclude_commands' => [
                'schedule:run',
                'cache:*',
            ],
        ]);

        $exactMatch = $this->createScheduledEvent('schedule:run', '* * * * *');
        $wildcardMatch = $this->createScheduledEvent('cache:clear', '* * * * *');

        $this->assertFalse($this->collector->shouldMonitor($exactMatch));
        $this->assertFalse($this->collector->shouldMonitor($wildcardMatch));
    }

    public function test_should_monitor_returns_true_for_non_excluded_commands(): void
    {
        config([
            'observatory.scheduled_tasks.exclude_commands' => [
                'schedule:run',
            ],
        ]);

        $event = $this->createScheduledEvent('queue:work', '* * * * *');

        $this->assertTrue($this->collector->shouldMonitor($event));
    }

    /**
     * Create a mock ScheduledEvent with the given command and expression.
     */
    protected function createScheduledEvent(string $command, string $expression): ScheduledEvent
    {
        $event = Mockery::mock(ScheduledEvent::class)->makePartial();
        $event->command = $command;
        $event->expression = $expression;
        $event->description = null;
        $event->timezone = 'UTC';
        $event->withoutOverlapping = false;

        $event->shouldReceive('mutexName')->andReturn('framework/schedule-' . md5($command));

        return $event;
    }
}
