<?php

namespace JunixLabs\Observatory\Tests\Unit;

use JunixLabs\Observatory\Support\SensitiveDataMasker;
use JunixLabs\Observatory\Tests\TestCase;

class SensitiveDataMaskerTest extends TestCase
{
    protected SensitiveDataMasker $masker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->masker = new SensitiveDataMasker(
            maskFields: ['password', 'token', 'secret', 'api_key', 'credit_card', 'cvv'],
            maskReplacement: '********',
            excludeHeaders: ['authorization', 'x-api-key']
        );
    }

    public function test_mask_array_masks_sensitive_fields(): void
    {
        $data = [
            'username' => 'john',
            'password' => 'super-secret-123',
            'token' => 'abc-token-xyz',
            'secret' => 'my-secret',
            'api_key' => 'key-12345',
            'credit_card' => '4111111111111111',
            'cvv' => '123',
        ];

        $result = $this->masker->maskArray($data);

        $this->assertEquals('********', $result['password']);
        $this->assertEquals('********', $result['token']);
        $this->assertEquals('********', $result['secret']);
        $this->assertEquals('********', $result['api_key']);
        $this->assertEquals('********', $result['credit_card']);
        $this->assertEquals('********', $result['cvv']);
    }

    public function test_mask_array_does_not_mask_non_sensitive_fields(): void
    {
        $data = [
            'username' => 'john',
            'email' => 'john@example.com',
            'age' => 30,
            'active' => true,
        ];

        $result = $this->masker->maskArray($data);

        $this->assertEquals('john', $result['username']);
        $this->assertEquals('john@example.com', $result['email']);
        $this->assertEquals(30, $result['age']);
        $this->assertTrue($result['active']);
    }

    public function test_mask_array_handles_nested_arrays_recursively(): void
    {
        $data = [
            'user' => [
                'name' => 'john',
                'credentials' => [
                    'password' => 'nested-secret',
                    'token' => 'nested-token',
                ],
            ],
            'meta' => [
                'api_key' => 'deep-key',
                'version' => '1.0',
            ],
        ];

        $result = $this->masker->maskArray($data);

        $this->assertEquals('john', $result['user']['name']);
        $this->assertEquals('********', $result['user']['credentials']['password']);
        $this->assertEquals('********', $result['user']['credentials']['token']);
        $this->assertEquals('********', $result['meta']['api_key']);
        $this->assertEquals('1.0', $result['meta']['version']);
    }

    public function test_mask_json_masks_json_string_content(): void
    {
        $json = json_encode([
            'username' => 'john',
            'password' => 'secret-pass',
            'token' => 'my-token',
        ]);

        $result = $this->masker->maskJson($json);
        $decoded = json_decode($result, true);

        $this->assertEquals('john', $decoded['username']);
        $this->assertEquals('********', $decoded['password']);
        $this->assertEquals('********', $decoded['token']);
    }

    public function test_mask_json_returns_original_string_if_invalid_json(): void
    {
        $invalidJson = 'this is not valid json {{{';

        $result = $this->masker->maskJson($invalidJson);

        $this->assertEquals($invalidJson, $result);
    }

    public function test_filter_headers_removes_sensitive_headers(): void
    {
        $headers = [
            'Authorization' => 'Bearer some-token',
            'X-Api-Key' => 'my-api-key',
            'Content-Type' => 'application/json',
        ];

        $result = $this->masker->filterHeaders($headers);

        $this->assertEquals('********', $result['Authorization']);
        $this->assertEquals('********', $result['X-Api-Key']);
    }

    public function test_filter_headers_keeps_non_sensitive_headers(): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'text/html',
            'User-Agent' => 'Mozilla/5.0',
        ];

        $result = $this->masker->filterHeaders($headers);

        $this->assertEquals('application/json', $result['Content-Type']);
        $this->assertEquals('text/html', $result['Accept']);
        $this->assertEquals('Mozilla/5.0', $result['User-Agent']);
    }

    public function test_mask_query_string_masks_sensitive_query_params(): void
    {
        $queryString = 'username=john&password=secret&token=abc123&page=1';

        $result = $this->masker->maskQueryString($queryString);

        parse_str($result, $params);

        $this->assertEquals('john', $params['username']);
        $this->assertEquals('********', $params['password']);
        $this->assertEquals('********', $params['token']);
        $this->assertEquals('1', $params['page']);
    }

    public function test_normalize_array_limits_items_to_max_items(): void
    {
        $data = [];
        for ($i = 0; $i < 10; $i++) {
            $data["item_{$i}"] = "value_{$i}";
        }

        $result = $this->masker->normalizeArray($data, maxItems: 3);

        // 3 items + 1 truncation notice
        $this->assertCount(4, $result);
        $this->assertArrayHasKey('...', $result);
        $this->assertStringContainsString('truncated', $result['...']);
    }

    public function test_normalize_array_limits_depth_to_max_depth(): void
    {
        $data = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => 'deep-value',
                    ],
                ],
            ],
        ];

        $result = $this->masker->normalizeArray($data, maxDepth: 2);

        $this->assertArrayHasKey('level1', $result);
        $this->assertArrayHasKey('level2', $result['level1']);
        $this->assertEquals(['...' => 'Max depth reached'], $result['level1']['level2']);
    }

    public function test_truncate_truncates_long_strings(): void
    {
        $longString = str_repeat('a', 200);

        $result = $this->masker->truncate($longString, 100);

        $this->assertStringStartsWith(str_repeat('a', 100), $result);
        $this->assertStringEndsWith('... [truncated]', $result);
        $this->assertLessThan(strlen($longString), strlen($result));
    }

    public function test_truncate_returns_short_strings_unchanged(): void
    {
        $shortString = 'Hello, world!';

        $result = $this->masker->truncate($shortString, 100);

        $this->assertEquals($shortString, $result);
    }

    public function test_from_config_reads_from_correct_config_keys(): void
    {
        config([
            'observatory.inbound.mask_fields' => ['password', 'secret'],
            'observatory.inbound.mask_replacement' => '[REDACTED]',
            'observatory.inbound.exclude_headers' => ['authorization'],
        ]);

        $masker = SensitiveDataMasker::fromConfig();

        // Verify mask_fields config is used
        $masked = $masker->maskArray(['password' => 'test', 'name' => 'john']);
        $this->assertEquals('[REDACTED]', $masked['password']);
        $this->assertEquals('john', $masked['name']);

        // Verify mask_replacement config is used
        $masked = $masker->maskArray(['secret' => 'test']);
        $this->assertEquals('[REDACTED]', $masked['secret']);

        // Verify exclude_headers config is used
        $headers = $masker->filterHeaders([
            'Authorization' => 'Bearer token',
            'Content-Type' => 'application/json',
        ]);
        $this->assertEquals('[REDACTED]', $headers['Authorization']);
        $this->assertEquals('application/json', $headers['Content-Type']);
    }
}
