<?php

namespace Tests\Feature\Middleware;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticateViaAtlasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.atlas_auth.url' => 'https://auth.example',
            'services.atlas_auth.timeout' => 10,
        ]);
    }

    public function test_successful_authentication_attaches_user_to_request(): void
    {
        Http::fake([
            'https://auth.example/api/auth/token/verify' => Http::response([
                'authorized' => true,
                'user' => [
                    'id' => 42,
                    'email' => 'user@example.com',
                ],
            ], 200),
        ]);

        Route::middleware('auth.atlas')->get('/middleware-success', function (Request $request) {
            return response()->json([
                'user' => $request->attributes->get('atlasUser'),
            ]);
        });

        $response = $this->getJson('/middleware-success', [
            'Authorization' => 'Bearer valid-token',
        ]);

        $response->assertOk()->assertJson([
            'user' => [
                'id' => 42,
                'email' => 'user@example.com',
            ],
        ]);
    }

    public function test_forwards_roles_and_permissions_when_configured(): void
    {
        $capturedPayload = null;

        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$capturedPayload) {
            $capturedPayload = $request->data();

            return Http::response([
                'authorized' => true,
                'user' => ['id' => 1],
            ], 200);
        });

        Route::middleware('auth.atlas:roles=admin|manager,permissions=view|edit')->post('/middleware-configured', function () {
            return response()->json(['status' => 'ok']);
        });

        $this->postJson('/middleware-configured', [], [
            'Authorization' => 'Bearer valid-token',
        ])->assertOk()->assertJson(['status' => 'ok']);

        $this->assertSame([
            'roles' => ['admin', 'manager'],
            'permissions' => ['view', 'edit'],
        ], $capturedPayload);
    }

    public function test_unauthorized_response_is_forwarded(): void
    {
        Http::fake([
            'https://auth.example/api/auth/token/verify' => Http::response([
                'message' => 'Invalid token.',
            ], 401),
        ]);

        Route::middleware('auth.atlas')->get('/middleware-unauthorized', fn () => response()->json());

        $this->getJson('/middleware-unauthorized', [
            'Authorization' => 'Bearer invalid-token',
        ])->assertStatus(401)->assertExactJson([
            'message' => 'Invalid token.',
        ]);
    }

    public function test_forbidden_response_is_forwarded(): void
    {
        Http::fake([
            'https://auth.example/api/auth/token/verify' => Http::response([
                'message' => 'Not enough permissions.',
            ], 403),
        ]);

        Route::middleware('auth.atlas')->get('/middleware-forbidden', fn () => response()->json());

        $this->getJson('/middleware-forbidden', [
            'Authorization' => 'Bearer forbidden-token',
        ])->assertStatus(403)->assertExactJson([
            'message' => 'Not enough permissions.',
        ]);
    }

    public function test_missing_token_results_in_unauthenticated_response(): void
    {
        Route::middleware('auth.atlas')->get('/middleware-missing-token', fn () => response()->json());

        $this->getJson('/middleware-missing-token')
            ->assertStatus(401)
            ->assertExactJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_network_error_returns_service_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection error.'));

        Route::middleware('auth.atlas')->get('/middleware-network-error', fn () => response()->json());

        $this->getJson('/middleware-network-error', [
            'Authorization' => 'Bearer token',
        ])->assertStatus(503)->assertExactJson([
            'message' => 'Authentication service unavailable.',
        ]);
    }

    public function test_authorized_false_in_successful_response_denied(): void
    {
        Http::fake([
            'https://auth.example/api/auth/token/verify' => Http::response([
                'authorized' => false,
                'reason' => 'Suspended account',
            ], 200),
        ]);

        Route::middleware('auth.atlas')->get('/middleware-authorized-false', fn () => response()->json());

        $this->getJson('/middleware-authorized-false', [
            'Authorization' => 'Bearer token',
        ])->assertStatus(403)->assertExactJson([
            'authorized' => false,
            'reason' => 'Suspended account',
        ]);
    }
}
