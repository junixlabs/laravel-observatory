<?php

namespace JunixLabs\Observatory\Loggers;

use Illuminate\Support\Facades\Log;
use JunixLabs\Observatory\Support\SensitiveDataMasker;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OutboundRequestLogger
{
    protected SensitiveDataMasker $masker;

    protected array $config;

    public function __construct(?SensitiveDataMasker $masker = null)
    {
        $this->masker = $masker ?? SensitiveDataMasker::fromConfig();
        $this->config = config('observatory.outbound_logger', []);
    }

    /**
     * Check if logger is enabled
     */
    public function isEnabled(): bool
    {
        return config('observatory.outbound_logger.enabled', false);
    }

    /**
     * Log outbound HTTP request
     */
    public function log(
        RequestInterface $request,
        ?ResponseInterface $response,
        float $duration,
        ?\Throwable $error = null
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $uri = $request->getUri();
        $host = $uri->getHost();

        if (! $this->shouldLog($host, $response, $duration)) {
            return;
        }

        $logData = $this->buildLogData($request, $response, $duration, $error);
        $channel = $this->config['channel'] ?? 'http_monitor';

        if ($error || ($response && $response->getStatusCode() >= 400)) {
            Log::channel($channel)->error('HTTP_OUTBOUND', $logData);
        } else {
            Log::channel($channel)->info('HTTP_OUTBOUND', $logData);
        }
    }

    /**
     * Check if request should be logged
     */
    protected function shouldLog(string $host, ?ResponseInterface $response, float $duration): bool
    {
        // Check excluded hosts
        $excludeHosts = $this->config['exclude_hosts'] ?? [];
        foreach ($excludeHosts as $excludeHost) {
            if (strcasecmp($host, $excludeHost) === 0) {
                return false;
            }
            // Wildcard match
            if (str_contains($excludeHost, '*')) {
                $pattern = str_replace('*', '.*', $excludeHost);
                if (preg_match("/^{$pattern}$/i", $host)) {
                    return false;
                }
            }
        }

        // Check status code filter
        $onlyStatusCodes = $this->config['only_status_codes'] ?? [];
        if (! empty($onlyStatusCodes) && $response) {
            if (! in_array($response->getStatusCode(), $onlyStatusCodes)) {
                return false;
            }
        }

        // Check slow threshold
        $slowThreshold = $this->config['slow_threshold_ms'] ?? 0;
        if ($slowThreshold > 0) {
            $durationMs = $duration * 1000;
            if ($durationMs < $slowThreshold) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build log data array
     */
    protected function buildLogData(
        RequestInterface $request,
        ?ResponseInterface $response,
        float $duration,
        ?\Throwable $error = null
    ): array {
        $uri = $request->getUri();
        $host = $uri->getHost();
        $durationMs = $duration * 1000;

        $data = [
            'request_id' => $this->getRequestId(),
            'type' => 'outbound',
            'service' => $this->detectService($host),
            'method' => $request->getMethod(),
            'url' => (string) $uri,
            'host' => $host,
            'path' => $uri->getPath(),
            'status_code' => $response ? $response->getStatusCode() : 0,
            'duration_ms' => round($durationMs, 2),
            'timestamp' => now()->toIso8601String(),
        ];

        // Add error info
        if ($error) {
            $data['error'] = [
                'class' => get_class($error),
                'message' => $error->getMessage(),
            ];
        }

        // Add request headers
        if ($this->config['log_request_headers'] ?? false) {
            $data['request_headers'] = $this->maskHeaders($request->getHeaders());
        }

        // Add response headers
        if ($response && ($this->config['log_response_headers'] ?? false)) {
            $data['response_headers'] = $this->maskHeaders($response->getHeaders());
        }

        // Add request body
        if ($this->config['log_request_body'] ?? false) {
            $data['request_body'] = $this->getRequestBody($request);
        }

        // Add response body
        if ($response && ($this->config['log_response_body'] ?? false)) {
            $data['response_body'] = $this->getResponseBody($response);
        }

        // Add labels for Loki
        $data['labels'] = array_merge(
            $this->config['labels'] ?? [],
            ['service' => $data['service']]
        );

        return $data;
    }

    /**
     * Detect service name from host
     */
    protected function detectService(string $host): string
    {
        $serviceMap = $this->config['service_detection'] ?? [];

        foreach ($serviceMap as $pattern => $serviceName) {
            // Exact match
            if (strcasecmp($host, $pattern) === 0) {
                return $serviceName;
            }

            // Wildcard match (e.g., *.etsy.com => etsy)
            if (str_contains($pattern, '*')) {
                $regex = str_replace(['*', '.'], ['.*', '\.'], $pattern);
                if (preg_match("/^{$regex}$/i", $host)) {
                    return $serviceName;
                }
            }
        }

        // Default: extract domain name
        $parts = explode('.', $host);
        if (count($parts) >= 2) {
            return $parts[count($parts) - 2]; // e.g., api.etsy.com -> etsy
        }

        return $host;
    }

    /**
     * Get current request ID from context
     */
    protected function getRequestId(): ?string
    {
        // Try to get from current request
        if (function_exists('request') && request()) {
            return request()->attributes->get('request_id');
        }

        return null;
    }

    /**
     * Mask sensitive headers
     */
    protected function maskHeaders(array $headers): array
    {
        $excludeHeaders = array_map('strtolower', $this->config['exclude_headers'] ?? [
            'authorization',
            'x-api-key',
            'x-auth-token',
            'cookie',
        ]);

        $masked = [];
        foreach ($headers as $name => $values) {
            $lowerName = strtolower($name);
            if (in_array($lowerName, $excludeHeaders)) {
                $masked[$name] = '********';
            } else {
                $masked[$name] = is_array($values) ? implode(', ', $values) : $values;
            }
        }

        return $masked;
    }

    /**
     * Get request body (masked)
     */
    protected function getRequestBody(RequestInterface $request): mixed
    {
        $maxSize = $this->config['max_request_body_size'] ?? 64000;
        $body = (string) $request->getBody();

        // Rewind the stream for subsequent reads
        $request->getBody()->rewind();

        if (empty($body)) {
            return null;
        }

        // Try to parse as JSON
        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $this->masker->maskArray($decoded);
        }

        // Return truncated raw content
        return $this->masker->truncate($body, $maxSize);
    }

    /**
     * Get response body (masked)
     */
    protected function getResponseBody(ResponseInterface $response): mixed
    {
        $maxSize = $this->config['max_response_body_size'] ?? 64000;
        $body = (string) $response->getBody();

        // Rewind the stream for subsequent reads
        $response->getBody()->rewind();

        if (empty($body)) {
            return null;
        }

        // Try to parse as JSON
        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $this->masker->maskArray($decoded);
        }

        // Return truncated raw content
        return $this->masker->truncate($body, $maxSize);
    }
}
