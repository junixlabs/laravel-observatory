<?php

namespace JunixLabs\Observatory\Tests\Unit;

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\EventMutex;
use JunixLabs\Observatory\Collectors\ScheduledTaskCollector;
use JunixLabs\Observatory\Contracts\ExporterInterface;
use JunixLabs\Observatory\Loggers\ScheduledTaskLogger;
use JunixLabs\Observatory\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;

class SkipReasonTest extends TestCase
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
            'observatory.labels' => ['environment' => 'testing'],
        ]);
    }

    public function test_skipped_task_includes_skip_reason_field(): void
    {
        $event = $this->createScheduledEvent('report:generate', '0 0 * * *');

        $this->logger->shouldReceive('log')->once();

        $this->exporter->shouldReceive('recordScheduledTask')->once()->withArgs(function (array $data) {
            return $data['status'] === 'skipped'
                && isset($data['skip_reason']);
        });

        $this->collector->skip($event);
    }

    public function test_completed_task_does_not_include_skip_reason(): void
    {
        $event = $this->createScheduledEvent('cache:clear', '* * * * *');

        $this->logger->shouldReceive('start')->once();
        $this->logger->shouldReceive('log')->once();

        $this->exporter->shouldReceive('recordScheduledTask')->once()->withArgs(function (array $data) {
            return $data['status'] === 'completed'
                && ! isset($data['skip_reason']);
        });

        $this->collector->start($event);
        $this->collector->end($event, 'completed');
    }

    public function test_failed_task_does_not_include_skip_reason(): void
    {
        $event = $this->createScheduledEvent('queue:work', '* * * * *');
        $exception = new \RuntimeException('Process failed');

        $this->logger->shouldReceive('start')->once();
        $this->logger->shouldReceive('log')->once();

        $this->exporter->shouldReceive('recordScheduledTask')->once()->withArgs(function (array $data) {
            return $data['status'] === 'failed'
                && ! isset($data['skip_reason']);
        });

        $this->collector->start($event);
        $this->collector->end($event, 'failed', $exception);
    }

    public function test_skip_reason_is_overlap_when_mutex_exists(): void
    {
        $mutex = Mockery::mock(EventMutex::class);

        $event = Mockery::mock(ScheduledEvent::class)->makePartial();
        $event->command = 'sync:data';
        $event->expression = '* * * * *';
        $event->description = null;
        $event->timezone = 'UTC';
        $event->withoutOverlapping = true;
        $event->mutex = $mutex;

        $event->shouldReceive('mutexName')->andReturn('framework/schedule-' . md5('sync:data'));
        $mutex->shouldReceive('exists')->with($event)->andReturn(true);

        $this->logger->shouldReceive('log')->once();

        $this->exporter->shouldReceive('recordScheduledTask')->once()->withArgs(function (array $data) {
            return $data['status'] === 'skipped'
                && $data['skip_reason'] === 'overlap';
        });

        $this->collector->skip($event);
    }

    public function test_skip_reason_is_unknown_when_not_overlapping(): void
    {
        $event = $this->createScheduledEvent('report:daily', '0 0 * * *');

        $this->logger->shouldReceive('log')->once();

        $this->exporter->shouldReceive('recordScheduledTask')->once()->withArgs(function (array $data) {
            return $data['status'] === 'skipped'
                && $data['skip_reason'] === 'unknown';
        });

        $this->collector->skip($event);
    }

    public function test_skip_reason_is_unknown_when_overlapping_but_mutex_not_held(): void
    {
        $mutex = Mockery::mock(EventMutex::class);

        $event = Mockery::mock(ScheduledEvent::class)->makePartial();
        $event->command = 'sync:data';
        $event->expression = '* * * * *';
        $event->description = null;
        $event->timezone = 'UTC';
        $event->withoutOverlapping = true;
        $event->mutex = $mutex;

        $event->shouldReceive('mutexName')->andReturn('framework/schedule-' . md5('sync:data'));
        $mutex->shouldReceive('exists')->with($event)->andReturn(false);

        $this->logger->shouldReceive('log')->once();

        $this->exporter->shouldReceive('recordScheduledTask')->once()->withArgs(function (array $data) {
            return $data['skip_reason'] === 'unknown';
        });

        $this->collector->skip($event);
    }

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
