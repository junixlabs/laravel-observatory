# Laravel Observatory

[![Latest Version on Packagist](https://img.shields.io/packagist/v/junixlabs/laravel-observatory.svg?style=flat-square)](https://packagist.org/packages/junixlabs/laravel-observatory)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/junixlabs/laravel-observatory/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/junixlabs/laravel-observatory/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/junixlabs/laravel-observatory.svg?style=flat-square)](https://packagist.org/packages/junixlabs/laravel-observatory)
[![License](https://img.shields.io/packagist/l/junixlabs/laravel-observatory.svg?style=flat-square)](https://packagist.org/packages/junixlabs/laravel-observatory)
[![PHP Version](https://img.shields.io/packagist/php-v/junixlabs/laravel-observatory.svg?style=flat-square)](https://packagist.org/packages/junixlabs/laravel-observatory)

A comprehensive observability toolkit for Laravel applications. Monitor HTTP requests, outbound API calls, queue jobs, and exceptions with Prometheus metrics export and structured logging for Grafana/Loki.

## Features

### Metrics (Prometheus)
- **Inbound Request Monitoring** - Automatically track all incoming HTTP requests
- **Outbound HTTP Monitoring** - Monitor all HTTP client calls to external services
- **Queue Job Monitoring** - Track job execution, duration, and failures
- **Exception Tracking** - Capture and count application exceptions
- **Prometheus Export** - Native Prometheus metrics format with `/metrics` endpoint
- **Custom Metrics** - Add your own counters, gauges, and histograms

### Logging (Loki/Grafana)
- **Inbound Request Logger** - Detailed HTTP request/response logging to Laravel channels
- **Outbound Request Logger** - Log external API calls with service detection
- **Job Logger** - Queue job execution logging with payload and duration
- **Exception Logger** - Structured exception logging with stack traces and context
- **Request ID Tracking** - Correlation IDs for distributed tracing
- **Sensitive Data Masking** - Automatic masking of passwords, tokens, and PII

### Additional Features
- **Service Detection** - Automatically identify external services (Stripe, AWS, etc.)
- **Grafana Dashboards** - Pre-built dashboard templates included
- **SidMonitor Integration** - (Coming Soon) Advanced monitoring with SidMonitor platform
- **Zero Configuration** - Works out of the box with sensible defaults

## Requirements

- PHP 8.2+
- Laravel 10.0+, 11.0+, or 12.0+

## Installation

```bash
composer require junixlabs/laravel-observatory
```

The package will auto-register its service provider.

### Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=observatory-config
```

## Quick Start

After installation, Observatory automatically:
1. Monitors all incoming HTTP requests
2. Tracks outbound HTTP calls via Laravel's HTTP client
3. Monitors queue job execution
4. Exposes metrics at `/metrics` endpoint

Visit `http://your-app.test/metrics` to see your Prometheus metrics!

## Configuration

### Basic Configuration

```env
# Enable/disable Observatory
OBSERVATORY_ENABLED=true

# Your application name (used in metrics)
OBSERVATORY_APP_NAME=my-app

# Exporter: 'prometheus' or 'sidmonitor'
OBSERVATORY_EXPORTER=prometheus
```

### Prometheus Configuration

```env
# Metrics endpoint path
OBSERVATORY_PROMETHEUS_ENDPOINT=/metrics

# Storage: 'memory', 'redis', 'apc', 'apcu'
OBSERVATORY_PROMETHEUS_STORAGE=memory

# Redis configuration (if using redis storage)
OBSERVATORY_REDIS_HOST=127.0.0.1
OBSERVATORY_REDIS_PORT=6379

# Enable basic auth for metrics endpoint
OBSERVATORY_PROMETHEUS_AUTH_ENABLED=false
OBSERVATORY_PROMETHEUS_AUTH_USERNAME=prometheus
OBSERVATORY_PROMETHEUS_AUTH_PASSWORD=secret
```

### Feature Toggles

```env
# Inbound request monitoring
OBSERVATORY_INBOUND_ENABLED=true

# Outbound HTTP monitoring
OBSERVATORY_OUTBOUND_ENABLED=true

# Queue job monitoring
OBSERVATORY_JOBS_ENABLED=true

# Exception tracking
OBSERVATORY_EXCEPTIONS_ENABLED=true
```

### Logging Configuration

Enable structured logging for Grafana/Loki integration:

```env
# Inbound request logging
OBSERVATORY_INBOUND_LOGGER_ENABLED=true
OBSERVATORY_INBOUND_LOGGER_CHANNEL=http_monitor

# Outbound request logging
OBSERVATORY_OUTBOUND_LOGGER_ENABLED=true
OBSERVATORY_OUTBOUND_LOGGER_CHANNEL=http_monitor

# Job execution logging
OBSERVATORY_JOB_LOGGER_ENABLED=true
OBSERVATORY_JOB_LOGGER_CHANNEL=http_monitor

# Exception logging
OBSERVATORY_EXCEPTION_LOGGER_ENABLED=true
OBSERVATORY_EXCEPTION_LOGGER_CHANNEL=http_monitor
```

### Request ID Configuration

Enable request correlation for distributed tracing:

```env
OBSERVATORY_REQUEST_ID_ENABLED=true
OBSERVATORY_REQUEST_ID_HEADER=X-Request-Id
```

The Request ID middleware will:
- Read existing request ID from incoming headers
- Generate UUID if not present
- Add request ID to response headers
- Include request ID in Laravel's log context

## Prometheus Metrics

### Available Metrics

| Metric | Type | Description |
|--------|------|-------------|
| `{app}_http_requests_total` | Counter | Total HTTP requests by method, route, status |
| `{app}_http_request_duration_seconds` | Histogram | Request latency distribution |
| `{app}_http_outbound_requests_total` | Counter | Outbound HTTP requests by method, host, status |
| `{app}_http_outbound_duration_seconds` | Histogram | Outbound request latency |
| `{app}_jobs_processed_total` | Counter | Queue jobs by name, queue, status |
| `{app}_jobs_duration_seconds` | Histogram | Job execution duration |
| `{app}_exceptions_total` | Counter | Exceptions by class and file |

### Prometheus Configuration

Add to your `prometheus.yml`:

```yaml
scrape_configs:
  - job_name: 'laravel'
    static_configs:
      - targets: ['your-app.test:80']
    metrics_path: '/metrics'
    # If using basic auth:
    # basic_auth:
    #   username: 'prometheus'
    #   password: 'secret'
```

## Custom Metrics

Use the `Observatory` facade to add custom metrics:

```php
use JunixLabs\Observatory\Facades\Observatory;

// Increment a counter
Observatory::increment('api_calls', ['endpoint' => 'users']);

// Set a gauge value
Observatory::gauge('active_connections', 42, ['server' => 'web-1']);

// Record a histogram observation
Observatory::histogram('payment_amount', 99.99, ['currency' => 'USD']);
```

## Excluding Paths and Jobs

### Exclude Paths from Monitoring

In `config/observatory.php`:

```php
'inbound' => [
    'exclude_paths' => [
        'telescope*',
        'horizon*',
        '_debugbar*',
        'health',
        'metrics',
        'api/internal/*',
    ],
],
```

### Exclude Jobs from Monitoring

```php
'jobs' => [
    'exclude_jobs' => [
        'App\Jobs\InternalHealthCheck',
        'App\Jobs\MetricsCollection',
    ],
],
```

### Exclude Hosts from Outbound Monitoring

```php
'outbound' => [
    'exclude_hosts' => [
        'localhost',
        '127.0.0.1',
        'internal-service.local',
    ],
],
```

## Service Detection

Automatically identify external services in outbound logs:

```php
'outbound_logger' => [
    'service_detection' => [
        // E-commerce Platforms
        '*.etsy.com' => 'etsy',
        '*.amazon.com' => 'amazon',
        '*.shopify.com' => 'shopify',

        // Payment & Infrastructure
        '*.stripe.com' => 'stripe',
        '*.amazonaws.com' => 'aws',
        '*.sendgrid.com' => 'sendgrid',

        // Custom services
        'api.myservice.com' => 'my_service',
    ],
],
```

This enables powerful Grafana queries:
```logql
{job="laravel"} | json | service="stripe"
```

## Structured Logging

All loggers output JSON-formatted logs optimized for Loki/Grafana:

### Inbound Request Log Example

```json
{
  "request_id": "550e8400-e29b-41d4-a716-446655440000",
  "method": "POST",
  "url": "/api/v1/orders",
  "route": "orders.store",
  "status_code": 201,
  "duration_ms": 145.23,
  "user_id": 123,
  "ip": "192.168.1.1",
  "labels": {
    "service": "api",
    "environment": "production"
  }
}
```

### Outbound Request Log Example

```json
{
  "request_id": "550e8400-e29b-41d4-a716-446655440000",
  "method": "POST",
  "url": "https://api.stripe.com/v1/charges",
  "service": "stripe",
  "status_code": 200,
  "duration_ms": 523.45,
  "labels": {
    "type": "outbound",
    "service": "stripe"
  }
}
```

### Job Log Example

```json
{
  "job_id": "job-uuid",
  "job_name": "ProcessOrder",
  "queue": "orders",
  "status": "completed",
  "duration_ms": 1234.56,
  "memory_mb": 45.2,
  "labels": {
    "queue": "orders",
    "status": "completed"
  }
}
```

### Exception Log Example

```json
{
  "request_id": "550e8400-e29b-41d4-a716-446655440000",
  "exception_class": "App\\Exceptions\\PaymentException",
  "message": "Payment declined",
  "file": "/app/Services/PaymentService.php",
  "line": 145,
  "trace": [...],
  "request": {
    "method": "POST",
    "url": "/api/v1/checkout"
  },
  "labels": {
    "exception_class": "PaymentException",
    "severity": "error"
  }
}
```

## Grafana Dashboards

Pre-built Grafana dashboard templates are included in the `dashboards/` directory:

- **observatory-dashboard.json** - Main overview dashboard with request volume, user analytics, external services, jobs, and exceptions
- **service-health-dashboard.json** - Deep-dive into external service health and performance

### Import Dashboard

1. Open Grafana → Dashboards → Import
2. Upload JSON file or paste content
3. Select your Loki datasource
4. Click Import

### Required Log Channel Configuration

Create a dedicated log channel in `config/logging.php`:

```php
'channels' => [
    'http_monitor' => [
        'driver' => 'single',
        'path' => storage_path('logs/http_monitor.log'),
        'level' => 'debug',
    ],
],
```

### Promtail Configuration

```yaml
scrape_configs:
  - job_name: laravel
    static_configs:
      - targets:
          - localhost
        labels:
          job: laravel
          __path__: /path/to/storage/logs/http_monitor.log
    pipeline_stages:
      - json:
          expressions:
            level: level
            request_id: request_id
            service: service
      - labels:
          level:
          service:
```

## Storage Adapters

For production environments with multiple workers, use persistent storage:

### Redis Storage (Recommended)

```env
OBSERVATORY_PROMETHEUS_STORAGE=redis
OBSERVATORY_REDIS_HOST=127.0.0.1
OBSERVATORY_REDIS_PORT=6379
```

### APCu Storage

```env
OBSERVATORY_PROMETHEUS_STORAGE=apcu
```

## SidMonitor Integration (Coming Soon)

SidMonitor provides advanced monitoring features beyond Prometheus:

- Real-time log streaming
- Distributed tracing
- Alerting and notifications
- Custom dashboards
- Rich contextual data

```env
OBSERVATORY_EXPORTER=sidmonitor
OBSERVATORY_SIDMONITOR_ENDPOINT=https://api.sidmonitor.com
OBSERVATORY_SIDMONITOR_API_KEY=your-api-key
OBSERVATORY_SIDMONITOR_PROJECT_ID=your-project-id
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email chuongld@canawan.com instead of using the issue tracker.

## Credits

- [JunixLabs](https://github.com/junixlabs)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
