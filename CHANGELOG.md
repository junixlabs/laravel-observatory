# Changelog

All notable changes to `laravel-observatory` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-03-19

### Added
- **Scheduled Task Monitoring** - Full lifecycle tracking for Laravel scheduled tasks
  - New `ScheduledTaskCollector` hooks into `ScheduledTaskStarting`, `ScheduledTaskFinished`, `ScheduledTaskFailed`, `ScheduledTaskSkipped` events
  - New `ScheduledTaskLogger` writes structured JSON logs (`SCHEDULED_TASK` message) with timing, memory, and exit code
  - Supports task output capture via `log_output` and `max_output_size` config
  - Supports command exclusion with wildcards and slow threshold filtering
  - New config section: `observatory.scheduled_tasks.*`
- **SidMonitor Exporter** - Full push-based exporter implementation (replaces previous stub)
  - Batch ingestion with separate buffers per data type (inbound, outbound, jobs, scheduled tasks)
  - Two API endpoints: `POST /api/ingest/batch` (HTTP logs), `POST /api/ingest/jobs/batch` (jobs + tasks)
  - `X-API-Key` authentication
  - Auto-flush on batch size threshold or time interval
  - Field mappings compatible with SidMonitor backend schema
  - New config section: `observatory.sidmonitor.*` (endpoint, api_key, timeout, batch settings)
- **Circuit Breaker** for SidMonitor exporter resilience
  - After N consecutive flush failures, pause sending for M seconds (half-open retry after cooldown)
  - Automatic buffer trimming when circuit is open to prevent unbounded memory growth
  - New config section: `observatory.circuit_breaker.*` (threshold default: 3, cooldown default: 30s)
- **Outbound Event-based Fallback** for Laravel < 10.14
  - Listens to `RequestSending`, `ResponseReceived`, `ConnectionFailed` events when `Http::globalMiddleware()` is unavailable
  - Previously silently ignored — now provides full outbound monitoring on older Laravel versions
  - New methods: `OutboundCollector::recordFromEvent()`, `OutboundRequestLogger::logFromEvent()`
  - Uses `spl_object_id()` keyed timing map with stale entry eviction (>5 min) for long-running processes
- **Shutdown Hook** - `app->terminating()` callback to flush SidMonitor buffer after response is sent
- **Scheduled Tasks Prometheus Metric** - New `{app}_scheduled_tasks_total` counter (labels: `command`, `status`)
- `Observatory::scheduledTasks()` method and `@method` Facade annotation
- `ExporterInterface::recordScheduledTask(array $data)` method

### Changed
- **Concurrent Request Safety** - `InboundCollector` and `InboundRequestLogger` now use `spl_object_id($request)` keyed arrays instead of single `$startTime`/`$startMemory` properties, fixing timing data corruption under concurrent requests (Octane, Swoole)
- **Outbound Collector Enhancements**
  - Added `parent_request_id` for correlation to parent inbound request
  - Added `request_size` and `response_size` fields
  - Added `error_message` and `error_code` for non-2xx responses
  - `shouldMonitor()` check moved before timer start in Guzzle middleware
  - Config values cached in constructor to avoid repeated `config()` lookups
- **Exception Handler Hardening** - Observatory logging and metrics recording now each wrapped in separate try/catch blocks to never suppress original exception reporting
- **SidMonitor env vars renamed** - `OBSERVATORY_SIDMONITOR_*` prefix changed to `SIDMONITOR_*` (e.g. `SIDMONITOR_ENDPOINT`, `SIDMONITOR_API_KEY`, `SIDMONITOR_BATCH_SIZE`)
- **App name config** - `OBSERVATORY_APP_NAME` env var replaced with standard Laravel `APP_NAME`

### Removed
- `sidmonitor.project_id` config option — authentication now uses API key only
- SidMonitor exporter stub/placeholder messages ("Coming soon", "planned features")

### Breaking Changes
- `ExporterInterface` has a new required method `recordScheduledTask(array $data): void` — custom exporter implementations must add this method
- SidMonitor env var prefix changed from `OBSERVATORY_SIDMONITOR_*` to `SIDMONITOR_*` — update `.env` files if upgrading

## [1.3.3] - 2026-01-18

### Fixed
- **Log Bloat** - Fixed large query params causing massive log entries (1000+ items)
  - Added `normalizeArray()` method to limit array depth and item count
  - Default limits: 50 items, 3 levels deep
  - Configurable via `inbound.max_query_items` and `inbound.max_query_depth`

## [1.3.2] - 2026-01-18

### Fixed
- **Redis AUTH Error** - Fixed "ERR AUTH called without password configured" when Redis has no authentication
  - Password now only included in config when explicitly set (non-empty)
  - Prometheus Redis library tried to AUTH when password key existed, even if null

## [1.3.1] - 2026-01-18

### Fixed
- **Docker Build Error** - Fixed "Can't connect to Redis server" during `composer install`
  - PrometheusExporter now uses lazy initialization pattern
  - Storage connection deferred until first metric is recorded
  - Default storage changed from `apcu` to `memory` for safer package discovery
  - Follows same pattern used by Laravel core (RedisServiceProvider, QueueServiceProvider)

