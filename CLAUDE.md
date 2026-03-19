# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

Laravel Observatory (`junixlabs/laravel-observatory`) — a Laravel package that provides observability for HTTP requests (inbound + outbound), queue jobs, scheduled tasks, and exceptions. Outputs structured JSON logs (Loki/ELK compatible) and optional Prometheus metrics. Part of the SidMonitor APM platform.

## Commands

```bash
composer install                    # Install dependencies
composer test                       # Run PHPUnit tests (Unit + Feature suites)
vendor/bin/phpunit --filter=ClassName  # Run a single test class
vendor/bin/phpunit --filter=testMethodName  # Run a single test method
composer format                     # Fix code style (Laravel Pint, PSR-12)
composer format -- --test           # Check code style without fixing
composer analyse                    # Static analysis (PHPStan level 5, src/ only)
```

## Architecture

### Collect → Log → Export pipeline

Every monitored event follows the same flow:
1. **Collector** (`src/Collectors/`) — hooks into Laravel events/middleware to capture timing and metadata
2. **Logger** (`src/Loggers/`) — writes structured JSON to the configured log channel
3. **Exporter** (`src/Exporters/`) — records metrics via `ExporterInterface`

The `ObservatoryServiceProvider` wires everything together: registers singletons, pushes middleware, listens to queue/scheduler events, and wraps the exception handler.

### Exporter system

Two exporters implement `ExporterInterface` (`src/Contracts/`):
- **PrometheusExporter** — pull-based, stores in APCu/Redis/memory, scraped via `/metrics` endpoint. Lazy-initializes storage.
- **SidMonitorExporter** — push-based, buffers data in-memory then flushes batches to `POST /api/ingest/batch` (logs) and `POST /api/ingest/jobs/batch` (jobs/tasks) with `X-API-Key` auth. Includes circuit breaker (N failures → cooldown period → half-open retry). Auto-flushes on batch size or time interval; final flush on `app->terminating()`.

Selected via `OBSERVATORY_EXPORTER` env var (`prometheus` | `sidmonitor`).

### Outbound monitoring strategy

Two approaches depending on Laravel version:
- **Laravel 10.14+**: Guzzle-level `Http::globalMiddleware()` — captures all HTTP requests
- **Laravel 8–10.13**: Event-based fallback via `RequestSending`/`ResponseReceived`/`ConnectionFailed` — captures only `Http` facade calls

### Testing setup

Uses Orchestra Testbench with `ObservatoryServiceProvider` auto-loaded. Tests use in-memory Prometheus storage. Test suites: `Unit` and `Feature` in `tests/`.

### Config

Single config file `config/observatory.php` — all features enabled by default, controlled via `OBSERVATORY_*` env vars. Config covers: inbound/outbound/jobs/scheduled_tasks/exceptions toggles, path/host/job exclusions, sensitive data masking, Prometheus storage, SidMonitor batch/circuit-breaker settings.

## CI

GitHub Actions matrix: PHP 8.1–8.3 × Laravel 10–11 (excludes PHP 8.1 + Laravel 11). Runs `composer test` and `composer format -- --test`.
