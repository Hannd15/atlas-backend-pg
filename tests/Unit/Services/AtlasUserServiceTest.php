<?php

namespace Tests\Unit\Services;

use App\Services\AtlasUserService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AtlasUserServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Config::set('services.atlas_auth.url', 'https://auth.example');
        Config::set('services.atlas_auth.timeout', 5);
    }

    public function test_list_users_sends_request_and_returns_payload(): void
    {
        Http::fake(function (HttpRequest $request) {
            return Http::response([
                ['id' => 5, 'name' => 'Remote User'],
            ], 200);
        });

        $service = new AtlasUserService;

        $result = $service->listUsers('token-123');

        $this->assertSame([
            ['id' => 5, 'name' => 'Remote User'],
        ], $result);

        Http::assertSent(function (HttpRequest $request) {
            return $request->url() === 'https://auth.example/api/auth/users'
                && $request->method() === 'GET'
                && $request->hasHeader('Authorization', 'Bearer token-123');
        });
    }

    public function test_get_user_throws_http_exception_for_not_found(): void
    {
        Http::fake([
            'https://auth.example/api/auth/users/9' => Http::response([
                'message' => 'User not found.',
            ], 404),
        ]);

        $service = new AtlasUserService;

        try {
            $service->getUser('token', 9);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(404, $exception->getResponse()->getStatusCode());
            $this->assertSame([
                'message' => 'User not found.',
            ], $this->decodeJson($exception));
        }
    }

    public function test_update_user_propagates_validation_errors(): void
    {
        Http::fake([
            'https://auth.example/api/auth/users/7' => Http::response([
                'message' => 'The email field must be unique.',
                'errors' => ['email' => ['The email has already been taken.']],
            ], 422),
        ]);

        $service = new AtlasUserService;

        try {
            $service->updateUser('token', 7, ['email' => 'duplicate@example.com']);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(422, $exception->getResponse()->getStatusCode());
            $this->assertSame([
                'message' => 'The email field must be unique.',
                'errors' => ['email' => ['The email has already been taken.']],
            ], $this->decodeJson($exception));
        }
    }

    public function test_fetch_users_by_ids_skips_missing_and_returns_results(): void
    {
        Http::fake([
            'https://auth.example/api/auth/users/*' => Http::sequence()
                ->push(['id' => 3, 'name' => 'Remote Three'], 200)
                ->push(['message' => 'Not found'], 404)
                ->push(['id' => 5, 'name' => 'Remote Five'], 200),
        ]);

        $service = new AtlasUserService;

        $result = $service->fetchUsersByIds('token', [3, null, 4, 5, 3]);

        $this->assertSame([
            3 => ['id' => 3, 'name' => 'Remote Three'],
            5 => ['id' => 5, 'name' => 'Remote Five'],
        ], $result);

        Http::assertSentCount(3);
    }

    public function test_names_by_ids_returns_only_found_names(): void
    {
        Http::fake([
            'https://auth.example/api/auth/users/*' => Http::sequence()
                ->push(['id' => 2, 'name' => 'Alice Remote'], 200)
                ->push(['message' => 'Not found'], 404)
                ->push(['id' => 4, 'name' => null], 200)
                ->push(['id' => 6, 'name' => 'Charlie Remote'], 200),
        ]);

        $service = new AtlasUserService;

        $names = $service->namesByIds('token', [2, 3, 4, 6]);

        $this->assertSame([
            2 => 'Alice Remote',
            6 => 'Charlie Remote',
        ], $names);

        Http::assertSentCount(4);
    }

    public function test_list_users_requires_non_empty_token(): void
    {
        $service = new AtlasUserService;

        try {
            $service->listUsers(' ');
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(401, $exception->getResponse()->getStatusCode());
            $this->assertSame([
                'message' => 'Unauthenticated.',
            ], $this->decodeJson($exception));
        }
    }

    public function test_list_users_throws_service_unavailable_on_server_error(): void
    {
        Http::fake([
            'https://auth.example/api/auth/users' => Http::response([], 500),
        ]);

        $service = new AtlasUserService;

        try {
            $service->listUsers('token');
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(503, $exception->getResponse()->getStatusCode());
            $this->assertSame([
                'message' => 'Authentication service unavailable.',
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
