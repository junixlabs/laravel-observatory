<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Observatory Enabled
    |--------------------------------------------------------------------------
    |
    | This option controls whether Observatory monitoring is enabled.
    |
    */
    'enabled' => env('OBSERVATORY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is used to identify your application in metrics.
    |
    */
    'app_name' => env('OBSERVATORY_APP_NAME', env('APP_NAME', 'laravel')),

    /*
    |--------------------------------------------------------------------------
    | Exporter Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which exporter to use: 'prometheus' or 'sidmonitor'
    | Prometheus is available now, SidMonitor coming soon.
    |
    */
    'exporter' => env('OBSERVATORY_EXPORTER', 'prometheus'),

    /*
    |--------------------------------------------------------------------------
    | Prometheus Configuration
    |--------------------------------------------------------------------------
    */
    'prometheus' => [
        // Metrics endpoint path
        'endpoint' => env('OBSERVATORY_PROMETHEUS_ENDPOINT', '/metrics'),

        // Storage adapter: 'memory', 'redis', 'apc', 'apcu'
        'storage' => env('OBSERVATORY_PROMETHEUS_STORAGE', 'memory'),

        // Redis connection (if using redis storage)
        'redis' => [
            'host' => env('OBSERVATORY_REDIS_HOST', '127.0.0.1'),
            'port' => env('OBSERVATORY_REDIS_PORT', 6379),
            'password' => env('OBSERVATORY_REDIS_PASSWORD', null),
            'database' => env('OBSERVATORY_REDIS_DATABASE', 0),
        ],

        // Histogram buckets for request duration (in seconds)
        'buckets' => [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10],

        // Enable/disable basic auth for metrics endpoint
        'auth' => [
            'enabled' => env('OBSERVATORY_PROMETHEUS_AUTH_ENABLED', false),
            'username' => env('OBSERVATORY_PROMETHEUS_AUTH_USERNAME', 'prometheus'),
            'password' => env('OBSERVATORY_PROMETHEUS_AUTH_PASSWORD', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SidMonitor Configuration (Coming Soon)
    |--------------------------------------------------------------------------
    */
    'sidmonitor' => [
        'endpoint' => env('OBSERVATORY_SIDMONITOR_ENDPOINT', 'https://api.sidmonitor.com'),
        'api_key' => env('OBSERVATORY_SIDMONITOR_API_KEY', ''),
        'project_id' => env('OBSERVATORY_SIDMONITOR_PROJECT_ID', ''),

        // Batch settings for efficient data transmission
        'batch' => [
            'size' => env('OBSERVATORY_SIDMONITOR_BATCH_SIZE', 100),
            'interval' => env('OBSERVATORY_SIDMONITOR_BATCH_INTERVAL', 10), // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbound Request Monitoring (Metrics)
    |--------------------------------------------------------------------------
    */
    'inbound' => [
        'enabled' => env('OBSERVATORY_INBOUND_ENABLED', true),

        // Paths to exclude from monitoring (supports wildcards)
        'exclude_paths' => [
            'telescope*',
            'horizon*',
            '_debugbar*',
            'health',
            'metrics',
        ],

        // HTTP methods to monitor
        'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],

        // Record request/response body for metrics (use with caution - can be large)
        'record_body' => env('OBSERVATORY_INBOUND_RECORD_BODY', false),

        // Maximum body size to record (in bytes)
        'max_body_size' => env('OBSERVATORY_INBOUND_MAX_BODY_SIZE', 64000),

        // Headers to exclude from recording (sensitive data)
        'exclude_headers' => [
            'authorization',
            'cookie',
            'x-api-key',
            'x-auth-token',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbound Request Logger (Log Channel Output)
    |--------------------------------------------------------------------------
    |
    | Log detailed HTTP request/response data to Laravel log channels.
    | This is separate from metrics - useful for debugging, audit trails,
    | and integration with log aggregators like Loki/Grafana.
    |
    */
    'inbound_logger' => [
        'enabled' => env('OBSERVATORY_INBOUND_LOGGER_ENABLED', false),

        // Laravel log channel to use (e.g., 'http_monitor', 'daily', 'single')
        'channel' => env('OBSERVATORY_INBOUND_LOGGER_CHANNEL', 'daily'),

        // Log request details
        'log_request_headers' => env('OBSERVATORY_LOG_REQUEST_HEADERS', true),
        'log_request_body' => env('OBSERVATORY_LOG_REQUEST_BODY', false),

        // Log response details
        'log_response_headers' => env('OBSERVATORY_LOG_RESPONSE_HEADERS', false),
        'log_response_body' => env('OBSERVATORY_LOG_RESPONSE_BODY', false),

        // Size limits (in bytes)
        'max_request_body_size' => env('OBSERVATORY_MAX_REQUEST_BODY_SIZE', 64000),
        'max_response_body_size' => env('OBSERVATORY_MAX_RESPONSE_BODY_SIZE', 64000),

        // Paths to exclude from logging (supports wildcards)
        'exclude_paths' => [
            'telescope*',
            'horizon*',
            '_debugbar*',
            'health',
            'metrics',
            'api/v1/ping',
        ],

        // Only log requests matching these status codes (empty = log all)
        // Example: [500, 502, 503] to only log server errors
        'only_status_codes' => [],

        // Slow request threshold in milliseconds (0 = disabled)
        // When set, only requests slower than this will be logged
        'slow_threshold_ms' => env('OBSERVATORY_SLOW_THRESHOLD_MS', 0),

        // Headers to exclude from logging (case-insensitive)
        'exclude_headers' => [
            'authorization',
            'cookie',
            'set-cookie',
            'x-api-key',
            'x-auth-token',
            'x-csrf-token',
        ],

        // Fields to mask in request/response body (supports dot notation)
        'mask_fields' => [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'token',
            'secret',
            'api_key',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
        ],

        // Mask replacement string
        'mask_replacement' => '********',

        // Include memory usage in logs
        'log_memory' => env('OBSERVATORY_LOG_MEMORY', true),

        // Include authenticated user info in logs
        'log_user' => env('OBSERVATORY_LOG_USER', true),

        // Custom context to include in every log entry
        // Useful for adding tenant_id, workspace_id, etc.
        'custom_context' => [
            // 'tenant_id' => null,  // Will be resolved at runtime
        ],

        // Labels for log aggregators (Loki, etc.)
        'labels' => [
            'service' => env('OBSERVATORY_SERVICE_NAME', 'api'),
            'environment' => env('APP_ENV', 'production'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request ID Tracking
    |--------------------------------------------------------------------------
    |
    | Automatically generate and track request IDs for distributed tracing.
    |
    */
    'request_id' => [
        'enabled' => env('OBSERVATORY_REQUEST_ID_ENABLED', true),

        // Header name to read/write request ID
        'header_name' => env('OBSERVATORY_REQUEST_ID_HEADER', 'X-Request-Id'),

        // Generate UUID if not provided in request
        'generate_if_missing' => true,

        // Include request ID in response headers
        'include_in_response' => true,

        // Add request ID to Laravel's Log context
        'include_in_log_context' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound HTTP Monitoring
    |--------------------------------------------------------------------------
    */
    'outbound' => [
        'enabled' => env('OBSERVATORY_OUTBOUND_ENABLED', true),

        // Hosts to exclude from monitoring
        'exclude_hosts' => [
            'localhost',
            '127.0.0.1',
        ],

        // Record request/response body
        'record_body' => env('OBSERVATORY_OUTBOUND_RECORD_BODY', false),

        // Maximum body size to record (in bytes)
        'max_body_size' => env('OBSERVATORY_OUTBOUND_MAX_BODY_SIZE', 64000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound HTTP Logger (Log Channel Output)
    |--------------------------------------------------------------------------
    |
    | Log outbound HTTP requests to Laravel log channels.
    | Useful for debugging external API calls, audit trails,
    | and integration with log aggregators like Loki/Grafana.
    |
    */
    'outbound_logger' => [
        'enabled' => env('OBSERVATORY_OUTBOUND_LOGGER_ENABLED', false),

        // Laravel log channel to use
        'channel' => env('OBSERVATORY_OUTBOUND_LOGGER_CHANNEL', 'http_monitor'),

        // Log request details
        'log_request_headers' => env('OBSERVATORY_OUTBOUND_LOG_REQUEST_HEADERS', true),
        'log_request_body' => env('OBSERVATORY_OUTBOUND_LOG_REQUEST_BODY', false),

        // Log response details
        'log_response_headers' => env('OBSERVATORY_OUTBOUND_LOG_RESPONSE_HEADERS', false),
        'log_response_body' => env('OBSERVATORY_OUTBOUND_LOG_RESPONSE_BODY', false),

        // Size limits (in bytes)
        'max_request_body_size' => env('OBSERVATORY_OUTBOUND_MAX_REQUEST_BODY_SIZE', 64000),
        'max_response_body_size' => env('OBSERVATORY_OUTBOUND_MAX_RESPONSE_BODY_SIZE', 64000),

        // Hosts to exclude from logging
        'exclude_hosts' => [
            'localhost',
            '127.0.0.1',
        ],

        // Only log requests matching these status codes (empty = log all)
        // Example: [500, 502, 503] to only log server errors
        'only_status_codes' => [],

        // Slow request threshold in milliseconds (0 = disabled)
        'slow_threshold_ms' => env('OBSERVATORY_OUTBOUND_SLOW_THRESHOLD_MS', 0),

        // Headers to exclude from logging (case-insensitive)
        'exclude_headers' => [
            'authorization',
            'x-api-key',
            'x-auth-token',
            'x-amz-security-token',
            'cookie',
        ],

        // Service detection: map host patterns to service names
        // This helps group logs by external service in dashboards
        'service_detection' => [
            '*.etsy.com' => 'etsy',
            'openapi.etsy.com' => 'etsy',
            '*.amazon.com' => 'amazon',
            'advertising-api.amazon.com' => 'amazon_ads',
            '*.amazonaws.com' => 'aws',
            '*.shopify.com' => 'shopify',
            '*.tiktok.com' => 'tiktok',
            '*.printify.com' => 'printify',
            '*.stripe.com' => 'stripe',
            'api.stripe.com' => 'stripe',
            '*.sendgrid.com' => 'sendgrid',
            '*.sentry.io' => 'sentry',
        ],

        // Labels for log aggregators (Loki, etc.)
        'labels' => [
            'type' => 'outbound',
            'environment' => env('APP_ENV', 'production'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Job Monitoring (Metrics)
    |--------------------------------------------------------------------------
    */
    'jobs' => [
        'enabled' => env('OBSERVATORY_JOBS_ENABLED', true),

        // Jobs to exclude from monitoring (class names)
        'exclude_jobs' => [
            // 'App\Jobs\SomeInternalJob',
        ],

        // Record job payload
        'record_payload' => env('OBSERVATORY_JOBS_RECORD_PAYLOAD', false),

        // Maximum payload size to record (in bytes)
        'max_payload_size' => env('OBSERVATORY_JOBS_MAX_PAYLOAD_SIZE', 64000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Logger (Log Channel Output)
    |--------------------------------------------------------------------------
    |
    | Log detailed job execution data to Laravel log channels.
    | Useful for debugging, audit trails, and integration with Loki/Grafana.
    |
    */
    'job_logger' => [
        'enabled' => env('OBSERVATORY_JOB_LOGGER_ENABLED', false),

        // Laravel log channel to use
        'channel' => env('OBSERVATORY_JOB_LOGGER_CHANNEL', 'daily'),

        // Jobs to exclude from logging (class names or patterns)
        'exclude_jobs' => [
            // 'App\Jobs\SomeInternalJob',
            // 'App\Jobs\Heartbeat*',
        ],

        // Only log jobs with these statuses (empty = log all)
        // Options: 'processed', 'failed'
        'only_statuses' => [],

        // Slow job threshold in milliseconds (0 = disabled)
        // When set, only jobs slower than this will be logged
        'slow_threshold_ms' => env('OBSERVATORY_JOB_SLOW_THRESHOLD_MS', 0),

        // Include memory usage in logs
        'log_memory' => env('OBSERVATORY_JOB_LOG_MEMORY', true),

        // Include job payload in logs (use with caution - can be large)
        'log_payload' => env('OBSERVATORY_JOB_LOG_PAYLOAD', false),

        // Maximum payload size to log (in bytes)
        'max_payload_size' => env('OBSERVATORY_JOB_MAX_PAYLOAD_SIZE', 64000),

        // Include stack trace for failed jobs
        'log_stack_trace' => env('OBSERVATORY_JOB_LOG_STACK_TRACE', true),

        // Maximum stack frames to include
        'max_stack_frames' => 10,

        // Labels for log aggregators (Loki, etc.)
        'labels' => [
            'type' => 'job',
            'environment' => env('APP_ENV', 'production'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception Tracking (Metrics)
    |--------------------------------------------------------------------------
    */
    'exceptions' => [
        'enabled' => env('OBSERVATORY_EXCEPTIONS_ENABLED', true),

        // Exception classes to ignore
        'ignore' => [
            Illuminate\Auth\AuthenticationException::class,
            Illuminate\Auth\Access\AuthorizationException::class,
            Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            Illuminate\Validation\ValidationException::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception Logger (Log Channel Output)
    |--------------------------------------------------------------------------
    |
    | Log detailed exception data to Laravel log channels.
    | Provides full stack traces, request context, and user info for debugging.
    |
    */
    'exception_logger' => [
        'enabled' => env('OBSERVATORY_EXCEPTION_LOGGER_ENABLED', false),

        // Laravel log channel to use
        'channel' => env('OBSERVATORY_EXCEPTION_LOGGER_CHANNEL', 'daily'),

        // Exception classes to ignore (will not be logged)
        'ignore' => [
            Illuminate\Auth\AuthenticationException::class,
            Illuminate\Auth\Access\AuthorizationException::class,
            Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            Illuminate\Validation\ValidationException::class,
        ],

        // Ignore exceptions matching these patterns
        'ignore_patterns' => [
            // 'App\Exceptions\*BusinessException',
        ],

        // Include request context (method, url, headers, body)
        'log_request_context' => env('OBSERVATORY_EXCEPTION_LOG_REQUEST', true),

        // Include request headers in context
        'log_request_headers' => env('OBSERVATORY_EXCEPTION_LOG_HEADERS', false),

        // Include request body in context
        'log_request_body' => env('OBSERVATORY_EXCEPTION_LOG_BODY', false),

        // Include authenticated user info
        'log_user' => env('OBSERVATORY_EXCEPTION_LOG_USER', true),

        // Include full stack trace
        'log_stack_trace' => env('OBSERVATORY_EXCEPTION_LOG_STACK_TRACE', true),

        // Maximum stack frames to include
        'max_stack_frames' => 20,

        // Include function arguments in stack trace (can be large/sensitive)
        'log_arguments' => false,

        // Include previous exception chain
        'log_previous' => true,

        // Maximum depth for previous exception chain
        'max_previous_depth' => 3,

        // Include memory usage at time of exception
        'log_memory' => env('OBSERVATORY_EXCEPTION_LOG_MEMORY', true),

        // Critical exceptions (will be labeled as 'critical' severity)
        'critical_exceptions' => [
            \Error::class,
            \ParseError::class,
            \TypeError::class,
        ],

        // Warning exceptions (will be labeled as 'warning' severity)
        'warning_exceptions' => [
            Illuminate\Validation\ValidationException::class,
            Illuminate\Auth\AuthenticationException::class,
        ],

        // Labels for log aggregators (Loki, etc.)
        'labels' => [
            'type' => 'exception',
            'environment' => env('APP_ENV', 'production'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Labels
    |--------------------------------------------------------------------------
    |
    | Add custom labels to all metrics. Useful for multi-tenant or
    | multi-environment setups.
    |
    */
    'labels' => [
        'environment' => env('APP_ENV', 'production'),
        // 'tenant' => 'default',
        // 'region' => 'us-east-1',
    ],
];
