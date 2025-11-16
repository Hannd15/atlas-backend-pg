<?php

namespace Tests\Unit\Services;

use App\Services\AtlasAuthService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AtlasAuthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.atlas_auth.url', 'https://auth.example');
        Config::set('services.atlas_auth.timeout', 10);
    }

    public function test_verify_token_posts_payload_and_returns_decoded_response(): void
    {
        $capturedRequest = [];

        Http::fake(function (HttpRequest $request) use (&$capturedRequest) {
            $capturedRequest = [
                'url' => $request->url(),
                'data' => $request->data(),
                'headers' => $request->headers(),
            ];

            return Http::response([
                'authorized' => true,
                'user' => [
                    'id' => 99,
                    'email' => 'tester@example.com',
                    'roles' => ['Director'],
                ],
            ], 200);
        });

        $service = new AtlasAuthService;

        $result = $service->verifyToken('secure-token', [' admin ', '', 'Manager'], [' view ', null, 'edit']);

        $this->assertNotEmpty($capturedRequest, 'Authentication request was not issued.');
        $this->assertSame('https://auth.example/api/auth/token/verify', $capturedRequest['url']);
        $this->assertSame([
            'roles' => ['admin', 'Manager'],
            'permissions' => ['view', 'edit'],
        ], $capturedRequest['data']);
        $this->assertSame('Bearer secure-token', $capturedRequest['headers']['Authorization'][0] ?? null);

        $this->assertTrue($result['authorized']);
        $this->assertSame(99, $result['user']['id']);
        $this->assertSame('tester@example.com', $result['user']['email']);
    }

    public function test_verify_token_throws_http_exception_when_service_returns_unauthorized(): void
    {
        Http::fake([
            'https://auth.example/api/auth/token/verify' => Http::response([
                'message' => 'Invalid token.',
            ], 401),
        ]);

        $service = new AtlasAuthService;

        try {
            $service->verifyToken('bad-token');
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(401, $exception->getResponse()->getStatusCode());
            $this->assertSame([
                'message' => 'Invalid token.',
            ], $this->decodeJson($exception));
        }
    }

    public function test_verify_token_throws_http_exception_when_service_returns_forbidden(): void
    {
        Http::fake([
            'https://auth.example/api/auth/token/verify' => Http::response([
                'message' => 'Missing permissions.',
            ], 403),
        ]);

        $service = new AtlasAuthService;

        try {
            $service->verifyToken('token-without-permissions');
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(403, $exception->getResponse()->getStatusCode());
            $this->assertSame([
                'message' => 'Missing permissions.',
            ], $this->decodeJson($exception));
        }
    }

    public function test_verify_token_throws_http_exception_when_service_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection error.'));

        $service = new AtlasAuthService;

        try {
            $service->verifyToken('token');
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(503, $exception->getResponse()->getStatusCode());
            $this->assertSame([
                'message' => 'Authentication service unavailable.',
            ], $this->decodeJson($exception));
        }
    }

    public function test_verify_token_throws_http_exception_when_http_client_fails_request(): void
    {
        Http::fake([
            'https://auth.example/api/auth/token/verify' => Http::response([
                'error' => 'Unexpected failure',
            ], 500),
        ]);

        $service = new AtlasAuthService;

        try {
            $service->verifyToken('token');
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(503, $exception->getResponse()->getStatusCode());
            $this->assertSame([
                'message' => 'Authentication service unavailable.',
            ], $this->decodeJson($exception));
        }
    }

    public function test_verify_token_throws_http_exception_when_generic_exception_is_thrown(): void
    {
        Http::fake(fn () => throw new \RuntimeException('boom'));

        $service = new AtlasAuthService;

        try {
            $service->verifyToken('token');
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(503, $exception->getResponse()->getStatusCode());
            $this->assertSame([
                'message' => 'Authentication service unavailable.',
            ], $this->decodeJson($exception));
        }
    }

    public function test_verify_token_throws_http_exception_when_authorized_flag_is_false(): void
    {
        Http::fake([
            'https://auth.example/api/auth/token/verify' => Http::response([
                'authorized' => false,
                'reason' => 'Suspended account',
            ], 200),
        ]);

        $service = new AtlasAuthService;

        try {
            $service->verifyToken('token');
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(403, $exception->getResponse()->getStatusCode());
            $this->assertSame([
                'authorized' => false,
                'reason' => 'Suspended account',
            ], $this->decodeJson($exception));
        }
    }

    public function test_verify_token_throws_http_exception_when_service_url_is_missing(): void
    {
        Config::set('services.atlas_auth.url', '');

        $service = new AtlasAuthService;

        try {
            $service->verifyToken('token');
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(503, $exception->getResponse()->getStatusCode());
            $this->assertSame([
                'message' => 'Authentication service unavailable.',
            ], $this->decodeJson($exception));
        }
    }

    public function test_verify_token_requires_non_empty_token(): void
    {
        $service = new AtlasAuthService;

        try {
            $service->verifyToken('');
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(401, $exception->getResponse()->getStatusCode());
            $this->assertSame([
                'message' => 'Unauthenticated.',
            ], $this->decodeJson($exception));
        }
    }

    /**
     * @return array<mixed>
     */
    private function decodeJson(HttpResponseException $exception): array
    {
        return json_decode($exception->getResponse()->getContent(), true) ?? [];
    }
}
