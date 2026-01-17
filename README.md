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
- **User Tracking** - Track user_id and workspace_id for analytics
- **Sensitive Data Masking** - Automatic masking of passwords, tokens, and PII

### Additional Features
- **Service Detection** - Automatically identify external services (Stripe, AWS, etc.)
- **Grafana Dashboards** - Pre-built dashboard templates for Prometheus and Loki
- **Docker Setup** - Ready-to-use Docker Compose for Grafana, Prometheus, Loki stack
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

# Your application name (used as metrics prefix)
OBSERVATORY_APP_NAME=my-app

# Exporter: 'prometheus' or 'sidmonitor'
OBSERVATORY_EXPORTER=prometheus
```

### Prometheus Configuration

```env
# Metrics endpoint path
OBSERVATORY_PROMETHEUS_ENDPOINT=/metrics

# Storage: 'memory', 'redis', 'apc', 'apcu'
# Use 'redis' or 'apcu' for production with multiple workers
OBSERVATORY_PROMETHEUS_STORAGE=redis

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
# Metrics collection
OBSERVATORY_INBOUND_ENABLED=true
OBSERVATORY_OUTBOUND_ENABLED=true
OBSERVATORY_JOBS_ENABLED=true
OBSERVATORY_EXCEPTIONS_ENABLED=true
```

### Logging Configuration (for Loki)

Enable structured logging for Grafana/Loki integration:

```env
# Enable loggers
OBSERVATORY_INBOUND_LOGGER_ENABLED=true
OBSERVATORY_OUTBOUND_LOGGER_ENABLED=true
OBSERVATORY_JOB_LOGGER_ENABLED=true
OBSERVATORY_EXCEPTION_LOGGER_ENABLED=true

# Enable user tracking
OBSERVATORY_LOG_USER=true
```

> **Note:** The `observatory` logging channel is **auto-configured** by the package. Logs are written to `storage/logs/observatory.log` in JSON format. No manual configuration needed!

### Request ID Configuration

Enable request correlation for distributed tracing:

```env
OBSERVATORY_REQUEST_ID_ENABLED=true
OBSERVATORY_REQUEST_ID_HEADER=X-Request-Id
```

## Prometheus Metrics

### Available Metrics

| Metric | Type | Labels | Description |
|--------|------|--------|-------------|
| `{app}_http_requests_total` | Counter | method, route, status_code | Total HTTP requests |
| `{app}_http_request_duration_seconds` | Histogram | method, route, status_code | Request latency distribution |
| `{app}_http_outbound_requests_total` | Counter | method, host, status_code | Outbound HTTP requests |
| `{app}_http_outbound_duration_seconds` | Histogram | method, host, status_code | Outbound request latency |
| `{app}_jobs_processed_total` | Counter | job_name, queue, status | Queue jobs processed |
| `{app}_jobs_duration_seconds` | Histogram | job_name, queue, status | Job execution duration |
| `{app}_exceptions_total` | Counter | exception_class, file | Exceptions count |

### Prometheus Configuration

Add to your `prometheus.yml`:

```yaml
scrape_configs:
  - job_name: 'laravel'
    static_configs:
      - targets: ['your-app.test:80']
    metrics_path: '/metrics'
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

## Docker Setup (Grafana + Prometheus + Loki)

A complete Docker setup is included in `.tests/` directory for local development and testing.

### Quick Start

```bash
cd .tests

# Create .env with your Laravel logs path
echo "LARAVEL_LOGS_PATH=/path/to/your/laravel-app/storage/logs" > .env

# Start the stack
docker-compose up -d
```

### Access URLs

| Service | URL | Credentials |
|---------|-----|-------------|
| Grafana | http://localhost:3000 | admin / admin |
| Prometheus | http://localhost:9090 | - |
| Loki | http://localhost:3100 | - |

### Stack Components

```
┌─────────────────────────────────────────────────────────┐
│                     Grafana :3000                       │
│              (Dashboards & Visualization)               │
└─────────────────┬───────────────────┬───────────────────┘
                  │                   │
         ┌────────▼────────┐ ┌────────▼────────┐
         │ Prometheus:9090 │ │   Loki :3100    │
         │    (Metrics)    │ │    (Logs)       │
         └────────┬────────┘ └────────┬────────┘
                  │                   │
                  │           ┌───────▼────────┐
                  │           │   Promtail     │
                  │           │ (Log Shipper)  │
                  │           └───────┬────────┘
                  │                   │
         ┌────────▼───────────────────▼────────┐
         │           Laravel App               │
         │  /metrics          storage/logs/    │
         └─────────────────────────────────────┘
```

### Update Prometheus Target

Edit `.tests/docker/prometheus/prometheus.yml` to point to your Laravel app:

```yaml
scrape_configs:
  - job_name: 'laravel-observatory'
    static_configs:
      - targets: ['host.docker.internal:8000']  # Your Laravel app port
    metrics_path: /metrics
```

