# Changelog

All notable changes to `laravel-observatory` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
