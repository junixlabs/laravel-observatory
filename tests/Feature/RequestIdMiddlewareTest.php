<?php

namespace JunixLabs\Observatory\Tests\Feature;

use JunixLabs\Observatory\Middleware\RequestIdMiddleware;
use JunixLabs\Observatory\Tests\TestCase;

class RequestIdMiddlewareTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        $router->middleware(RequestIdMiddleware::class)->get('/test-request-id', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'request_id' => $request->attributes->get('request_id'),
            ]);
        });
    }

    public function test_generates_request_id_when_none_provided(): void
    {
        config([
            'observatory.request_id.enabled' => true,
            'observatory.request_id.generate_if_missing' => true,
            'observatory.request_id.include_in_response' => true,
        ]);

        $response = $this->getJson('/test-request-id');

        $response->assertStatus(200);

        $body = $response->json();
        $this->assertNotNull($body['request_id']);
        $this->assertNotEmpty($body['request_id']);
    }

    public function test_uses_existing_request_id_header_when_provided(): void
    {
        config([
            'observatory.request_id.enabled' => true,
            'observatory.request_id.header' => 'X-Request-Id',
            'observatory.request_id.include_in_response' => true,
        ]);

        $existingId = '550e8400-e29b-41d4-a716-446655440000';

        $response = $this->withHeaders([
            'X-Request-Id' => $existingId,
        ])->getJson('/test-request-id');

        $response->assertStatus(200);

        $body = $response->json();
        $this->assertEquals($existingId, $body['request_id']);
    }

    public function test_adds_request_id_to_response_headers(): void
    {
        config([
            'observatory.request_id.enabled' => true,
            'observatory.request_id.header' => 'X-Request-Id',
            'observatory.request_id.include_in_response' => true,
        ]);

        $response = $this->getJson('/test-request-id');

        $response->assertStatus(200);
        $response->assertHeader('X-Request-Id');

        $body = $response->json();
        $this->assertEquals($body['request_id'], $response->headers->get('X-Request-Id'));
    }

    public function test_does_not_generate_when_disabled_via_config(): void
    {
        config([
            'observatory.request_id.enabled' => false,
        ]);

        $response = $this->getJson('/test-request-id');

        $response->assertStatus(200);

        $body = $response->json();
        $this->assertNull($body['request_id']);

        $this->assertNull($response->headers->get('X-Request-Id'));
    }

    public function test_request_id_is_a_valid_uuid_format(): void
    {
        config([
            'observatory.request_id.enabled' => true,
            'observatory.request_id.generate_if_missing' => true,
            'observatory.request_id.include_in_response' => true,
        ]);

        $response = $this->getJson('/test-request-id');

        $response->assertStatus(200);

        $body = $response->json();
        $requestId = $body['request_id'];

        $this->assertNotNull($requestId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $requestId
        );
    }
}
