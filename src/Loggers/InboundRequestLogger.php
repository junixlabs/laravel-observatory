<?php

namespace JunixLabs\Observatory\Loggers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JunixLabs\Observatory\Support\SensitiveDataMasker;
use Symfony\Component\HttpFoundation\Response;

class InboundRequestLogger
{
    protected SensitiveDataMasker $masker;

    protected array $config;

    protected ?float $startTime = null;

    protected ?float $startMemory = null;

    protected ?string $requestId = null;

    public function __construct(?SensitiveDataMasker $masker = null)
    {
        $this->masker = $masker ?? SensitiveDataMasker::fromConfig();
        $this->config = config('observatory.inbound_logger', []);
    }

    /**
     * Check if logger is enabled
     */
    public function isEnabled(): bool
    {
        return config('observatory.inbound_logger.enabled', false);
    }

    /**
     * Start timing the request
     */
    public function start(Request $request): void
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        $this->requestId = $request->attributes->get('request_id');
    }

    /**
     * Log the completed request
     */
    public function log(Request $request, Response $response): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if (! $this->shouldLog($request, $response)) {
            return;
        }

        $duration = $this->startTime ? (microtime(true) - $this->startTime) * 1000 : 0;
        $memoryUsed = $this->startMemory ? memory_get_usage(true) - $this->startMemory : 0;
        $peakMemory = memory_get_peak_usage(true);

        $logData = $this->buildLogData($request, $response, $duration, $memoryUsed, $peakMemory);

        $channel = $this->config['channel'] ?? 'daily';

        Log::channel($channel)->info('HTTP_REQUEST', $logData);

        // Reset for next request
        $this->startTime = null;
        $this->startMemory = null;
        $this->requestId = null;
    }

    /**
     * Check if request should be logged
     */
    public function shouldLog(Request $request, Response $response): bool
    {
        // Check excluded paths
        $excludePaths = $this->config['exclude_paths'] ?? [];
        $path = $request->path();

        foreach ($excludePaths as $pattern) {
            if (fnmatch($pattern, $path)) {
                return false;
            }
        }

        // Check status code filter
        $onlyStatusCodes = $this->config['only_status_codes'] ?? [];
        if (! empty($onlyStatusCodes) && ! in_array($response->getStatusCode(), $onlyStatusCodes)) {
            return false;
        }

        // Check slow threshold
        $slowThreshold = $this->config['slow_threshold_ms'] ?? 0;
        if ($slowThreshold > 0 && $this->startTime) {
            $duration = (microtime(true) - $this->startTime) * 1000;
            if ($duration < $slowThreshold) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build the log data array
     */
    protected function buildLogData(
        Request $request,
        Response $response,
        float $duration,
        int $memoryUsed,
        int $peakMemory
    ): array {
        $data = [
            'request_id' => $this->requestId ?? $request->attributes->get('request_id'),
            'type' => 'inbound',
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'host' => $request->getHost(),
            'path' => $request->path(),
            'route' => $this->getRouteName($request),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => round($duration, 2),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ];

        // Add memory info
        if ($this->config['log_memory'] ?? true) {
            $data['memory'] = [
                'used_mb' => round($memoryUsed / 1024 / 1024, 2),
                'peak_mb' => round($peakMemory / 1024 / 1024, 2),
            ];
        }

        // Add user info
        if ($this->config['log_user'] ?? true) {
            $user = $request->user();
            if ($user) {
                $data['user_id'] = $user->id ?? null;
            }

            // Add workspace_id from header if exists
            $workspaceId = $request->header('X-Workspace-Id');
            if ($workspaceId) {
                $data['workspace_id'] = $workspaceId;
            }
        }

        // Add request headers
        if ($this->config['log_request_headers'] ?? true) {
            $data['request_headers'] = $this->masker->filterHeaders($request->headers->all());
        }

        // Add request body
        if ($this->config['log_request_body'] ?? false) {
            $data['request_body'] = $this->getRequestBody($request);
        }

        // Add response headers
        if ($this->config['log_response_headers'] ?? false) {
            $data['response_headers'] = $this->masker->filterHeaders($response->headers->all());
        }

        // Add response body
        if ($this->config['log_response_body'] ?? false) {
            $data['response_body'] = $this->getResponseBody($response);
        }

        // Add query parameters
        $queryParams = $request->query();
        if (! empty($queryParams)) {
            $data['query_params'] = $this->masker->maskArray($queryParams);
        }

        // Add custom context
        $customContext = $this->config['custom_context'] ?? [];
        if (! empty($customContext)) {
            $data['context'] = $customContext;
        }

        // Add labels for log aggregators
        $labels = $this->config['labels'] ?? [];
        if (! empty($labels)) {
            $data['labels'] = $labels;
        }

        return $data;
    }

    /**
     * Get masked request body
     */
    protected function getRequestBody(Request $request): mixed
    {
        $maxSize = $this->config['max_request_body_size'] ?? 64000;

        // Try to get as array first (for JSON/form data)
        $content = $request->all();

        if (! empty($content)) {
            return $this->masker->maskArray($content);
        }

        // Fall back to raw content
        $rawContent = $request->getContent();

        if (empty($rawContent)) {
            return null;
        }

        // Try to parse as JSON
        $decoded = json_decode($rawContent, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $this->masker->maskArray($decoded);
        }

        // Return truncated raw content
        return $this->masker->truncate($rawContent, $maxSize);
    }

    /**
     * Get masked response body
     */
    protected function getResponseBody(Response $response): mixed
    {
        $maxSize = $this->config['max_response_body_size'] ?? 64000;
        $content = $response->getContent();

        if (empty($content)) {
            return null;
        }

        // Try to parse as JSON
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $this->masker->maskArray($decoded);
        }

        // Return truncated raw content
        return $this->masker->truncate($content, $maxSize);
    }

    /**
     * Get route name or pattern
     */
    protected function getRouteName(Request $request): string
    {
        $route = $request->route();

        if ($route === null) {
            return 'unknown';
        }

        $name = $route->getName();
        if ($name) {
            return $name;
        }

        $uri = $route->uri();

        return $uri ?: 'unknown';
    }

    /**
     * Set custom request ID
     */
    public function setRequestId(string $requestId): self
    {
        $this->requestId = $requestId;

        return $this;
    }
}