### Added
- Tests for disabled Prometheus state
- Test to verify no connection when Prometheus disabled

## [1.3.0] - 2026-01-18

### Added
- **Zero Configuration** - Package works immediately after install with sensible defaults
- **Auto-registered Log Channel** - `observatory` channel automatically configured
  - Writes to `storage/logs/observatory.log`
  - JSON format (Loki/ELK compatible)
  - Daily rotation, 14 days retention
- **Custom Headers Support** - Configurable header extraction for multi-tenant apps
  - Configure via `inbound.custom_headers` option
  - Supports workspace, tenant, correlation IDs, etc.
- **Exporter Config Option** - Added `exporter` config for switching between prometheus/sidmonitor

### Changed
- **Config Structure Simplified** - Merged logger configs into main sections
  - `inbound_logger` → `inbound`
  - `outbound_logger` → `outbound`
  - `job_logger` → `jobs`
  - `exception_logger` → `exceptions`
- **Default Log Channel** - Changed from `daily` to `observatory` (auto-registered)
- **All Loggers Enabled by Default** - No configuration needed to start logging
- **Prometheus Disabled by Default** - Now optional, enable with `OBSERVATORY_PROMETHEUS_ENABLED=true`
- **Improved Grafana Dashboard** - Added Bar Gauge, Gauge panels, better visualizations
- **README Rewritten** - Accurate documentation reflecting current config structure

### Fixed
- Prometheus metrics endpoint removed 'web' middleware (was causing CSRF issues)
- Fixed `auth()` type hints in ExceptionLogger for PHPStan compatibility
- Removed unused `$memoryUsed` parameter in InboundRequestLogger

### Removed
- Hardcoded `X-Workspace-Id` header - Use `custom_headers` config instead
- `service-health-dashboard.json` - Merged into main dashboard

### Breaking Changes
- Config structure changed - Re-publish config if upgrading:
  ```bash
  php artisan vendor:publish --tag=observatory-config --force
  ```
- `X-Workspace-Id` no longer automatically extracted - Add to `custom_headers` if needed

## [1.2.0] - 2026-01-14

### Added
- **Inbound Request Logger** - Log detailed HTTP request/response data to Laravel log channels
- **Outbound Request Logger** - Log external API calls with automatic service detection
- **Job Logger** - Log queue job execution with payload, duration, and memory usage
- **Exception Logger** - Structured exception logging with stack traces and request context
- **Request ID Middleware** - Automatic correlation ID generation and propagation
- **Sensitive Data Masker** - Automatic masking of passwords, tokens, API keys, and PII
- **Service Detection** - Automatically identify external services (Stripe, AWS, Etsy, etc.)
- **Grafana Dashboard Templates** - Pre-built dashboards for Loki data visualization
  - `dashboards/observatory-dashboard.json` - Main overview dashboard
  - `dashboards/service-health-dashboard.json` - External service health dashboard

### Configuration
- New `inbound_logger` config section for HTTP request logging
- New `outbound_logger` config section with service detection patterns
- New `job_logger` config section for queue job logging
- New `exception_logger` config section for exception logging
- New `request_id` config section for correlation ID settings
- Service detection patterns for common services (Stripe, AWS, Shopify, Etsy, etc.)

### Logger Features
- JSON-formatted output optimized for Loki/Grafana
- Configurable log channels per logger type
- Slow request threshold filtering
- Status code filtering (log only errors, etc.)
- Header and body logging with size limits
- Automatic sensitive data masking in all loggers

## [1.1.0] - 2025-12-25

### Added
- Laravel 12.x support

### Changed
- Minimum PHP version is now 8.2 (required by Laravel 12)
- Updated orchestra/testbench to ^10.0 for Laravel 12 testing

## [1.0.0] - 2025-12-25

### Added
- Initial release
- Inbound HTTP request monitoring with automatic middleware
- Outbound HTTP monitoring via Laravel HTTP client integration
- Queue job monitoring with JobProcessing/JobProcessed/JobFailed events
- Exception tracking and counting
- Prometheus metrics export with `/metrics` endpoint
- Support for multiple storage adapters (memory, redis, apc, apcu)
- Custom metrics API (counters, gauges, histograms)
- Configurable path/job/host exclusions
- Basic authentication for metrics endpoint
- SidMonitor exporter stub (coming soon)
- Comprehensive configuration options
- Laravel 10.x and 11.x support
- PHP 8.2+ support

### Prometheus Metrics
- `http_requests_total` - Total HTTP requests counter
- `http_request_duration_seconds` - Request latency histogram
- `http_outbound_requests_total` - Outbound HTTP requests counter
- `http_outbound_duration_seconds` - Outbound latency histogram
- `jobs_processed_total` - Queue jobs counter
- `jobs_duration_seconds` - Job duration histogram
- `exceptions_total` - Exceptions counter