## Grafana Dashboards

Pre-built dashboards are included in the `dashboards/` directory:

| Dashboard | Data Source | Description |
|-----------|-------------|-------------|
| `prometheus-dashboard.json` | Prometheus | Metrics overview, latency percentiles, error rates |
| `observatory-dashboard.json` | Loki | Request logs, user analytics, exceptions |
| `service-health-dashboard.json` | Loki | External service health and performance |

### Dashboard Features

**Prometheus Dashboard:**
- Request rate & latency (P50, P90, P95, P99)
- Success rate & error rate
- Top routes by RPS and latency
- Outbound requests by host
- Job processing stats
- Exception counts

**Loki Dashboard:**
- Total requests, jobs, exceptions
- User activity tracking
- Request volume over time
- Status code distribution
- External service health
- Live log stream

### Import Dashboards

**Option 1: Auto-provision (Docker)**
Dashboards are automatically loaded when using the Docker setup.

**Option 2: Manual Import**
1. Open Grafana → Dashboards → Import
2. Upload JSON file from `dashboards/` directory
3. Select your datasource
4. Click Import

### Dashboard Variables

Update the dashboard variables to match your setup:

| Variable | Prometheus | Loki |
|----------|------------|------|
| `app_name` | Your app prefix (e.g., `MyApp`) | - |
| `job` | - | `laravel-observatory` |

## User Tracking

Observatory can track user activity in Loki logs for analytics:

### Enable User Tracking

```env
OBSERVATORY_LOG_USER=true
```

### Log Output

```json
{
  "message": "HTTP_REQUEST",
  "context": {
    "user_id": "123",
    "workspace_id": "456",
    "method": "POST",
    "route": "api.orders.store",
    "status_code": 200
  }
}
```

### Query Examples (LogQL)

```logql
# All requests by user 123
{job="laravel-observatory"} | json | user_id="123"

# Errors by user
{job="laravel-observatory"} | json | user_id="123" | status_code >= 400

# Top users by request count
sum by (user_id) (count_over_time({job="laravel-observatory"} | json | message="HTTP_REQUEST" | user_id != "" [1h]))
```

## Excluding Paths and Jobs

### Exclude Paths from Monitoring

```php
// config/observatory.php
'inbound' => [
    'exclude_paths' => [
        'telescope*',
        'horizon*',
        '_debugbar*',
        'health',
        'metrics',
    ],
],
```

### Exclude Jobs from Monitoring

```php
'jobs' => [
    'exclude_jobs' => [
        'App\Jobs\InternalHealthCheck',
    ],
],
```

## Service Detection

Automatically identify external services in outbound logs:

```php
'outbound_logger' => [
    'service_detection' => [
        '*.stripe.com' => 'stripe',
        '*.amazonaws.com' => 'aws',
        '*.sendgrid.com' => 'sendgrid',
        'api.myservice.com' => 'my_service',
    ],
],
```

Query in Grafana:
```logql
{job="laravel-observatory"} | json | service="stripe"
```

## Structured Log Examples

### Inbound Request

```json
{
  "message": "HTTP_REQUEST",
  "context": {
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "method": "POST",
    "url": "/api/v1/orders",
    "route": "orders.store",
    "status_code": 201,
    "duration_ms": 145.23,
    "user_id": "123",
    "workspace_id": "456",
    "ip": "192.168.1.1"
  }
}
```

### Outbound Request

```json
{
  "message": "HTTP_OUTBOUND",
  "context": {
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "method": "POST",
    "url": "https://api.stripe.com/v1/charges",
    "service": "stripe",
    "status_code": 200,
    "duration_ms": 523.45
  }
}
```

### Job Processed

```json
{
  "message": "JOB_PROCESSED",
  "context": {
    "job_name": "ProcessOrder",
    "queue": "orders",
    "status": "processed",
    "duration_ms": 1234.56,
    "memory_mb": 45.2
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
    "exception_message": "Payment declined",
    "file": "PaymentService.php",
    "line": 145,
    "user_id": "123"
  }
}
```

## Storage Adapters

For production with multiple workers, use persistent storage:

### Redis (Recommended)

```env
OBSERVATORY_PROMETHEUS_STORAGE=redis
OBSERVATORY_REDIS_HOST=127.0.0.1
OBSERVATORY_REDIS_PORT=6379
```

### APCu

```env
OBSERVATORY_PROMETHEUS_STORAGE=apcu
```

## Prometheus vs Loki: When to Use

| Use Case | Tool | Why |
|----------|------|-----|
| "Error rate increased 5%?" | Prometheus | Aggregated metrics, alerting |
| "Which user caused the error?" | Loki | Full log context, user_id |
| "P95 latency trend" | Prometheus | Histogram percentiles |
| "Show me the request payload" | Loki | Detailed logs |
| "Alert when CPU > 80%" | Prometheus | Numeric thresholds |
| "Trace request across services" | Loki | Request ID correlation |

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
