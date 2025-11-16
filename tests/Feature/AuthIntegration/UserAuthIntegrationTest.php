<?php

namespace Tests\Feature\AuthIntegration;

use App\Models\ProjectPosition;
use App\Models\User;
use App\Services\AtlasUserService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserAuthIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.atlas_auth.url', 'https://auth.example');
        Config::set('services.atlas_auth.timeout', 5);
        Http::preventStrayRequests();

        $this->app->instance(AtlasUserService::class, new AtlasUserService);
    }

    public function test_index_merges_remote_user_payload(): void
    {
        $position = ProjectPosition::create(['name' => 'Director']);
        $user = User::factory()->create(['name' => 'Local Name', 'email' => 'local@example.com']);
        $user->eligiblePositions()->sync([$position->id]);

        Http::fake([
            'https://auth.example/api/auth/users' => Http::response([
                ['id' => $user->id, 'name' => 'Remote User', 'email' => 'remote@example.com', 'roles' => ['Director']],
            ], 200),
        ]);

        $response = $this->getJson('/api/pg/users');

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $user->id,
            'name' => 'Remote User',
            'email' => 'remote@example.com',
            'roles' => ['Director'],
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://auth.example/api/auth/users'
                && $request->hasHeader('Authorization', 'Bearer test-token');
        });
    }

    public function test_index_bubbles_up_remote_forbidden_error(): void
    {
        User::factory()->count(2)->create();

        Http::fake([
            'https://auth.example/api/auth/users' => Http::response([
                'message' => 'Token expired.',
            ], 403),
        ]);

        $this->getJson('/api/pg/users')
            ->assertStatus(403)
            ->assertExactJson(['message' => 'Token expired.']);
    }

    public function test_index_requires_bearer_token(): void
    {
        User::factory()->create();

        Http::fake();

        $this->withHeaders(['Authorization' => ''])
            ->getJson('/api/pg/users')
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Missing bearer token.']);

        Http::assertNothingSent();
    }

    public function test_update_persists_local_changes_and_calls_remote_service(): void
    {
        $user = User::factory()->create(['name' => 'Original', 'email' => 'original@example.com']);
        $position = ProjectPosition::create(['name' => 'Director']);

        Http::fake([
            'https://auth.example/api/auth/users/'.$user->id => Http::response([
                'id' => $user->id,
                'name' => 'Remote Updated',
                'email' => 'updated@example.com',
            ], 200),
        ]);

        $payload = [
            'name' => 'Remote Updated',
            'email' => 'updated@example.com',
            'project_position_eligibility_ids' => [$position->id],
        ];

        $this->putJson('/api/pg/users/'.$user->id, $payload)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $user->id,
                'name' => 'Remote Updated',
                'email' => 'updated@example.com',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Remote Updated',
            'email' => 'updated@example.com',
        ]);

        Http::assertSent(function ($request) use ($user) {
            return $request->url() === 'https://auth.example/api/auth/users/'.$user->id
                && $request->method() === 'PUT'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['name'] === 'Remote Updated'
                && $request['email'] === 'updated@example.com';
        });
    }
}
