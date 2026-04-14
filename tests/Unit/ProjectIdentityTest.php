<?php

namespace JunixLabs\Observatory\Tests\Unit;

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Request;
use JunixLabs\Observatory\Collectors\InboundCollector;
use JunixLabs\Observatory\Collectors\JobCollector;
use JunixLabs\Observatory\Collectors\ScheduledTaskCollector;
use JunixLabs\Observatory\Contracts\ExporterInterface;
use JunixLabs\Observatory\Loggers\JobLogger;
use JunixLabs\Observatory\Loggers\ScheduledTaskLogger;
use JunixLabs\Observatory\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\HttpFoundation\Response;

class ProjectIdentityTest extends TestCase
{
    public function test_config_has_project_key_with_default(): void
    {
        $project = config('observatory.project');

        $this->assertNotNull($project);
    }

    public function test_config_project_respects_env_override(): void
    {
        config(['observatory.project' => 'hub-admin']);

        $this->assertEquals('hub-admin', config('observatory.project'));
    }

    public function test_inbound_collector_includes_project_in_labels(): void
    {
        config(['observatory.project' => 'my-app']);
        config(['observatory.labels' => ['environment' => 'testing']]);

        /** @var ExporterInterface|MockInterface $exporter */
        $exporter = Mockery::mock(ExporterInterface::class);
        $collector = new InboundCollector($exporter);

        $request = Request::create('/api/test', 'GET');
        $response = new Response('OK', 200);

        $exporter->shouldReceive('recordInbound')->once()->withArgs(function (array $data) {
            return isset($data['labels']['project'])
                && $data['labels']['project'] === 'my-app'
                && $data['labels']['environment'] === 'testing';
        });

        $collector->start($request);
        $collector->end($request, $response);
    }

    public function test_job_collector_includes_project_in_labels(): void
    {
        config(['observatory.project' => 'hub-admin']);
        config(['observatory.labels' => ['environment' => 'testing']]);

        /** @var ExporterInterface|MockInterface $exporter */
        $exporter = Mockery::mock(ExporterInterface::class);
        /** @var JobLogger|MockInterface $logger */
        $logger = Mockery::mock(JobLogger::class);
        $collector = new JobCollector($exporter, $logger);

        /** @var Job|MockInterface $job */
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('getJobId')->andReturn('123');
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('getConnectionName')->andReturn('sync');
        $job->shouldReceive('attempts')->andReturn(1);
        $job->shouldReceive('payload')->andReturn(['displayName' => 'TestJob']);
        $job->shouldReceive('getName')->andReturn('TestJob');

        $logger->shouldReceive('start')->once();
        $logger->shouldReceive('log')->once();

        $exporter->shouldReceive('recordJob')->once()->withArgs(function (array $data) {
            return isset($data['labels']['project'])
                && $data['labels']['project'] === 'hub-admin'
                && $data['labels']['environment'] === 'testing';
        });

        $collector->start($job);
        $collector->end($job, 'completed');
    }

    public function test_scheduled_task_collector_includes_project_in_labels(): void
    {
        config(['observatory.project' => 'hub-admin']);
        config(['observatory.labels' => ['environment' => 'testing']]);

        /** @var ExporterInterface|MockInterface $exporter */
        $exporter = Mockery::mock(ExporterInterface::class);
        /** @var ScheduledTaskLogger|MockInterface $logger */
        $logger = Mockery::mock(ScheduledTaskLogger::class);
        $collector = new ScheduledTaskCollector($exporter, $logger);

        $event = $this->createScheduledEvent('cache:clear', '* * * * *');

        $logger->shouldReceive('start')->once();
        $logger->shouldReceive('log')->once();

        $exporter->shouldReceive('recordScheduledTask')->once()->withArgs(function (array $data) {
            return isset($data['labels']['project'])
                && $data['labels']['project'] === 'hub-admin'
                && $data['labels']['environment'] === 'testing';
        });

        $collector->start($event);
        $collector->end($event, 'completed');
    }

    public function test_project_label_overrides_user_defined_project_label(): void
    {
        config(['observatory.project' => 'authoritative-project']);
        config(['observatory.labels' => ['environment' => 'testing', 'project' => 'user-defined']]);

        /** @var ExporterInterface|MockInterface $exporter */
        $exporter = Mockery::mock(ExporterInterface::class);
        $collector = new InboundCollector($exporter);

        $request = Request::create('/api/test', 'GET');
        $response = new Response('OK', 200);

        $exporter->shouldReceive('recordInbound')->once()->withArgs(function (array $data) {
            // The array_merge should override user-defined 'project' with the config value
            return $data['labels']['project'] === 'authoritative-project';
        });

        $collector->start($request);
        $collector->end($request, $response);
    }

    public function test_project_falls_back_to_app_name(): void
    {
        // Simulate config where observatory.project is not set — use app.name fallback
        $observatory = config('observatory');
        unset($observatory['project']);
        config(['observatory' => $observatory]);
        config(['app.name' => 'fallback-app']);

        /** @var ExporterInterface|MockInterface $exporter */
        $exporter = Mockery::mock(ExporterInterface::class);
        $collector = new InboundCollector($exporter);

        $request = Request::create('/api/test', 'GET');
        $response = new Response('OK', 200);

        $exporter->shouldReceive('recordInbound')->once()->withArgs(function (array $data) {
            return $data['labels']['project'] === 'fallback-app';
        });

        $collector->start($request);
        $collector->end($request, $response);
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
