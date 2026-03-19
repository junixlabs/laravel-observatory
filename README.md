# Laravel Observatory

[![Latest Version on Packagist](https://img.shields.io/packagist/v/junixlabs/laravel-observatory.svg?style=flat-square)](https://packagist.org/packages/junixlabs/laravel-observatory)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/junixlabs/laravel-observatory/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/junixlabs/laravel-observatory/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/junixlabs/laravel-observatory.svg?style=flat-square)](https://packagist.org/packages/junixlabs/laravel-observatory)
[![License](https://img.shields.io/packagist/l/junixlabs/laravel-observatory.svg?style=flat-square)](https://packagist.org/packages/junixlabs/laravel-observatory)
[![PHP Version](https://img.shields.io/packagist/php-v/junixlabs/laravel-observatory.svg?style=flat-square)](https://packagist.org/packages/junixlabs/laravel-observatory)

A comprehensive observability toolkit for Laravel applications. Monitor HTTP requests, outbound API calls, queue jobs, scheduled tasks, and exceptions with structured logging and optional Prometheus or SidMonitor metrics.

## Features

- **Inbound Request Logging** - Automatically log all incoming HTTP requests
- **Outbound HTTP Logging** - Log external API calls with service detection
- **Queue Job Logging** - Track job execution with duration and memory usage
- **Scheduled Task Logging** - Monitor artisan scheduled tasks lifecycle (start, finish, fail, skip)
- **Exception Logging** - Structured exception logging with stack traces
- **Request ID Tracking** - Correlation IDs for distributed tracing
- **Sensitive Data Masking** - Automatic masking of passwords, tokens, and PII
- **Dual Exporter Support** - Choose between Prometheus (pull) or SidMonitor (push) metrics
- **Circuit Breaker** - Resilient push-based exporting with automatic backoff
- **Zero Configuration** - Works out of the box with sensible defaults
- **Grafana Dashboards** - Pre-built dashboard templates included

## Requirements

- PHP 8.0+
- Laravel 9.0, 10.0, 11.0, or 12.0

## Installation

```bash
composer require junixlabs/laravel-observatory
```

The package auto-registers and works immediately - no configuration needed!

## Quick Start

After installation, Observatory automatically:
1. Logs all incoming HTTP requests to `storage/logs/observatory.log`
2. Logs outbound HTTP calls via Laravel's HTTP client
3. Logs queue job execution
4. Logs scheduled task execution
5. Logs exceptions with context

### View Your Logs

```bash
tail -f storage/logs/observatory.log | jq
```

## Configuration

### Publish Config (Optional)

```bash
php artisan vendor:publish --tag=observatory-config
```

### Environment Variables

All features are **enabled by default**. Only set these if you need to change defaults:

```env
# Disable Observatory entirely
OBSERVATORY_ENABLED=false

# Change log channel (default: 'observatory' -> storage/logs/observatory.log)
# Use 'stderr' for Docker/K8s
OBSERVATORY_LOG_CHANNEL=stderr

# Disable specific loggers
OBSERVATORY_INBOUND_ENABLED=false
OBSERVATORY_OUTBOUND_ENABLED=false
OBSERVATORY_JOBS_ENABLED=false
OBSERVATORY_SCHEDULED_TASKS_ENABLED=false
OBSERVATORY_EXCEPTIONS_ENABLED=false

# Log request/response bodies (disabled by default - can be large)
OBSERVATORY_LOG_BODY=true

# Only log slow requests (0 = log all)
OBSERVATORY_SLOW_THRESHOLD_MS=1000
```

## Exporters

Observatory supports two metrics exporters. Set via `OBSERVATORY_EXPORTER` env var.

### Prometheus (default, pull-based)

```env
OBSERVATORY_EXPORTER=prometheus
OBSERVATORY_PROMETHEUS_ENABLED=true
OBSERVATORY_PROMETHEUS_STORAGE=apcu  # or 'redis', 'memory'
```

Exposes a `/metrics` endpoint scraped by Prometheus.

### SidMonitor (push-based)

```env
OBSERVATORY_EXPORTER=sidmonitor
SIDMONITOR_ENDPOINT=https://api.sidmonitor.com
SIDMONITOR_API_KEY=your-api-key
```

Buffers data in-memory and flushes in batches to the SidMonitor backend. Includes a circuit breaker that pauses sending after consecutive failures to avoid blocking your application.

```env
# Batch settings
SIDMONITOR_BATCH_SIZE=100
SIDMONITOR_BATCH_INTERVAL=10

# Circuit breaker
SIDMONITOR_CIRCUIT_BREAKER_THRESHOLD=3   # failures before opening
SIDMONITOR_CIRCUIT_BREAKER_COOLDOWN=30   # seconds before retry
```

## Log Channel

Observatory auto-registers the `observatory` log channel:
- **File**: `storage/logs/observatory.log`
- **Format**: JSON (Loki/ELK compatible)
- **Rotation**: Daily, 14 days retention

For Docker/Kubernetes, use stderr:
```env
OBSERVATORY_LOG_CHANNEL=stderr
```

## Custom Headers

Extract custom headers into logs (multi-tenant, workspace, etc.):

```php
// config/observatory.php
'inbound' => [
    'custom_headers' => [
        'X-Workspace-Id' => 'workspace_id',
        'X-Tenant-Id' => 'tenant_id',
        'X-Correlation-Id' => 'correlation_id',
    ],
],
```

Result in logs:
```json
{
  "request_id": "abc-123",
  "method": "POST",
  "path": "/api/users",
  "workspace_id": "ws-456",
  "tenant_id": "tenant-789"
}
```

## Service Detection

Identify external services in outbound logs:

```php
// config/observatory.php
'outbound' => [
    'services' => [
        '*.stripe.com' => 'stripe',
        '*.amazonaws.com' => 'aws',
        '*.sendgrid.com' => 'sendgrid',
        'api.myservice.com' => 'my_service',
    ],
],
```

## Excluding Paths/Jobs/Tasks

```php
// config/observatory.php
'inbound' => [
    'exclude_paths' => [
        'telescope*',
        'horizon*',
        'health',
        'metrics',
    ],
],

'jobs' => [
    'exclude_jobs' => [
        'App\Jobs\InternalHealthCheck',
    ],
],

'scheduled_tasks' => [
    'exclude_commands' => [
        'schedule:run',
    ],
],
```

## Structured Log Examples

### Inbound Request

```json
{
  "message": "HTTP_REQUEST",
  "context": {
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "type": "inbound",
    "method": "POST",
    "url": "https://example.com/api/v1/orders",
    "path": "api/v1/orders",
    "route": "orders.store",
    "status_code": 201,
    "duration_ms": 145.23,
    "ip": "192.168.1.1",
    "user_agent": "Mozilla/5.0...",
    "user_id": 123,
    "memory_mb": 45.2,
    "environment": "production"
  }
}
```

### Outbound Request

```json
{
  "message": "HTTP_OUTBOUND",
  "context": {
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "type": "outbound",
    "service": "stripe",
    "method": "POST",
    "url": "https://api.stripe.com/v1/charges",
    "host": "api.stripe.com",
    "status_code": 200,
    "duration_ms": 523.45,
    "environment": "production"
  }
}
```

### Job Processed

```json
{
  "message": "JOB_PROCESSED",
  "context": {
    "job_id": "123",
    "job_name": "App\\Jobs\\ProcessOrder",
    "queue": "orders",
    "status": "processed",
    "duration_ms": 1234.56,
    "attempts": 1,
    "memory": {
      "used_mb": 12.5,
      "peak_mb": 45.2
    },
    "environment": "production"
  }
}
```

### Scheduled Task

```json
{
  "message": "SCHEDULED_TASK",
  "context": {
    "command": "reports:generate",
    "description": "Generate daily reports",
    "expression": "0 2 * * *",
    "status": "completed",
    "duration_ms": 4523.12,
    "exit_code": 0,
    "memory": {
      "used_mb": 32.1,
      "peak_mb": 64.5
    },
    "environment": "production"
  }
}
```

### Exception

```json
{
  "message": "EXCEPTION",
  "context": {
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "exception_class": "App\\Exceptions\\PaymentException",
    "message": "Payment declined",
    "code": 402,
    "file": "/app/Services/PaymentService.php",
    "line": 145,
    "request": {
      "method": "POST",
      "url": "https://example.com/api/orders",
      "path": "api/orders"
    },
    "user": {
      "id": 123
    },
    "trace": ["..."],
    "environment": "production"
  }
}
```

## Prometheus Metrics (Optional)

```env
OBSERVATORY_PROMETHEUS_ENABLED=true
OBSERVATORY_PROMETHEUS_STORAGE=apcu  # or 'redis', 'memory'
```

Visit `http://your-app.test/metrics` to see metrics.

### Available Metrics

| Metric | Type | Description |
|--------|------|-------------|
| `{app}_http_requests_total` | Counter | Total HTTP requests |
| `{app}_http_request_duration_seconds` | Histogram | Request latency |
| `{app}_http_outbound_requests_total` | Counter | Outbound HTTP requests |
| `{app}_jobs_processed_total` | Counter | Queue jobs processed |
| `{app}_scheduled_tasks_total` | Counter | Scheduled tasks executed |
| `{app}_exceptions_total` | Counter | Exceptions count |

### Prometheus Auth

```env
OBSERVATORY_METRICS_AUTH=true
OBSERVATORY_METRICS_USER=prometheus
OBSERVATORY_METRICS_PASS=secret
```

## Grafana Dashboards

Import pre-built dashboards from the `dashboards/` directory:

| Dashboard | Data Source | Description |
|-----------|-------------|-------------|
| `observatory-dashboard.json` | Loki | Request logs, user analytics, exceptions |
| `prometheus-dashboard.json` | Prometheus | Metrics overview, latency percentiles |

### LogQL Query Examples

```logql
# All requests
{job="laravel-observatory"} | json | message="HTTP_REQUEST"

# Errors only
{job="laravel-observatory"} | json | status_code >= 400

# By user
{job="laravel-observatory"} | json | user_id="123"

# Slow requests (>1s)
{job="laravel-observatory"} | json | duration_ms > 1000

# External service calls
{job="laravel-observatory"} | json | type="outbound" | service="stripe"

# Scheduled tasks
{job="laravel-observatory"} | json | message="SCHEDULED_TASK"

# Exceptions
{job="laravel-observatory"} | json | message="EXCEPTION"
```

## Kubernetes Deployment

See [k8s/README.md](k8s/README.md) for Kubernetes deployment with Loki stack.

## Upgrading from v1.x

### Breaking Changes in v2.0

1. **SidMonitor env vars renamed**: `OBSERVATORY_SIDMONITOR_*` is now `SIDMONITOR_*`
2. **ExporterInterface**: New `recordScheduledTask()` method required for custom exporters

Update your `.env` file if using SidMonitor:
```env
# Before (v1.x)
OBSERVATORY_SIDMONITOR_ENDPOINT=...
OBSERVATORY_SIDMONITOR_API_KEY=...

# After (v2.0)
SIDMONITOR_ENDPOINT=...
SIDMONITOR_API_KEY=...
```

Re-publish config if upgrading:
```bash
php artisan vendor:publish --tag=observatory-config --force
```

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG](CHANGELOG.md) for recent changes.

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

Report security issues to chuongld@canawan.com instead of the issue tracker.

## Credits

- [JunixLabs](https://github.com/junixlabs)
- [All Contributors](../../contributors)

## License

MIT License. See [LICENSE](LICENSE) for details.
