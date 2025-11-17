<?php

namespace Tests\Feature\AuthIntegration;

use App\Models\GroupMember;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Services\AtlasUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectAuthIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.atlas_auth.url', 'https://auth.example');
        Config::set('services.atlas_auth.timeout', 5);
        Http::preventStrayRequests();

        $this->app->instance(AtlasUserService::class, new AtlasUserService);
    }

    public function test_index_includes_remote_member_names(): void
    {
        $user = User::factory()->create();
        $status = ProjectStatus::firstOrCreate(['name' => 'Activo']);
        $project = Project::factory()->create(['status_id' => $status->id]);
        $group = ProjectGroup::create(['project_id' => $project->id, 'name' => 'Team A']);
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);

        Http::fake([
            'https://auth.example/api/auth/users/*' => Http::response([
                'id' => $user->id,
                'name' => 'Remote Member',
            ], 200),
        ]);

        $response = $this->getJson('/api/pg/projects');

        $response->assertOk();
        $payload = $response->json();
        $this->assertSame('Remote Member', $payload[0]['member_names']);

        Http::assertSent(function ($request) use ($user) {
            return $request->url() === 'https://auth.example/api/auth/users/'.$user->id
                && $request->hasHeader('Authorization', 'Bearer test-token');
        });
    }

    public function test_index_returns_service_unavailable_when_auth_fails(): void
    {
        $user = User::factory()->create();
        $status = ProjectStatus::firstOrCreate(['name' => 'Activo']);
        $project = Project::factory()->create(['status_id' => $status->id]);
        $group = ProjectGroup::create(['project_id' => $project->id, 'name' => 'Team A']);
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);

        Http::fake([
            'https://auth.example/api/auth/users/*' => Http::response([], 500),
        ]);

        $this->getJson('/api/pg/projects')
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Authentication service unavailable.']);
    }

    public function test_index_requires_token_for_remote_lookup(): void
    {
        $user = User::factory()->create();
        $status = ProjectStatus::firstOrCreate(['name' => 'Activo']);
        $project = Project::factory()->create(['status_id' => $status->id]);
        $group = ProjectGroup::create(['project_id' => $project->id, 'name' => 'Team A']);
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);

        Http::fake();

        $this->withHeaders(['Authorization' => ''])
            ->getJson('/api/pg/projects')
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);

        Http::assertNothingSent();
    }
}
